<?php
header('Content-Type: application/json');
require_once 'db_connect.php';

if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$username = $_SESSION['username'];
$pdo = getDBConnection();

try {
    // Ambil data anak berdasarkan parent_username di tabel student_details
    // Join ke users untuk dapat nama & username anak
    $stmt = $pdo->prepare("
        SELECT u.user_id, u.username, u.full_name, u.gender, u.student_photo_path 
        FROM student_details sd
        JOIN users u ON sd.user_id = u.user_id
        WHERE sd.parent_username = ?
    ");
    $stmt->execute([$username]);
    $children = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(['success' => true, 'children' => $children]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>