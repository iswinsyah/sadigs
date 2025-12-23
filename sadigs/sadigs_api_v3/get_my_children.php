<?php
header('Content-Type: application/json');
require_once 'db_connect.php';

if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['user_id'])) {
    sendJSONResponse(['success' => false, 'message' => 'Unauthorized'], 401);
}

$pdo = getDBConnection();
$walisantri_id = $_SESSION['user_id'];

try {
    // 1. Ambil username Walisantri yang sedang login
    $stmt = $pdo->prepare("SELECT username FROM users WHERE user_id = ?");
    $stmt->execute([$walisantri_id]);
    $wali_username = $stmt->fetchColumn();

    if (!$wali_username) {
        sendJSONResponse(['success' => false, 'message' => 'User tidak ditemukan'], 404);
    }

    // 2. Cari Santri yang kolom 'parent_username'-nya SAMA PERSIS dengan username Walisantri ini
    // Ini adalah kunci privasinya. Hanya data yang cocok yang diambil.
    $sql = "SELECT u.user_id, u.full_name, u.username 
            FROM users u
            JOIN student_details sd ON u.user_id = sd.user_id
            WHERE sd.parent_username = ? 
            AND u.user_id IN (SELECT user_id FROM user_roles WHERE role_name = 'Santri')";
            
    $stmtChildren = $pdo->prepare($sql);
    $stmtChildren->execute([$wali_username]);
    $children = $stmtChildren->fetchAll(PDO::FETCH_ASSOC);

    sendJSONResponse(['success' => true, 'children' => $children]);

} catch (Exception $e) {
    sendJSONResponse(['success' => false, 'message' => $e->getMessage()], 500);
}
?>