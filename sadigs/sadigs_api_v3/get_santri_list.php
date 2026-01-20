<?php
header('Content-Type: application/json');
require_once 'db_connect.php';

if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['user_id'])) {
    sendJSONResponse(['success' => false, 'message' => 'Unauthorized'], 401);
}

$pdo = getDBConnection();

try {
    // Ambil semua user yang memiliki role 'Santri', 'Santri Rijal', atau 'Santri Nisa''
    // Diurutkan berdasarkan Nama Lengkap agar mudah dicari
    $sql = "
        SELECT DISTINCT u.username, u.full_name, ur.role_name
        FROM users u
        JOIN user_roles ur ON u.user_id = ur.user_id
        WHERE ur.role_name LIKE 'Santri%'
        ORDER BY u.full_name ASC
    ";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    sendJSONResponse(['success' => true, 'data' => $data]);
} catch (Exception $e) {
    sendJSONResponse(['success' => false, 'message' => $e->getMessage()], 500);
}
?>