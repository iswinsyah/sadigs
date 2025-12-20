<?php
// =================================================================
// SADIGS 3.0: SIGNUP (CLEAN VERSION)
// =================================================================

// Buffer output untuk mencegah error HTML merusak JSON
ob_start();

// Pastikan db_connect dimuat
require_once 'db_connect.php';

// Pastikan metode request adalah POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendJSONResponse(array('success' => false, 'message' => 'Permintaan tidak sah.'), 400);
}

// Ambil data JSON
$data = json_decode(file_get_contents("php://input"), true);

// Validasi Input Dasar
$username = $data['username'] ?? '';
$email = $data['email'] ?? '';
$password = $data['password'] ?? '';
$roles = $data['roles'] ?? [];

// Pastikan selalu ada role default (Santri) jika kosong
if (empty($roles)) {
    $roles = ['Santri'];
}

if (empty($username) || empty($email) || empty($password)) {
    sendJSONResponse(array('success' => false, 'message' => 'Semua bidang wajib diisi.'), 400);
}

if (strlen($password) < 8) {
    sendJSONResponse(array('success' => false, 'message' => 'Kata sandi minimal 8 karakter.'), 400);
}

try {
    $pdo = getDBConnection();
    
    // 1. Cek Duplikasi Username/Email
    $sql_check = "SELECT COUNT(*) FROM users WHERE username = :username OR email = :email";
    $stmt_check = $pdo->prepare($sql_check);
    $stmt_check->execute(['username' => $username, 'email' => $email]);
    
    if ($stmt_check->fetchColumn() > 0) {
        sendJSONResponse(array('success' => false, 'message' => 'Username atau Email sudah terdaftar.'), 409);
    }
    
    // 2. Mulai Transaksi
    $pdo->beginTransaction();
    
    // Hash Password
    $password_hash = password_hash($password, PASSWORD_BCRYPT);
    
    // 3. Insert User (Default is_active = 1 sementara)
    $sql_insert_user = "INSERT INTO users (username, email, password_hash, is_active) VALUES (:username, :email, :password_hash, 1)";
    $stmt_user = $pdo->prepare($sql_insert_user);
    $stmt_user->execute(['username' => $username, 'email' => $email, 'password_hash' => $password_hash]);
    $user_id = $pdo->lastInsertId();

    // 4. Insert Roles
    $sql_insert_role = "INSERT INTO user_roles (user_id, role_name) VALUES (:user_id, :role_name)";
    $stmt_role = $pdo->prepare($sql_insert_role);

    foreach ($roles as $role) {
        $stmt_role->execute(['user_id' => $user_id, 'role_name' => $role]);
    }
    
    // Commit
    $pdo->commit();

    sendJSONResponse(array(
        'success' => true,
        'message' => 'Pendaftaran berhasil! Akun Anda langsung aktif.'
    ));

} catch (\PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log("Signup DB Error: " . $e->getMessage());
    sendJSONResponse(array('success' => false, 'message' => 'Terjadi kesalahan sistem saat pendaftaran.'), 500);
}