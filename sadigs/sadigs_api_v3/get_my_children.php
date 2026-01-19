<?php
header('Content-Type: application/json');
require_once 'db_connect.php';

if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$username = $_SESSION['username'];
$user_id = $_SESSION['user_id'];
$pdo = getDBConnection();

try {
    // --- SELF-HEALING: Pastikan kolom 'grade' ada (Fix Data Tidak Ditemukan) ---
    try {
        $pdo->query("SELECT grade FROM student_details LIMIT 1");
    } catch (Exception $e) {
        // Jika error (kolom tidak ada), buat kolomnya
        $pdo->exec("ALTER TABLE student_details ADD COLUMN grade VARCHAR(20) NULL");
    }

    // --- SELF-HEALING: Pastikan kolom 'student_photo_path' ada ---
    try {
        $pdo->query("SELECT student_photo_path FROM student_details LIMIT 1");
    } catch (Exception $e) {
        $pdo->exec("ALTER TABLE student_details ADD COLUMN student_photo_path VARCHAR(255) NULL");
    }

    // Ambil nama lengkap user login untuk fallback pencarian
    $stmtUser = $pdo->prepare("SELECT full_name FROM users WHERE user_id = ?");
    $stmtUser->execute([$user_id]);
    $fullName = $stmtUser->fetchColumn();

    // Cari anak berdasarkan Username Wali ATAU Nama Lengkap Wali
    $sql = "
        SELECT u.user_id, u.username, u.full_name, u.gender, sd.student_photo_path, sd.grade 
        FROM student_details sd
        JOIN users u ON sd.user_id = u.user_id
        WHERE sd.parent_username = ? 
           OR (sd.parent_name IS NOT NULL AND sd.parent_name != '' AND sd.parent_name = ?)
    ";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$username, $fullName]);
    $children = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(['success' => true, 'data' => $children]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>