<?php
header('Content-Type: application/json');
require_once 'db_connect.php';

if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['user_id'])) {
    sendJSONResponse(['success' => false, 'message' => 'Unauthorized'], 401);
}

$student_id = $_SESSION['user_id'];
$pdo = getDBConnection();

try {
    $stmt = $pdo->prepare("
        SELECT * 
        FROM tahfizh_reports 
        WHERE student_id = ? 
        ORDER BY report_date DESC
    ");
    $stmt->execute([$student_id]);
    $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

    sendJSONResponse(['success' => true, 'data' => $data]);

} catch (Exception $e) {
    sendJSONResponse(['success' => false, 'message' => 'Database error: ' . $e->getMessage()], 500);
}
?>