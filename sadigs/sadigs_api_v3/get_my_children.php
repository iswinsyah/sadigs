<?php
header('Content-Type: application/json');
require_once 'db_connect.php';

if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['user_id'], $_SESSION['username'])) {
    sendJSONResponse(['success' => false, 'message' => 'Unauthorized'], 401);
}

$pdo = getDBConnection();
$walisantri_username = $_SESSION['username'];

try {
    // Cari santri yang field parent_username-nya cocok dengan username walisantri yang login
    $stmt = $pdo->prepare("
        SELECT u.user_id, sd.full_name, u.username 
        FROM student_data sd
        JOIN users u ON sd.user_id = u.user_id
        WHERE sd.parent_username = ?
    ");
    $stmt->execute([$walisantri_username]);
    $children = $stmt->fetchAll(PDO::FETCH_ASSOC);

    sendJSONResponse(['success' => true, 'children' => $children]);

} catch (Exception $e) {
    sendJSONResponse(['success' => false, 'message' => 'Database error: ' . $e->getMessage()], 500);
}
?>