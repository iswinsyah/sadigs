<?php
header('Content-Type: application/json');
require_once 'db_connect.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id'])) {
    sendJSONResponse(['success' => false, 'message' => 'Unauthorized'], 401);
}

$pdo = getDBConnection();

try {
    // Ambil user yang memiliki role 'Musyrif' dan status approved
    $sql = "SELECT u.user_id, u.username, u.full_name 
            FROM users u 
            JOIN user_roles ur ON u.user_id = ur.user_id 
            WHERE ur.role_name = 'Musyrif' AND ur.status = 'approved' 
            ORDER BY u.full_name ASC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    sendJSONResponse(['success' => true, 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
} catch (Exception $e) {
    sendJSONResponse(['success' => false, 'message' => $e->getMessage()], 500);
}
?>