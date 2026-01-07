<?php
header('Content-Type: application/json');
require_once 'db_connect.php';

if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['user_id'])) {
    sendJSONResponse(['success' => false, 'message' => 'Unauthorized'], 401);
    exit;
}

$pdo = getDBConnection();
$user_id = $_SESSION['user_id'];
$action = $_GET['action'] ?? '';

// --- AUTO MIGRATION: Buat Tabel Jika Belum Ada ---
$pdo->exec("CREATE TABLE IF NOT EXISTS teacher_availability (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    subjects TEXT, -- JSON array mapel yang diampu
    availability TEXT, -- JSON object jam tersedia
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY (user_id)
)");

$pdo->exec("CREATE TABLE IF NOT EXISTS curriculum_requirements (
    id INT AUTO_INCREMENT PRIMARY KEY,
    grade VARCHAR(20),
    subject VARCHAR(100),
    method ENUM('Daring', 'Luring') DEFAULT 'Luring',
    subject_type ENUM('Diniyah', 'Diknas') DEFAULT 'Diknas',
    hours_per_week INT,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_req (grade, subject)
)");

// Migration: Add subject_type column if not exists
try {
    $pdo->query("SELECT subject_type FROM curriculum_requirements LIMIT 1");
} catch (Exception $e) {
    $pdo->exec("ALTER TABLE curriculum_requirements ADD COLUMN subject_type ENUM('Diniyah', 'Diknas') DEFAULT 'Diknas' AFTER subject");
}

// Migration: Add method column if not exists
try {
    $pdo->query("SELECT method FROM curriculum_requirements LIMIT 1");
} catch (Exception $e) {
    $pdo->exec("ALTER TABLE curriculum_requirements ADD COLUMN method ENUM('Daring', 'Luring') DEFAULT 'Luring' AFTER subject_type");
}
// --------------------------------------------------

try {
    // 1. GET AVAILABILITY (Untuk Ustadz)
    if ($action === 'get_availability') {
        $target_id = $user_id;
        
        // Admin Override
        if (isset($_GET['user_id']) && !empty($_GET['user_id'])) {
            $allowed_roles = ['Kepala Sekolah', 'Sekretaris Sekolah'];
            $user_roles = $_SESSION['roles'] ?? [];
            if (count(array_intersect($allowed_roles, $user_roles)) > 0) {
                $target_id = $_GET['user_id'];
            }
        }

        $stmt = $pdo->prepare("SELECT subjects, availability FROM teacher_availability WHERE user_id = ?");
        $stmt->execute([$target_id]);
        $data = $stmt->fetch(PDO::FETCH_ASSOC);

        // Ambil juga jadwal yang sudah FIX (assigned) dari hasil generate
        $assigned_slots = [];
        try {
            $stmtSched = $pdo->prepare("SELECT day, period_index, subject, grade FROM schedule_assignments WHERE teacher_id = ?");
            $stmtSched->execute([$target_id]);
            $assigned_slots = $stmtSched->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            // Tabel mungkin belum ada jika belum pernah generate, abaikan
        }
        
        if ($data) {
            $data['subjects'] = json_decode($data['subjects'], true);
            $data['availability'] = json_decode($data['availability'], true);
        } else {
            $data = ['subjects' => [], 'availability' => []];
        }
        
        $data['assigned_schedule'] = $assigned_slots; // Kirim data jadwal fix
        sendJSONResponse(['success' => true, 'data' => $data]);
    }

    // 2. SAVE AVAILABILITY (Untuk Ustadz)
    elseif ($action === 'save_availability') {
        $input = json_decode(file_get_contents('php://input'), true);
        $target_id = $user_id;

        // Admin Override
        if (isset($input['user_id']) && !empty($input['user_id'])) {
            $allowed_roles = ['Kepala Sekolah', 'Sekretaris Sekolah'];
            $user_roles = $_SESSION['roles'] ?? [];
            if (count(array_intersect($allowed_roles, $user_roles)) > 0) {
                $target_id = $input['user_id'];
            }
        }

        $subjects = json_encode($input['subjects'] ?? []);
        $availability = json_encode($input['availability'] ?? []);

        $stmt = $pdo->prepare("INSERT INTO teacher_availability (user_id, subjects, availability) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE subjects = VALUES(subjects), availability = VALUES(availability)");
        $stmt->execute([$target_id, $subjects, $availability]);
        
        sendJSONResponse(['success' => true, 'message' => 'Ketersediaan mengajar berhasil disimpan.']);
    }

    // 3. GET CURRICULUM (Untuk Admin)
    elseif ($action === 'get_curriculum') {
        $stmt = $pdo->query("SELECT * FROM curriculum_requirements ORDER BY grade ASC, subject ASC");
        sendJSONResponse(['success' => true, 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
    }

    // 4. SAVE CURRICULUM ITEM (Untuk Admin)
    elseif ($action === 'save_curriculum_item') {
        // Cek Role Admin
        $allowed = ['Kepala Sekolah', 'Ketua Yayasan', 'Sekretaris Sekolah'];
        $user_roles = $_SESSION['roles'] ?? [];
        if (empty(array_intersect($allowed, $user_roles))) {
            sendJSONResponse(['success' => false, 'message' => 'Akses ditolak.'], 403);
        }

        $input = json_decode(file_get_contents('php://input'), true);
        $grade = $input['grade'];
        $subject = $input['subject'];
        $method = $input['method'] ?? 'Luring';
        $subject_type = $input['subject_type'] ?? 'Diknas';
        $hours = (int)$input['hours'];

        if (!$grade || !$subject || $hours <= 0) {
            sendJSONResponse(['success' => false, 'message' => 'Data tidak valid.'], 400);
        }

        $stmt = $pdo->prepare("INSERT INTO curriculum_requirements (grade, subject, subject_type, method, hours_per_week) VALUES (?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE hours_per_week = VALUES(hours_per_week), subject_type = VALUES(subject_type), method = VALUES(method)");
        $stmt->execute([$grade, $subject, $subject_type, $method, $hours]);

        sendJSONResponse(['success' => true, 'message' => 'Data kurikulum disimpan.']);
    }

    // 5. DELETE CURRICULUM ITEM
    elseif ($action === 'delete_curriculum_item') {
        // Cek Role Admin
        $allowed = ['Kepala Sekolah', 'Ketua Yayasan', 'Sekretaris Sekolah'];
        $user_roles = $_SESSION['roles'] ?? [];
        if (empty(array_intersect($allowed, $user_roles))) {
            sendJSONResponse(['success' => false, 'message' => 'Akses ditolak.'], 403);
        }

        $input = json_decode(file_get_contents('php://input'), true);
        $id = $input['id'];

        $stmt = $pdo->prepare("DELETE FROM curriculum_requirements WHERE id = ?");
        $stmt->execute([$id]);

        sendJSONResponse(['success' => true, 'message' => 'Item dihapus.']);
    }

    else {
        sendJSONResponse(['success' => false, 'message' => 'Action not found'], 400);
    }

} catch (Exception $e) {
    sendJSONResponse(['success' => false, 'message' => 'Error: ' . $e->getMessage()], 500);
}
?>