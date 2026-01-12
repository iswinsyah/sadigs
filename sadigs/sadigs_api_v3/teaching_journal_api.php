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
$user_id = $_SESSION['user_id'];

// Auto-Create Table
$pdo->exec("CREATE TABLE IF NOT EXISTS teaching_journal (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    teaching_date DATE NOT NULL,
    start_time TIME NOT NULL,
    end_time TIME NOT NULL,
    grade VARCHAR(50) NOT NULL,
    subject VARCHAR(100) NOT NULL,
    topic TEXT,
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)");

// Update Schema: Tambah kolom lokasi jika belum ada
try { $pdo->exec("ALTER TABLE teaching_journal ADD COLUMN location_lat VARCHAR(50) NULL"); } catch (Exception $e) { }
try { $pdo->exec("ALTER TABLE teaching_journal ADD COLUMN location_long VARCHAR(50) NULL"); } catch (Exception $e) { }
// Update agar kolom ini boleh NULL (untuk sistem Start/End)
try { $pdo->exec("ALTER TABLE teaching_journal MODIFY end_time TIME NULL"); } catch (Exception $e) { }
try { $pdo->exec("ALTER TABLE teaching_journal MODIFY topic TEXT NULL"); } catch (Exception $e) { }
try { $pdo->exec("ALTER TABLE teaching_journal MODIFY notes TEXT NULL"); } catch (Exception $e) { }
// Update Baru: Jam Ke & Santri Absen (Pisahkan try-catch agar tidak skip jika error di atas)
try { $pdo->exec("ALTER TABLE teaching_journal ADD COLUMN period_index INT NULL"); } catch (Exception $e) { }
try { $pdo->exec("ALTER TABLE teaching_journal ADD COLUMN absent_students TEXT NULL"); } catch (Exception $e) { }

