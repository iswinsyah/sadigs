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

        // B. AMBIL DATA
        // 1. Kebutuhan Kurikulum (Apa yang harus diajarkan)
        $reqs = $pdo->query("SELECT * FROM curriculum_requirements ORDER BY hours_per_week DESC")->fetchAll(PDO::FETCH_ASSOC);
        
        // 2. Data Guru (Siapa yang bisa mengajar apa & kapan)
        // UPDATE: Urutkan berdasarkan updated_at ASC (Siapa cepat dia dapat prioritas dicek duluan)
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

        // C. ALGORITMA PENJADWALAN (GREEDY)
        $assignments = []; // Menyimpan state jadwal global untuk cek bentrok guru
        $unassigned = [];

        $days = ['Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'];
        $periods = 10;

        foreach ($reqs as $req) {
            $grade = $req['grade'];
            $subject = $req['subject'];
            $hoursNeeded = (int)$req['hours_per_week'];

            // Cari guru yang bisa mengajar mapel ini
            $candidates = array_filter($teachers, function($t) use ($subject) {
                return in_array($subject, $t['subjects']);
            });
            
            if (empty($candidates)) {
                $unassigned[] = "$subject Kelas $grade (Tidak ada guru pengampu)";
                continue;
            }

            // UPDATE LOGIKA: Coba semua kandidat, jangan cuma yang pertama.
            // Karena sudah diurutkan di query awal, ini otomatis menerapkan "Siapa Cepat Dia Dapat".
            $is_assigned = false;
            
            foreach ($candidates as $teacher) {
                // Simulasi: Cek apakah guru ini bisa memenuhi kuota jam yang dibutuhkan?
                $possible_slots = [];
                
                foreach ($days as $day) {
                    if (count($possible_slots) >= $hoursNeeded) break;
                    
                    $teacherSlots = $teacher['availability'][$day] ?? [];

                    for ($p = 1; $p <= $periods; $p++) {
                        if (count($possible_slots) >= $hoursNeeded) break;

                        // SYARAT 1: Guru bersedia di jam ini?
                        if (!in_array($p, $teacherSlots)) continue;

                        // SYARAT 2: Kelas ini sudah ada pelajaran belum di jam ini?
                        $classKey = "{$grade}|{$day}|{$p}";
                        if (isset($assignments[$classKey])) continue;

                        // SYARAT 3: Guru ini sudah mengajar di kelas LAIN belum di jam ini? (ANTI BENTROK)
                        $teacherKey = "TEACHER|{$teacher['user_id']}|{$day}|{$p}";
                        if (isset($assignments[$teacherKey])) continue;

                        // Jika lolos, catat sebagai slot potensial
                        $possible_slots[] = ['day' => $day, 'period' => $p];
                    }
                }

                // Jika guru ini bisa memenuhi kebutuhan jam (atau setidaknya sebagian besar), Assign!
                // Di sini kita set strict: harus bisa memenuhi SEMUA jam yang diminta agar jadwal rapi.
                // Jika ingin lebih longgar (partial), ubah kondisi ini.
                if (count($possible_slots) == $hoursNeeded) {
                    $stmt = $pdo->prepare("INSERT INTO schedule_assignments (grade, day, period_index, subject, teacher_id, teacher_name) VALUES (?, ?, ?, ?, ?, ?)");
                    
                    foreach ($possible_slots as $slot) {
                        $stmt->execute([$grade, $slot['day'], $slot['period'], $subject, $teacher['user_id'], $teacher['name']]);
                        
                        $assignments["{$grade}|{$slot['day']}|{$slot['period']}"] = true;
                        $assignments["TEACHER|{$teacher['user_id']}|{$slot['day']}|{$slot['period']}"] = true;
                    }
                    $is_assigned = true;
                    break; // Berhenti mencari guru lain untuk mapel ini
                }
            }

            if (!$is_assigned) {
                $unassigned[] = "$subject Kelas $grade (Tidak ada guru yang cocok/tersedia untuk $hoursNeeded JP)";
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