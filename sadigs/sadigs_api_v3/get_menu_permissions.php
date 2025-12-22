<?php
header('Content-Type: application/json');
require_once 'db_connect.php';

if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['user_id'])) {
    sendJSONResponse(['success' => false, 'message' => 'Unauthorized'], 401);
}

$pdo = getDBConnection();
$user_id = $_SESSION['user_id'];

try {
    // 1. Ambil Role User yang APPROVED
    $stmtRoles = $pdo->prepare("SELECT role_name FROM user_roles WHERE user_id = ? AND status = 'approved'");
    $stmtRoles->execute([$user_id]);
    $roles = $stmtRoles->fetchAll(PDO::FETCH_COLUMN);

    if (empty($roles)) {
        // Jika tidak ada role approved, tidak ada menu khusus yang tampil
        sendJSONResponse(['success' => true, 'permissions' => []]);
        exit;
    }

    // 2. Ambil Menu ID yang boleh dilihat oleh role-role tersebut
    // Menggunakan IN clause
    $placeholders = implode(',', array_fill(0, count($roles), '?'));
    $sql = "SELECT DISTINCT menu_id FROM menu_permissions WHERE role_name IN ($placeholders) AND can_view = 1";
    
    $stmtMenu = $pdo->prepare($sql);
    $stmtMenu->execute($roles);
    $allowedMenus = $stmtMenu->fetchAll(PDO::FETCH_COLUMN);

    sendJSONResponse(['success' => true, 'permissions' => $allowedMenus]);

} catch (Exception $e) {
    sendJSONResponse(['success' => false, 'message' => $e->getMessage()], 500);
}
?>