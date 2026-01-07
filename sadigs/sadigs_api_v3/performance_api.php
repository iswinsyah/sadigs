<?php
header('Content-Type: application/json');
require_once 'db_connect.php';

if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['user_id'])) {
    sendJSONResponse(['success' => false, 'message' => 'Unauthorized'], 401);
}

$pdo = getDBConnection();
$action = $_GET['action'] ?? '';
$user_id = $_SESSION['user_id']; // Current logged in user

try {
    // 1. GET ACTIVE PERIOD
    if ($action === 'get_current_period') {
        $stmt = $pdo->query("SELECT * FROM performance_periods WHERE is_active = 1 ORDER BY start_date DESC LIMIT 1");
        $period = $stmt->fetch(PDO::FETCH_ASSOC);
        sendJSONResponse(['success' => true, 'data' => $period]);
    }

    // 2. GET USTADZ SUMMARY (For Kepala Sekolah)
    elseif ($action === 'get_ustadz_summary') {
        // Cek Role
        $allowed = ['Kepala Sekolah', 'Ketua Yayasan', 'Sekretaris Sekolah'];
        $user_roles = $_SESSION['roles'] ?? [];
        if (empty(array_intersect($allowed, $user_roles))) {
            sendJSONResponse(['success' => false, 'message' => 'Access Denied'], 403);
        }

        $period_id = $_GET['period_id'];
        
        // Ambil semua Ustadz
        $sql = "SELECT u.user_id, u.full_name, u.username 
                FROM users u 
                JOIN user_roles ur ON u.user_id = ur.user_id 
                WHERE ur.role_name = 'Ustadz' AND ur.status = 'approved'
                GROUP BY u.user_id";
        $ustadzs = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);

        // Hitung nilai ringkas
        foreach ($ustadzs as &$u) {
            $u['final_score'] = calculateFinalScore($pdo, $u['user_id'], $period_id, 'Ustadz');
        }

        sendJSONResponse(['success' => true, 'data' => $ustadzs]);
    }

    // 3. GET DETAIL SCORE (View/Edit)
    elseif ($action === 'get_score_detail') {
        $target_user_id = $_GET['user_id'];
        $period_id = $_GET['period_id'];
        $role_name = 'Ustadz'; // Fokus Ustadz dulu

        // Ambil KPI
        $stmtKPI = $pdo->prepare("SELECT * FROM performance_kpi WHERE role_name = ?");
        $stmtKPI->execute([$role_name]);
        $kpis = $stmtKPI->fetchAll(PDO::FETCH_ASSOC);

        // Hitung/Ambil Nilai
        $scores = [];
        foreach ($kpis as $kpi) {
            $scoreVal = 0;
            $notes = '';

            if ($kpi['kpi_type'] === 'automatic') {
                // Hitung Real-time
                $scoreVal = calculateAutoKPI($pdo, $target_user_id, $period_id, $kpi['kpi_name']);
            } else {
                // Ambil dari DB
                $stmtScore = $pdo->prepare("SELECT score, notes FROM performance_scores WHERE user_id = ? AND period_id = ? AND kpi_id = ?");
                $stmtScore->execute([$target_user_id, $period_id, $kpi['id']]);
                $res = $stmtScore->fetch(PDO::FETCH_ASSOC);
                $scoreVal = $res ? (float)$res['score'] : 0;
                $notes = $res ? $res['notes'] : '';
            }

            $scores[] = [
                'kpi_id' => $kpi['id'],
                'kpi_name' => $kpi['kpi_name'],
                'kpi_type' => $kpi['kpi_type'],
                'weight' => $kpi['weight'],
                'score' => $scoreVal,
                'notes' => $notes
            ];
        }

        sendJSONResponse(['success' => true, 'data' => $scores]);
    }

    // 4. SAVE MANUAL SCORE
    elseif ($action === 'save_manual_score') {
        // Cek Role
        $allowed = ['Kepala Sekolah', 'Ketua Yayasan'];
        $user_roles = $_SESSION['roles'] ?? [];
        if (empty(array_intersect($allowed, $user_roles))) {
            sendJSONResponse(['success' => false, 'message' => 'Access Denied'], 403);
        }

        $input = json_decode(file_get_contents('php://input'), true);
        $target_user_id = $input['user_id'];
        $period_id = $input['period_id'];
        $kpi_id = $input['kpi_id'];
        $score = $input['score'];
        $notes = $input['notes'] ?? '';

        $stmt = $pdo->prepare("INSERT INTO performance_scores (user_id, period_id, kpi_id, score, assessor_id, notes) VALUES (?, ?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE score = VALUES(score), assessor_id = VALUES(assessor_id), notes = VALUES(notes)");
        $stmt->execute([$target_user_id, $period_id, $kpi_id, $score, $user_id, $notes]);

        sendJSONResponse(['success' => true, 'message' => 'Nilai tersimpan.']);
    }

} catch (Exception $e) {
    sendJSONResponse(['success' => false, 'message' => $e->getMessage()], 500);
}

