<?php
// =================================================================
// SADIGS 3.0: SELECT ROLE (AFTER LOGIN)
// =================================================================
ob_start();
require_once 'db_connect.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id'])) {
    sendJSONResponse(['success' => false, 'message' => 'Akses ditolak.'], 401);
}

$data = json_decode(file_get_contents("php://input"), true);
$roles = $data['roles'] ?? [];

if (empty($roles) || !is_array($roles)) {
    sendJSONResponse(['success' => false, 'message' => 'Setidaknya satu peran harus dipilih.'], 400);
}

try {
    $pdo = getDBConnection();
    
    // 1. Cek Kuota (Looping untuk setiap peran)
    // Ambil semua setting kuota dulu untuk efisiensi
    $stmt_settings = $pdo->query("SELECT role_name, max_limit FROM quota_settings");
    $quota_settings = $stmt_settings->fetchAll(PDO::FETCH_KEY_PAIR);

    foreach ($roles as $role) {
        if (isset($quota_settings[$role]) && $quota_settings[$role] > 0) {
            $max_limit = $quota_settings[$role];
            
            $sql_count = "SELECT COUNT(*) FROM user_roles ur JOIN users u ON ur.user_id = u.user_id WHERE ur.role_name = :role AND u.is_active = 1";
            $stmt_count = $pdo->prepare($sql_count);
            $stmt_count->execute(['role' => $role]);
            $current_count = $stmt_count->fetchColumn();

            if ($current_count >= $max_limit) {
                sendJSONResponse(['success' => false, 'message' => "Kuota untuk posisi '$role' sudah penuh."], 400);
            }
        }
    }

    // 2. Mulai Transaksi
    $pdo->beginTransaction();

    // Insert Roles (Looping)
    $sql_insert_role = "INSERT INTO user_roles (user_id, role_name) VALUES (:user_id, :role_name)";
    $stmt_role = $pdo->prepare($sql_insert_role);

    foreach ($roles as $role) {
        $stmt_role->execute(['user_id' => $_SESSION['user_id'], 'role_name' => $role]);
    }

    // Update User jadi Non-Aktif (Menunggu Validasi)
    $sql_update_user = "UPDATE users SET is_active = 0 WHERE user_id = :user_id";
    $stmt_update = $pdo->prepare($sql_update_user);
    $stmt_update->execute(['user_id' => $_SESSION['user_id']]);

    $pdo->commit();

    // Hapus sesi (Logout paksa)
    session_destroy();

    sendJSONResponse([
        'success' => true, 
        'message' => 'Peran berhasil dipilih. Akun Anda sekarang menunggu validasi Yayasan.'
    ]);

} catch (\PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log("Select Role Error: " . $e->getMessage());
    sendJSONResponse(['success' => false, 'message' => 'Terjadi kesalahan sistem.'], 500);
}
?>