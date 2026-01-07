<?php
header('Content-Type: application/json');
require_once 'db_connect.php';

if (session_status() === PHP_SESSION_NONE) session_start();

// 1. Cek Login
if (!isset($_SESSION['user_id'])) {
    sendJSONResponse(['success' => false, 'message' => 'Unauthorized'], 401);
}

// 2. Cek Role (Hanya Ketua Yayasan yang boleh melakukan ini)
$roles = $_SESSION['roles'] ?? [];
if (!in_array('Ketua Yayasan', $roles)) {
    sendJSONResponse(['success' => false, 'message' => 'Akses Ditolak. Hanya Ketua Yayasan yang berhak.'], 403);
}

$input = json_decode(file_get_contents('php://input'), true);
$target_user_id = $input['user_id'] ?? null;

if (!$target_user_id) {
    sendJSONResponse(['success' => false, 'message' => 'Target user tidak ditemukan.'], 400);
}

try {
    $pdo = getDBConnection();
    
    // Ambil data target user
    $stmt = $pdo->prepare("SELECT user_id, username, full_name FROM users WHERE user_id = ?");
    $stmt->execute([$target_user_id]);
    $targetUser = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$targetUser) {
        sendJSONResponse(['success' => false, 'message' => 'User tidak ditemukan.'], 404);
    }

    // Ambil role target user
    $stmtRoles = $pdo->prepare("SELECT role_name FROM user_roles WHERE user_id = ? AND status = 'approved'");
    $stmtRoles->execute([$target_user_id]);
    $targetRoles = $stmtRoles->fetchAll(PDO::FETCH_COLUMN);

    // SIMPAN SESI ASLI (ADMIN) jika belum tersimpan (agar tidak tertimpa jika impersonate berantai)
    if (!isset($_SESSION['impersonator_user_id'])) {
        $_SESSION['impersonator_user_id'] = $_SESSION['user_id'];
        $_SESSION['impersonator_username'] = $_SESSION['username'];
        $_SESSION['impersonator_roles'] = $_SESSION['roles'];
    }

    // SET SESI BARU (TARGET)
    $_SESSION['user_id'] = $targetUser['user_id'];
    $_SESSION['username'] = $targetUser['username'];
    $_SESSION['full_name'] = $targetUser['full_name'];
    $_SESSION['roles'] = $targetRoles;

    sendJSONResponse(['success' => true, 'message' => 'Berhasil login sebagai ' . $targetUser['username']]);

} catch (Exception $e) {
    sendJSONResponse(['success' => false, 'message' => 'Error: ' . $e->getMessage()], 500);
}
?>