<?php
header('Content-Type: application/json');
require_once 'db_connect.php';

if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['user_id'])) {
    sendJSONResponse(['success' => false, 'message' => 'Unauthorized'], 401);
}

$pdo = getDBConnection();

try {
    // Ambil data user dan role-nya
    $sql = "SELECT u.user_id, u.username, u.full_name, u.email, u.password_hash,
                   GROUP_CONCAT(ur.role_name SEPARATOR ', ') as roles
            FROM users u 
            LEFT JOIN user_roles ur ON u.user_id = ur.user_id 
            GROUP BY u.user_id
            ORDER BY u.full_name ASC";
            
    $stmt = $pdo->query($sql);
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);

    sendJSONResponse(['success' => true, 'data' => $users]);

} catch (Exception $e) {
    sendJSONResponse(['success' => false, 'message' => $e->getMessage()], 500);
}
?>