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
    // Ambil nama lengkap user login untuk fallback pencarian
    $stmtUser = $pdo->prepare("SELECT full_name FROM users WHERE user_id = ?");
    $stmtUser->execute([$user_id]);
    $fullName = $stmtUser->fetchColumn();

    // Cari anak berdasarkan Username Wali ATAU Nama Lengkap Wali
    $sql = "
        SELECT u.user_id, u.username, u.full_name, u.gender, u.student_photo_path, sd.grade 
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