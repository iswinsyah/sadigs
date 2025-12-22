<?php
header('Content-Type: application/json');
require_once 'db_connect.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id'], $_SESSION['username'])) {
    sendJSONResponse(['success' => false, 'message' => 'Unauthorized'], 401);
}

$pdo = getDBConnection();
$parent_username = $_SESSION['username'];

try {
    // Cari semua user_id santri yang kolom parent_username-nya cocok dengan username walisantri yang login
    $sql = "SELECT u.user_id, u.username, u.full_name 
            FROM users u
            JOIN student_details sd ON u.user_id = sd.user_id
            WHERE sd.parent_username = ?";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$parent_username]);
    $children = $stmt->fetchAll(PDO::FETCH_ASSOC);

    sendJSONResponse(['success' => true, 'children' => $children]);

} catch (Exception $e) {
    sendJSONResponse(['success' => false, 'message' => 'Gagal mengambil data: ' . $e->getMessage()], 500);
}
?>