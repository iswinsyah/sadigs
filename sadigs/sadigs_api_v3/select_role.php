<?php
header('Content-Type: application/json');
require_once 'db_connect.php';

if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['user_id'])) {
    sendJSONResponse(['success' => false, 'message' => 'Unauthorized'], 401);
}

$input = json_decode(file_get_contents('php://input'), true);
$roles = $input['roles'] ?? [];
$user_id = $_SESSION['user_id'];

if (empty($roles)) {
    sendJSONResponse(['success' => false, 'message' => 'Tidak ada peran yang dipilih.'], 400);
}

try {
    $pdo = getDBConnection();
    $pdo->beginTransaction();

    // Siapkan statement insert
    // Status default 'pending' agar menunggu validasi admin/yayasan
    $stmt = $pdo->prepare("INSERT INTO user_roles (user_id, role_name, status) VALUES (?, ?, 'pending') ON DUPLICATE KEY UPDATE status = status");

    foreach ($roles as $role) {
        // Validasi nama role sederhana (opsional)
        $stmt->execute([$user_id, $role]);
    }

    $pdo->commit();
    
    // Update session roles agar perubahan langsung terasa (opsional, tapi bagus untuk UX)
    // Namun idealnya user harus relogin atau refresh dashboard untuk melihat status 'pending'
    
    sendJSONResponse(['success' => true, 'message' => 'Peran berhasil diajukan. Mohon tunggu validasi admin.']);

} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    sendJSONResponse(['success' => false, 'message' => 'Database error: ' . $e->getMessage()], 500);
}
?>