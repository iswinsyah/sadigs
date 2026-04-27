<?php
header('Content-Type: application/json');
require_once 'db_connect.php';

if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$user_id = $_SESSION['user_id'];
$month = $_GET['month'] ?? date('Y-m'); // Format YYYY-MM

$pdo = getDBConnection();

try {
    $sql = "SELECT *, DATE_FORMAT(attendance_date, '%d %b %Y') as formatted_date 
            FROM employee_attendance 
            WHERE user_id = ? AND attendance_date LIKE ? 
            ORDER BY attendance_date DESC";
            
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$user_id, "$month%"]);
    
    echo json_encode(['success' => true, 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>