try {
    if ($action === 'get_active_session') {
        // Cek apakah ada sesi yang belum selesai hari ini
        $stmt = $pdo->prepare("SELECT * FROM teaching_journal WHERE user_id = ? AND teaching_date = CURDATE() AND end_time IS NULL ORDER BY id DESC LIMIT 1");
        $stmt->execute([$user_id]);
        $data = $stmt->fetch(PDO::FETCH_ASSOC);
        echo json_encode(['success' => true, 'data' => $data ?: null]);
    }
    elseif ($action === 'get_absent_students') {
        // Ambil data santri yang sedang izin/sakit HARI INI (Status Approved)
        // Digunakan untuk referensi otomatis di form jurnal
        $stmt = $pdo->query("SELECT student_name, leave_type FROM guardian_leave_requests 
                             WHERE status = 'approved' 
                             AND CURDATE() BETWEEN start_date AND end_date");
        $absentees = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode(['success' => true, 'data' => $absentees]);
    }
    elseif ($action === 'start_class') {
        // MULAI MENGAJAR
        $data = json_decode(file_get_contents('php://input'), true);
        
        // Cek double session
        $stmtCheck = $pdo->prepare("SELECT id FROM teaching_journal WHERE user_id = ? AND teaching_date = CURDATE() AND end_time IS NULL");
        $stmtCheck->execute([$user_id]);
        if ($stmtCheck->fetch()) {
             echo json_encode(['success' => false, 'message' => 'Anda masih memiliki sesi mengajar yang belum selesai.']);
             exit;
        }

        $stmt = $pdo->prepare("INSERT INTO teaching_journal (user_id, teaching_date, start_time, grade, subject, period_index, location_lat, location_long) VALUES (?, CURDATE(), CURTIME(), ?, ?, ?, ?, ?)");
        $stmt->execute([
            $user_id, 
            $data['grade'], 
            $data['subject'], 
            $data['period_index'] ?? null,
            $data['latitude'] ?? null,
            $data['longitude'] ?? null
        ]);
        
        echo json_encode(['success' => true, 'message' => 'Selamat mengajar! Waktu mulai tercatat.']);

    } elseif ($action === 'end_class') {
        // SELESAI MENGAJAR
        $data = json_decode(file_get_contents('php://input'), true);
        $id = $data['id'];
        
        $stmt = $pdo->prepare("UPDATE teaching_journal SET end_time = CURTIME(), topic = ?, notes = ?, absent_students = ?, location_lat = COALESCE(location_lat, ?), location_long = COALESCE(location_long, ?) WHERE id = ? AND user_id = ?");
        $stmt->execute([
            $data['topic'], 
            $data['notes'], 
            $data['absent_students'] ?? '',
            $data['latitude'] ?? null,
            $data['longitude'] ?? null,
            $id, 
            $user_id
        ]);
        echo json_encode(['success' => true, 'message' => 'Sesi mengajar selesai. Terima kasih!']);

    } elseif ($action === 'get_my_history') {
        // Riwayat Pribadi Ustadz
        $month = $_GET['month'] ?? date('Y-m');
        $stmt = $pdo->prepare("SELECT *, TIMEDIFF(end_time, start_time) as duration FROM teaching_journal WHERE user_id = ? AND teaching_date LIKE ? ORDER BY teaching_date DESC, start_time DESC");
        $stmt->execute([$user_id, "$month%"]);
        echo json_encode(['success' => true, 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);

    } elseif ($action === 'get_recap') {
        // Rekap untuk Yayasan (Semua Ustadz)
        // Cek Hak Akses (Hanya Yayasan)
        $user_roles = $_SESSION['roles'] ?? [];
        $allowed = ['Ketua Yayasan', 'Sekretaris Yayasan', 'Bendahara Yayasan', 'Admin Sekolah'];
        if (empty(array_intersect($allowed, $user_roles))) {
            echo json_encode(['success' => false, 'message' => 'Akses ditolak.']);
            exit;
        }

        $start_date = $_GET['start_date'] ?? date('Y-m-01');
        $end_date = $_GET['end_date'] ?? date('Y-m-t');

        $sql = "SELECT j.*, u.full_name, u.username, TIMEDIFF(j.end_time, j.start_time) as duration 
                FROM teaching_journal j 
                JOIN users u ON j.user_id = u.user_id 
                WHERE j.teaching_date BETWEEN ? AND ? 
                ORDER BY j.teaching_date DESC, u.full_name ASC";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$start_date, $end_date]);
        
        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Analisis Ketepatan Waktu
        foreach ($data as &$row) {
            $row['punctuality'] = analyzePunctuality($row['start_time'], $row['end_time']);
        }
        
        echo json_encode(['success' => true, 'data' => $data]);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

// Fungsi Helper: Analisis Ketepatan Waktu
function analyzePunctuality($start, $end) {
    // Daftar Jam Pelajaran Standar
    $slots = [
        ['s' => '04:30', 'e' => '05:15'], ['s' => '05:15', 'e' => '06:00'],
        ['s' => '07:30', 'e' => '08:15'], ['s' => '08:15', 'e' => '09:00'],
        ['s' => '09:15', 'e' => '10:00'], ['s' => '10:00', 'e' => '10:45'],
        ['s' => '10:45', 'e' => '11:15'], ['s' => '15:15', 'e' => '16:00'],
        ['s' => '16:00', 'e' => '16:45'], ['s' => '16:45', 'e' => '17:30']
    ];

    $start_ts = strtotime($start);
    $end_ts = strtotime($end);
    $best_start_diff = 9999; $best_slot_start = null;
    $best_end_diff = 9999; $best_slot_end = null;

    // Cari slot jadwal terdekat
    foreach ($slots as $slot) {
        $diff_s = abs($start_ts - strtotime($slot['s']));
        if ($diff_s < $best_start_diff) { $best_start_diff = $diff_s; $best_slot_start = $slot['s']; }
        
        $diff_e = abs($end_ts - strtotime($slot['e']));
        if ($diff_e < $best_end_diff) { $best_end_diff = $diff_e; $best_slot_end = $slot['e']; }
    }

    $status = [];
    // Toleransi 5 menit
    if (($start_ts - strtotime($best_slot_start)) > 300) $status[] = "Telat " . round(($start_ts - strtotime($best_slot_start))/60) . "m";
    if ((strtotime($best_slot_end) - $end_ts) > 300) $status[] = "Plg Cepat " . round((strtotime($best_slot_end) - $end_ts)/60) . "m";

    return empty($status) ? "Tepat Waktu" : implode(", ", $status);
}
?>