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

        // B. AMBIL DATA GURU & KLASIFIKASI (JURUS PAMUNGKAS - FIX)
        // Ambil data guru beserta role-nya, urutkan berdasarkan waktu submit (FCFS)
        // B. AMBIL DATA GURU & KLASIFIKASI
        // Urutkan berdasarkan waktu submit (FCFS) untuk memenuhi aturan "Siapa cepat dia dapat"
        $sql = "SELECT t.user_id, u.username, u.full_name, t.subjects, t.availability, t.updated_at,
                       GROUP_CONCAT(ur.role_name) as roles_str
                FROM teacher_availability t
                JOIN users u ON t.user_id = u.user_id
                LEFT JOIN user_roles ur ON t.user_id = ur.user_id
                GROUP BY t.user_id
                ORDER BY t.updated_at ASC"; // FCFS basis waktu submit
        
        $teachersRaw = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);

        $ustadzTamu = [];
        $ustadzTetap = [];

        // Definisi Role yang dianggap "Tamu" (Hanya mengajar, tidak ada jabatan struktural)
        // Walisantri dianggap netral/tamu jika mengajar.
        $pureTeachingRoles = ['Ustadz', 'Ustadzah', 'Walisantri'];

        foreach ($teachersRaw as $t) {
            $t['subjects'] = json_decode($t['subjects'], true) ?? [];
            $t['availability'] = json_decode($t['availability'], true) ?? [];
            $t['name'] = $t['full_name'] ?: $t['username'];
            
            $userRoles = explode(',', $t['roles_str'] ?? '');
            $userRoles = array_map('trim', $userRoles); // Bersihkan spasi
            
            // Cek apakah punya role struktural (selain Ustadz/Walisantri)
            $isTetap = false;
            foreach ($userRoles as $r) {
                if (!empty($r) && !in_array($r, $pureTeachingRoles)) {
                    $isTetap = true; // Punya jabatan lain (Kepsek, Musyrif, dll)
                    break;
                }
            }

            if ($isTetap) {
                $ustadzTetap[] = $t;
            } else {
                $ustadzTamu[] = $t;
            }
        }

        // GABUNGKAN: Prioritas Ustadz Tamu (FCFS), baru Ustadz Tetap (FCFS)
        // Karena Tamu diproses duluan, mereka akan mengisi slot kosong lebih dulu.
        // Jika Tetap menginginkan slot yang sudah diisi Tamu, Tetap akan kalah (karena slot sudah terisi).
        $teachers = array_merge($ustadzTamu, $ustadzTetap);

        // C. AMBIL KEBUTUHAN KURIKULUM
        // Kita butuh ID untuk tracking mana yang sudah terisi
        $reqs = $pdo->query("SELECT * FROM curriculum_requirements ORDER BY grade ASC, subject ASC")->fetchAll(PDO::FETCH_ASSOC);
        
        // Tracking status pemenuhan jam per requirement
        // Format: [req_id => jumlah_jam_terisi]
        $reqStatus = [];
        foreach ($reqs as $r) {
            $reqStatus[$r['id']] = 0;
        }

        // D. ALGORITMA PENJADWALAN (TEACHER-CENTRIC + PRIORITY)
        // D. ALGORITMA PENJADWALAN (TEACHER-CENTRIC + PRIORITY + RULE 7)
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
                    if (strcasecmp(trim($req['subject']), trim($subjName)) !== 0) continue;

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
                // Analisis penyebab kegagalan
                $candidates = array_filter($teachers, function($t) use ($req) {
                    return in_array(trim($req['subject']), array_map('trim', $t['subjects']));
                });

                if (empty($candidates)) {
                    $reason = "Belum ada guru yang mengampu mapel ini.";
                } else {
                    $reason = "Guru tersedia (" . count($candidates) . " orang), tapi jam ketersediaan mereka habis atau bentrok dengan mapel lain di kelas ini.";
                }

                $unassigned[] = "{$req['subject']} Kelas {$req['grade']} (Kurang " . ($needed - $filled) . " JP) -> $reason";
            }
        }

        echo json_encode([
            'success' => true, 
            'message' => 'Jadwal berhasil digenerate! Ustadz Tamu prioritas, Ustadz Tetap fleksibel.',
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