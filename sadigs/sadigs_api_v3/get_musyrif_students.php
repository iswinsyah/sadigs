<?php
header('Content-Type: application/json');
require_once 'db_connect.php';

if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['user_id'])) {
    sendJSONResponse(['success' => false, 'message' => 'Unauthorized'], 401);
}

$pdo = getDBConnection();
$musyrif_id = $_SESSION['user_id'];

try {
    // Ambil santri yang dimentori oleh user ini
    $sql = "SELECT u.user_id, u.full_name, u.username
            FROM users u
            JOIN mentoring_assignments ma ON u.user_id = ma.student_id
            WHERE ma.musyrif_id = ?
            ORDER BY u.full_name ASC";
            
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$musyrif_id]);
    sendJSONResponse(['success' => true, 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
} catch (Exception $e) {
    sendJSONResponse(['success' => false, 'message' => $e->getMessage()], 500);
}
?>