<?php
header('Content-Type: application/json');
require_once 'db_connect.php';

if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$pdo = getDBConnection();
$action = $_GET['action'] ?? '';

// --- 1. PERSIAPAN TABEL JADWAL ---
$pdo->exec("CREATE TABLE IF NOT EXISTS schedule_assignments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    grade VARCHAR(10),
    day VARCHAR(20),
    period_index INT,
    subject VARCHAR(100),
    teacher_id INT,
    teacher_name VARCHAR(100),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_slot (grade, day, period_index) -- Satu kelas cuma bisa 1 mapel di 1 waktu
)");

try {
    if ($action === 'generate') {
        // A. BERSIHKAN JADWAL LAMA
        $pdo->exec("TRUNCATE TABLE schedule_assignments");

        // B. AMBIL DATA GURU (URUTKAN DARI YANG PERTAMA SUBMIT)
        // Ini kunci "Siapa Cepat Dia Dapat". Kita proses guru satu per satu.
        $teachersRaw = $pdo->query("
            SELECT t.user_id, u.username, u.full_name, t.subjects, t.availability 
            FROM teacher_availability t
            JOIN users u ON t.user_id = u.user_id
            ORDER BY t.updated_at ASC
        ")->fetchAll(PDO::FETCH_ASSOC);

        $teachers = [];
        foreach ($teachersRaw as $t) {
            $t['subjects'] = json_decode($t['subjects'], true) ?? [];
            $t['availability'] = json_decode($t['availability'], true) ?? [];
            $t['name'] = $t['full_name'] ?: $t['username'];
            $teachers[] = $t;
        }

        // C. AMBIL KEBUTUHAN KURIKULUM
        // Kita butuh ID untuk tracking mana yang sudah terisi
        $reqs = $pdo->query("SELECT * FROM curriculum_requirements ORDER BY grade ASC, subject ASC")->fetchAll(PDO::FETCH_ASSOC);
        
        // Tracking status pemenuhan jam per requirement
        // Format: [req_id => jumlah_jam_terisi]
        $reqStatus = [];
        foreach ($reqs as $r) {
            $reqStatus[$r['id']] = 0;
        }

        // D. ALGORITMA PENJADWALAN (TEACHER-CENTRIC)
        $assignments = []; // State jadwal global: "GRADE|DAY|PERIOD" => true, "TEACHER|ID|DAY|PERIOD" => true

        $days = ['Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'];
        $periods = 10;

        // Loop Guru (Prioritas Utama)
        foreach ($teachers as $teacher) {
            // Loop Mapel yang diampu guru ini
            foreach ($teacher['subjects'] as $subjName) {
                
                // Cari requirement kurikulum yang cocok dengan mapel guru ini
                foreach ($reqs as $req) {
                    // Cek kesamaan nama mapel (Case Insensitive)
                    if (strcasecmp($req['subject'], $subjName) !== 0) continue;

                    $reqId = $req['id'];
                    $needed = (int)$req['hours_per_week'];
                    $current = $reqStatus[$reqId];

                    // Jika requirement ini sudah penuh diisi guru lain, skip
                    if ($current >= $needed) continue;

                    // Hitung sisa jam yang dibutuhkan
                    $remaining = $needed - $current;
                    
                    // Cari slot kosong untuk guru ini di kelas ini
                    $slots_found = [];
                    foreach ($days as $day) {
                        if (count($slots_found) >= $remaining) break;
                        
                        $teacherSlots = $teacher['availability'][$day] ?? [];

                        for ($p = 1; $p <= $periods; $p++) {
                            if (count($slots_found) >= $remaining) break;

                            // SYARAT 1: Guru bersedia di jam ini?
                            if (!in_array($p, $teacherSlots)) continue;

                            // SYARAT 2: Guru ini sudah mengajar di kelas LAIN di jam ini? (ANTI BENTROK GURU)
                            if (isset($assignments["TEACHER|{$teacher['user_id']}|{$day}|{$p}"])) continue;

                            // SYARAT 3: Kelas ini sudah ada pelajaran lain di jam ini? (ANTI BENTROK KELAS)
                            if (isset($assignments["{$req['grade']}|{$day}|{$p}"])) continue;

                            // Slot OK
                            $slots_found[] = ['day' => $day, 'period' => $p];
                        }
                    }

                    // Eksekusi Penyimpanan Jadwal
                    if (!empty($slots_found)) {
                        $stmt = $pdo->prepare("INSERT INTO schedule_assignments (grade, day, period_index, subject, teacher_id, teacher_name) VALUES (?, ?, ?, ?, ?, ?)");
                        
                        foreach ($slots_found as $slot) {
                            $stmt->execute([$req['grade'], $slot['day'], $slot['period'], $req['subject'], $teacher['user_id'], $teacher['name']]);
                            
                            // Update State Global
                            $assignments["{$req['grade']}|{$slot['day']}|{$slot['period']}"] = true;
                            $assignments["TEACHER|{$teacher['user_id']}|{$slot['day']}|{$slot['period']}"] = true;
                            $reqStatus[$reqId]++;
                        }
                    }
                }
            }
        }

        // E. CEK YANG BELUM TERJADWAL (REPORTING)
        $unassigned = [];
        foreach ($reqs as $req) {
            $needed = (int)$req['hours_per_week'];
            $filled = $reqStatus[$req['id']];
            if ($filled < $needed) {
                $unassigned[] = "{$req['subject']} Kelas {$req['grade']} (Kurang " . ($needed - $filled) . " JP)";
            }
        }

        echo json_encode([
            'success' => true, 
            'message' => 'Jadwal berhasil digenerate otomatis!',
            'unassigned' => $unassigned
        ]);

    } elseif ($action === 'get_schedule') {
        // Ambil jadwal untuk ditampilkan
        $stmt = $pdo->query("SELECT * FROM schedule_assignments");
        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Reformat data agar mudah dirender di frontend
        // Structure: result[day][period][grade] = {subject, teacher}
        $schedule = [];
        foreach ($data as $row) {
            $schedule[$row['day']][$row['period_index']][$row['grade']] = [
                'subject' => $row['subject'],
                'teacher' => $row['teacher_name']
            ];
        }

        echo json_encode(['success' => true, 'data' => $schedule]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
?>