<?php
header('Content-Type: application/json');
require_once 'db_connect.php';

if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['user_id'])) {
    sendJSONResponse(['success' => false, 'message' => 'Unauthorized'], 401);
}

$user_id = $_SESSION['user_id'];
$pdo = getDBConnection();

try {
    // Ambil data dari tabel users
    $stmt = $pdo->prepare("
        SELECT 
            u.username, 
            u.email, 
            u.full_name, 
            u.gender, 
            u.bio
        FROM users u
        WHERE u.user_id = ?
    ");
    $stmt->execute([$user_id]);
    $data = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($data) {
        sendJSONResponse(['success' => true, 'data' => $data]);
    } else {
        sendJSONResponse(['success' => false, 'message' => 'Profil tidak ditemukan.'], 404);
    }

} catch (Exception $e) {
    sendJSONResponse(['success' => false, 'message' => 'Database error: ' . $e->getMessage()], 500);
}
?>