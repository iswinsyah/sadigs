<?php
header('Content-Type: application/json');
require_once 'db_connect.php';

if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$pdo = getDBConnection();

try {
    // Ambil user dengan role Musyrif/Musyrifah/Kepala Asrama
    $sql = "SELECT u.user_id, u.username, u.full_name, u.gender, ur.role_name 
            FROM users u 
            JOIN user_roles ur ON u.user_id = ur.user_id 
            WHERE ur.role_name IN ('Musyrif', 'Musyrifah', 'Kepala Asrama Putra', 'Kepala Asrama Putri') 
            AND ur.status = 'approved'
            GROUP BY u.user_id
            ORDER BY u.full_name ASC";
    
    $stmt = $pdo->query($sql);
    echo json_encode(['success' => true, 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>