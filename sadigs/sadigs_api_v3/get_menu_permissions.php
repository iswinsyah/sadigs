<?php
require_once 'db_connect.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id'])) {
    sendJSONResponse(['success' => false, 'message' => 'Unauthorized'], 401);
}

$user_roles = $_SESSION['roles'] ?? [];

if (empty($user_roles)) {
    sendJSONResponse(['success' => true, 'permissions' => []]); // Kirim array kosong jika tidak punya peran
}

try {
    $pdo = getDBConnection();
    
    // Buat placeholder sebanyak jumlah peran
    $placeholders = implode(',', array_fill(0, count($user_roles), '?'));
    
    $sql = "SELECT menu_id FROM menu_permissions WHERE role_name IN ($placeholders) AND can_view = TRUE";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($user_roles);
    $permissions = $stmt->fetchAll(PDO::FETCH_COLUMN, 0); // Ambil hanya kolom menu_id
    
    sendJSONResponse(['success' => true, 'permissions' => array_unique($permissions)]);
} catch (Exception $e) {
    sendJSONResponse(['success' => false, 'message' => $e->getMessage()], 500);
}
?>