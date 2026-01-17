<?php
header('Content-Type: application/json');
require_once 'db_connect.php';

if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['user_id'])) {
    sendJSONResponse(['success' => false, 'message' => 'Unauthorized'], 401);
}

$user_id = $_SESSION['user_id'];
$pdo = getDBConnection();

try {
    // --- AUTO-FIX: Pastikan tabel kinerja ada (Self-Healing) ---
    // 1. Tabel Periode
    $pdo->exec("CREATE TABLE IF NOT EXISTS performance_periods (
        id INT AUTO_INCREMENT PRIMARY KEY,
        period_name VARCHAR(50) NOT NULL,
        start_date DATE NOT NULL,
        end_date DATE NOT NULL,
        is_active BOOLEAN DEFAULT 1,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");

    // 2. Cek/Buat Periode Aktif Bulan Ini
    $stmtCheckPeriod = $pdo->query("SELECT COUNT(*) FROM performance_periods WHERE is_active = 1");
    if ($stmtCheckPeriod->fetchColumn() == 0) {
        $currentMonth = date('F Y');
        $startDate = date('Y-m-01');
        $endDate = date('Y-m-t');
        $stmtInsertPeriod = $pdo->prepare("INSERT INTO performance_periods (period_name, start_date, end_date) VALUES (?, ?, ?)");
        $stmtInsertPeriod->execute([$currentMonth, $startDate, $endDate]);
    }

    // 3. Tabel KPI (Jaga-jaga jika belum ada)
    $pdo->exec("CREATE TABLE IF NOT EXISTS performance_kpi (
        id INT AUTO_INCREMENT PRIMARY KEY,
        role_name VARCHAR(50) NOT NULL,
        kpi_name VARCHAR(100) NOT NULL,
        kpi_type ENUM('automatic', 'manual') NOT NULL,
        weight DECIMAL(5,2) NOT NULL,
        description TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");

    // 4. Tabel Skor (Tanpa FK strict agar tidak error saat create)
    $pdo->exec("CREATE TABLE IF NOT EXISTS performance_scores (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        period_id INT NOT NULL,
        kpi_id INT NOT NULL,
        score DECIMAL(5,2) DEFAULT 0,
        assessor_id INT NULL,
        notes TEXT,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY unique_score (user_id, period_id, kpi_id)
    )");
    // -----------------------------------------------------------

    // 1. Tentukan Peran Utama (Prioritas peran pegawai)
    $stmtRole = $pdo->prepare("SELECT role_name FROM user_roles WHERE user_id = ? AND status = 'approved'");
    $stmtRole->execute([$user_id]);
    $roles = $stmtRole->fetchAll(PDO::FETCH_COLUMN);
    
    // Abaikan peran non-pegawai
    $payrollRoles = array_diff($roles, ['Walisantri', 'Santri', 'Santri Rijal', "Santri Nisa'"]);
    $role = reset($payrollRoles); // Ambil peran pertama yang relevan

    if (!$role) {
        sendJSONResponse(['success' => false, 'message' => 'Peran Anda tidak memiliki data penggajian.']);
    }

    // 2. Ambil Standar Gaji (Gaji Pokok & Potensi Bonus)
    $stmtStd = $pdo->prepare("
        SELECT sc.name, sc.type, ss.amount 
        FROM salary_standards ss
        JOIN salary_components sc ON ss.component_id = sc.id
        WHERE ss.role_name = ?
    ");
    $stmtStd->execute([$role]);
    $standards = $stmtStd->fetchAll(PDO::FETCH_ASSOC);
    
    $fixed_total = 0;
    $max_kpi_bonus = 0;

    foreach ($standards as $std) {
        if ($std['type'] === 'fixed') {
            $fixed_total += $std['amount'];
        } elseif (stripos($std['name'], 'Kinerja') !== false || stripos($std['name'], 'Bonus') !== false) {
            $max_kpi_bonus = $std['amount']; // Tangkap nilai maksimal bonus kinerja
        }
    }

    // 3. Ambil Konfigurasi Tarif (Zona Waktu, Rapat, Piket)
    $config = $pdo->query("SELECT config_key, config_value FROM payroll_config")->fetchAll(PDO::FETCH_KEY_PAIR);

    // 4. Hitung Pendapatan Harian (Absensi, Rapat, Piket)
    $currentMonthStart = date('Y-m-01');
    $currentMonthEnd = date('Y-m-t');
    
    $stmtAtt = $pdo->prepare("SELECT check_in_time, category, status FROM employee_attendance WHERE user_id = ? AND attendance_date BETWEEN ? AND ?");
    $stmtAtt->execute([$user_id, $currentMonthStart, $currentMonthEnd]);
    $attendance_records = $stmtAtt->fetchAll(PDO::FETCH_ASSOC);

    $attendance_allowance = 0;
    $meeting_allowance = 0;
    $piket_allowance = 0;
    
    $att_counts = ['green' => 0, 'yellow' => 0, 'orange' => 0, 'red' => 0];

    foreach ($attendance_records as $att) {
        if ($att['category'] === 'Absensi Harian' && $att['status'] !== 'Alpha') {
            $time = strtotime($att['check_in_time']);
            // Logika Zona Waktu (Hardcoded sementara, idealnya dari DB juga)
            if ($time <= strtotime('07:10:00')) {
                $attendance_allowance += $config['reward_zona_hijau'] ?? 0;
                $att_counts['green']++;
            } elseif ($time <= strtotime('07:30:00')) {
                $attendance_allowance += $config['reward_zona_kuning'] ?? 0;
                $att_counts['yellow']++;
            } elseif ($time <= strtotime('08:00:00')) {
                $attendance_allowance += $config['reward_zona_oranye'] ?? 0;
                $att_counts['orange']++;
            } else {
                $attendance_allowance += $config['reward_zona_merah'] ?? 0;
                $att_counts['red']++;
            }
        } elseif (strpos($att['category'], 'Rapat') !== false && $att['status'] === 'Hadir') {
            $meeting_allowance += $config['insentif_rapat'] ?? 0;
        } elseif (strpos($att['category'], 'Piket') !== false && $att['status'] === 'Hadir') {
            $piket_allowance += $config['insentif_piket'] ?? 0;
        }
    }

    // 5. Hitung Estimasi Bonus KPI (Real-time)
    // Ambil definisi KPI untuk peran ini
    $stmtKPI = $pdo->prepare("SELECT id, kpi_name, kpi_type, weight FROM performance_kpi WHERE role_name = ?");
    $stmtKPI->execute([$role]);
    $kpis = $stmtKPI->fetchAll(PDO::FETCH_ASSOC);

    $total_kpi_score = 0;
    
    // Ambil periode aktif
    $stmtPeriod = $pdo->query("SELECT id, start_date, end_date FROM performance_periods WHERE is_active = 1 ORDER BY start_date DESC LIMIT 1");
    $period = $stmtPeriod->fetch(PDO::FETCH_ASSOC);

    if ($period && !empty($kpis)) {
        foreach ($kpis as $kpi) {
            $score = 0;
            if ($kpi['kpi_type'] === 'automatic') {
                // --- LOGIKA AUTO KPI (Simplified) ---
                if ($kpi['kpi_name'] === 'Kedisiplinan Kehadiran') {
                    // Hitung hari kerja efektif
                    $start = new DateTime($period['start_date']);
                    $end = new DateTime($period['end_date']);
                    $end->modify('+1 day');
                    $interval = new DateInterval('P1D');
                    $periodRange = new DatePeriod($start, $interval, $end);
                    $workDays = 0;
                    foreach ($periodRange as $date) { if ($date->format('N') != 7) $workDays++; }
                    if ($workDays == 0) $workDays = 1;
                    
                    $stmtCount = $pdo->prepare("SELECT COUNT(*) FROM employee_attendance WHERE user_id = ? AND attendance_date BETWEEN ? AND ? AND status IN ('Hadir', 'Telat') AND category = 'Absensi Harian'");
                    $stmtCount->execute([$user_id, $period['start_date'], $period['end_date']]);
                    $present = $stmtCount->fetchColumn();
                    $score = min(100, round(($present / $workDays) * 100, 2));
                }
                elseif ($kpi['kpi_name'] === 'Kehadiran Rapat') {
                    $stmtRapat = $pdo->prepare("SELECT COUNT(*) FROM employee_attendance WHERE user_id = ? AND attendance_date BETWEEN ? AND ? AND status = 'Hadir' AND category LIKE '%Rapat%'");
                    $stmtRapat->execute([$user_id, $period['start_date'], $period['end_date']]);
                    $score = ($stmtRapat->fetchColumn() > 0) ? 100 : 0;
                }
                elseif ($kpi['kpi_name'] === 'Intensitas Simakan Hafalan') {
                     $stmtInput = $pdo->prepare("SELECT COUNT(DISTINCT report_date) FROM tahfizh_reports WHERE musyrif_id = ? AND report_date BETWEEN ? AND ?");
                     $stmtInput->execute([$user_id, $period['start_date'], $period['end_date']]);
                     $inputDays = $stmtInput->fetchColumn();
                     $targetDays = 24; 
                     $score = min(100, round(($inputDays / $targetDays) * 100, 2));
                }
            } else {
                // Manual KPI - Ambil dari DB
                $stmtS = $pdo->prepare("SELECT score FROM performance_scores WHERE user_id = ? AND period_id = ? AND kpi_id = ?");
                $stmtS->execute([$user_id, $period['id'], $kpi['id']]);
                $score = (float)$stmtS->fetchColumn();
            }
            $total_kpi_score += ($score * $kpi['weight'] / 100);
        }
    }

    // Hitung nominal bonus berdasarkan skor KPI
    $kpi_bonus = ($total_kpi_score / 100) * $max_kpi_bonus;

    // 6. Total Estimasi Take Home Pay
    $total_estimated = $fixed_total + $attendance_allowance + $meeting_allowance + $piket_allowance + $kpi_bonus;

    $data = [
        'role' => $role,
        'period' => date('F Y'),
        'base_salary' => $fixed_total,
        'attendance' => [
            'counts' => $att_counts,
            'total' => $attendance_allowance
        ],
        'meeting' => $meeting_allowance,
        'piket' => $piket_allowance,
        'kpi' => ['score' => round($total_kpi_score, 2), 'bonus' => $kpi_bonus],
        'grand_total' => $total_estimated
    ];

    sendJSONResponse(['success' => true, 'data' => $data]);

} catch (Exception $e) {
    sendJSONResponse(['success' => false, 'message' => $e->getMessage()], 500);
}
?>