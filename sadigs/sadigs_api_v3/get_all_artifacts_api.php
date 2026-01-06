<?php
header('Content-Type: application/json');
require_once 'db_connect.php';

if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$user_id = $_SESSION['user_id'];

try {
    $pdo = getDBConnection();
    
    // Cek apakah tabel ada
    $check = $pdo->query("SHOW TABLES LIKE 'teaching_artifacts'");
    if ($check->rowCount() == 0) {
        echo json_encode(['success' => true, 'data' => []]);
        exit;
    }

    // Ambil semua data, urutkan dari yang terbaru
    $stmt = $pdo->prepare("SELECT id, subject, grade, fase, type, topic, status, created_at, content FROM teaching_artifacts WHERE user_id = ? ORDER BY created_at DESC");
    $stmt->execute([$user_id]);
    $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(['success' => true, 'data' => $data]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
?>