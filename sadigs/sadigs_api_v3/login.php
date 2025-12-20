<?php
// =================================================================
// SADIGS 3.0: LOGIN (CLEAN VERSION)
// =================================================================

// Buffer output untuk mencegah error HTML
ob_start();

require_once 'db_connect.php';

// Mulai sesi
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Ambil data JSON
$data = json_decode(file_get_contents("php://input"), true);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendJSONResponse(array('success' => false, 'message' => 'Metode harus POST.'), 405);
}

$username = $data['username'] ?? '';
$password = $data['password'] ?? '';

if (empty($username) || empty($password)) {
    sendJSONResponse(array('success' => false, 'message' => 'Username dan kata sandi harus diisi.'), 400);
}

try {
    $pdo = getDBConnection();
    
    // 1. Cari User berdasarkan Username
    $sql_user = "SELECT user_id, username, password_hash, is_active FROM users WHERE username = :username";
    $stmt_user = $pdo->prepare($sql_user);
    $stmt_user->execute(['username' => $username]);
    $user = $stmt_user->fetch();

    // 2. Verifikasi User & Password
    if (!$user) {
        sendJSONResponse(array('success' => false, 'message' => 'Username atau kata sandi salah.'), 401);
    }
    
    if (!password_verify($password, $user['password_hash'])) {
        sendJSONResponse(array('success' => false, 'message' => 'Username atau kata sandi salah.'), 401);
    }

    // 3. Cek Status Aktif
    if ($user['is_active'] == 0) {
        sendJSONResponse(array('success' => false, 'message' => 'Akun belum diaktifkan oleh Admin.'), 403);
    }

    // 4. Ambil Roles
    $sql_roles = "SELECT role_name FROM user_roles WHERE user_id = :user_id";
    $stmt_roles = $pdo->prepare($sql_roles);
    $stmt_roles->execute(['user_id' => $user['user_id']]);
    $roles_db = $stmt_roles->fetchAll(PDO::FETCH_COLUMN, 0);

    // 5. Set Session
    session_regenerate_id(true);
    $_SESSION['user_id'] = $user['user_id'];
    $_SESSION['username'] = $user['username'];
    $_SESSION['roles'] = $roles_db; 
    
    sendJSONResponse(array(
        'success' => true, 
        'message' => 'Login berhasil!',
        'redirect_path' => '../dashboard.html'
    ));

} catch (\PDOException $e) {
    sendJSONResponse(array('success' => false, 'message' => 'Login Error: ' . $e->getMessage()), 500);
}