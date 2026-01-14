<?php
header('Content-Type: application/json');
require_once 'db_connect.php';

if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['user_id'])) {
    sendJSONResponse(['success' => false, 'message' => 'Unauthorized'], 401);
}

// Cek Izin (Hanya Admin/Kepsek/Yayasan)
$allowed = ['Ketua Yayasan', 'Kepala Sekolah', 'Admin Sekolah', 'Sekretaris Sekolah'];
$user_roles = $_SESSION['roles'] ?? [];
if (empty(array_intersect($allowed, $user_roles))) {
    sendJSONResponse(['success' => false, 'message' => 'Akses ditolak.'], 403);
}

$pdo = getDBConnection();
$action = $_GET['action'] ?? '';

// --- AUTO-SCHEMA: Update tabel student_details ---
try {
    // Cek kolom grade
    $pdo->query("SELECT grade FROM student_details LIMIT 1");
} catch (Exception $e) {
    $pdo->exec("ALTER TABLE student_details ADD COLUMN grade VARCHAR(20) DEFAULT 'Belum Masuk Kelas'");
}

try {
    // Cek kolom status (akademik)
    $pdo->query("SELECT status FROM student_details LIMIT 1");
} catch (Exception $e) {
    $pdo->exec("ALTER TABLE student_details ADD COLUMN status VARCHAR(20) DEFAULT 'Aktif'");
}

try {
    if ($action === 'get_students') {
        // Ambil data santri + detail orang tua
        $sql = "SELECT u.user_id, u.full_name, u.gender, 
                       sd.father_name, sd.mother_name, 
                       sd.grade, sd.status
                FROM users u
                JOIN user_roles ur ON u.user_id = ur.user_id
                LEFT JOIN student_details sd ON u.user_id = sd.user_id
                WHERE ur.role_name IN ('Santri', 'Santri Rijal', 'Santri Nisa\'')
                GROUP BY u.user_id
                ORDER BY sd.grade ASC, u.full_name ASC";
        
        $stmt = $pdo->query($sql);
        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        sendJSONResponse(['success' => true, 'data' => $data]);
    } 
    elseif ($action === 'update_status') {
        $input = json_decode(file_get_contents('php://input'), true);
        $user_id = $input['user_id'];
        $grade = $input['grade']; // Bisa berisi 'Kelas 7', 'Lulus', 'Keluar', dll.
        
        // Logika: Jika grade adalah status keluar (Lulus/Pindah/Keluar), update status juga
        $status = 'Aktif';
        $grade_value = $grade;
        
        if (in_array($grade, ['Lulus', 'Pindah', 'Keluar', 'DO'])) {
            $status = 'Non-Aktif';
            // Grade tetap disimpan sebagai status terakhir atau label khusus
        }

        // Pastikan row ada di student_details
        $stmtCheck = $pdo->prepare("SELECT user_id FROM student_details WHERE user_id = ?");
        $stmtCheck->execute([$user_id]);
        if ($stmtCheck->rowCount() == 0) {
            $pdo->prepare("INSERT INTO student_details (user_id) VALUES (?)")->execute([$user_id]);
        }

        $stmt = $pdo->prepare("UPDATE student_details SET grade = ?, status = ? WHERE user_id = ?");
        $stmt->execute([$grade_value, $status, $user_id]);
        
        sendJSONResponse(['success' => true, 'message' => 'Data berhasil diperbarui.']);
    }
} catch (Exception $e) {
    sendJSONResponse(['success' => false, 'message' => $e->getMessage()], 500);
}
?>