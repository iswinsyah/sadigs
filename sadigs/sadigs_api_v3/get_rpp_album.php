<?php
// API: Get RPP Album List
header('Content-Type: application/json');
require_once 'db_connect.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id'])) {
    sendJSONResponse(['success' => false, 'message' => 'Unauthorized'], 401);
    exit;
}

$user_id = $_SESSION['user_id'];

try {
    $pdo = getDBConnection();
    // Ambil daftar RPP milik user yang sedang login
    $stmt = $pdo->prepare("SELECT id, subject, grade, topic, DATE_FORMAT(created_at, '%d %b %Y') as created_at_formatted FROM rpp_album WHERE user_id = ? ORDER BY created_at DESC");
    $stmt->execute([$user_id]);
    $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

    sendJSONResponse(['success' => true, 'data' => $data]);

} catch (Exception $e) {
    sendJSONResponse(['success' => false, 'message' => 'Database error: ' . $e->getMessage()], 500);
}
?>