// --- HELPER FUNCTIONS ---

function calculateAutoKPI($pdo, $user_id, $period_id, $kpi_name) {
    // Ambil rentang tanggal periode
    $stmtP = $pdo->prepare("SELECT start_date, end_date FROM performance_periods WHERE id = ?");
    $stmtP->execute([$period_id]);
    $period = $stmtP->fetch(PDO::FETCH_ASSOC);
    if (!$period) return 0;

    if ($kpi_name === 'Kedisiplinan Kehadiran') {
        // Hitung hari kerja (Senin-Sabtu)
        $start = new DateTime($period['start_date']);
        $end = new DateTime($period['end_date']);
        $end->modify('+1 day');
        $interval = new DateInterval('P1D');
        $periodRange = new DatePeriod($start, $interval, $end);

        $workDays = 0;
        foreach ($periodRange as $date) {
            if ($date->format('N') != 7) $workDays++; // 7 = Sunday
        }
        if ($workDays == 0) return 100; // Avoid division by zero

        // Hitung kehadiran
        // UPDATE: Hanya hitung 'Absensi Harian' agar tidak rancu dengan rapat
        $stmtAtt = $pdo->prepare("SELECT COUNT(*) FROM attendance WHERE user_id = ? AND date BETWEEN ? AND ? AND status IN ('Hadir', 'Telat') AND category = 'Absensi Harian'");
        $stmtAtt->execute([$user_id, $period['start_date'], $period['end_date']]);
        $presentDays = $stmtAtt->fetchColumn();

        return min(100, round(($presentDays / $workDays) * 100, 2));
    }

    if ($kpi_name === 'Kelengkapan Administrasi') {
        // Cek Promes (ATP) - Asumsi 1 per semester cukup
        $stmtPromes = $pdo->prepare("SELECT COUNT(*) FROM saved_promes WHERE user_id = ?");
        $stmtPromes->execute([$user_id]);
        $hasPromes = $stmtPromes->fetchColumn() > 0;

        // Cek Modul Ajar di bulan ini
        $stmtModul = $pdo->prepare("SELECT COUNT(*) FROM teaching_artifacts WHERE user_id = ? AND type = 'modul' AND created_at BETWEEN ? AND ?");
        $stmtModul->execute([$user_id, $period['start_date'] . ' 00:00:00', $period['end_date'] . ' 23:59:59']);
        $hasModul = $stmtModul->fetchColumn() > 0;

        $score = 0;
        if ($hasPromes) $score += 50;
        if ($hasModul) $score += 50;
        return $score;
    }

    if ($kpi_name === 'Kehadiran Rapat') {
        // Cek apakah ada absensi dengan kategori Rapat di bulan ini
        // Kita cari yang mengandung kata 'Rapat' atau spesifik 'Absensi Rapat Bulanan'
        $stmtRapat = $pdo->prepare("SELECT COUNT(*) FROM attendance WHERE user_id = ? AND date BETWEEN ? AND ? AND status = 'Hadir' AND category LIKE '%Rapat%'");
        $stmtRapat->execute([$user_id, $period['start_date'], $period['end_date']]);
        $rapatCount = $stmtRapat->fetchColumn();

        // Jika hadir minimal 1x rapat bulan ini, nilai 100. Jika tidak, 0.
        return ($rapatCount > 0) ? 100 : 0;
    }

    return 0;
}

function calculateFinalScore($pdo, $user_id, $period_id, $role_name) {
    $stmtKPI = $pdo->prepare("SELECT id, kpi_name, kpi_type, weight FROM performance_kpi WHERE role_name = ?");
    $stmtKPI->execute([$role_name]);
    $kpis = $stmtKPI->fetchAll(PDO::FETCH_ASSOC);

    $totalScore = 0;
    $totalWeight = 0;

    foreach ($kpis as $kpi) {
        $score = 0;
        if ($kpi['kpi_type'] === 'automatic') {
            $score = calculateAutoKPI($pdo, $user_id, $period_id, $kpi['kpi_name']);
        } else {
            $stmtS = $pdo->prepare("SELECT score FROM performance_scores WHERE user_id = ? AND period_id = ? AND kpi_id = ?");
            $stmtS->execute([$user_id, $period_id, $kpi['id']]);
            $score = (float)$stmtS->fetchColumn();
        }
        $totalScore += ($score * $kpi['weight'] / 100);
        $totalWeight += $kpi['weight'];
    }

    return round($totalScore, 2);
}
?>