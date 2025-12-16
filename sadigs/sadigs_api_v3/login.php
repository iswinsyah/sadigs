<?php
// Mulai output buffering untuk menangkap output yang tidak diinginkan (seperti warning).
// Ini harus menjadi baris PERTAMA di file.
ob_start();

// --- KODE DEBUG (Biarkan aktif selama pengembangan) ---
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', $_SERVER['DOCUMENT_ROOT'] . '/php_error.log'); 
error_reporting(E_ALL);
// --- KODE DEBUG DARI HOSTINGER END ---

// =================================================================
// SADIGS 3.0: LOGIKA UTAMA LOGIN
// FIX KRITIS: Menghapus require_once dan mengimplementasikan fungsi internal.
// FIX KRITIS: Mengganti semua referensi 'id' dengan 'user_id'.
// =================================================================

// Menggunakan satu sumber koneksi dan fungsi utility
require_once 'db_connect.php';


if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
date_default_timezone_set('Asia/Jakarta');

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || empty(file_get_contents("php://input"))) {
    sendJSONResponse(array('success' => false, 'message' => 'Permintaan tidak sah.'), 400);
}

$data = json_decode(file_get_contents("php://input"), true);

$username = $data['username'] ?? '';
$password = $data['password'] ?? '';

if (empty($username) || empty($password)) {
    sendJSONResponse(array('success' => false, 'message' => 'Username dan kata sandi harus diisi.'), 400);
}

try {
    $pdo = getDBConnection();
    
    // FIX KRITIS: Mengubah SELECT id menjadi SELECT user_id
    $sql_user = "SELECT user_id, username, password_hash, is_active FROM users WHERE username = :username";
    $stmt_user = $pdo->prepare($sql_user);
    $stmt_user->execute(['username' => $username]);
    $user = $stmt_user->fetch();

    if (!$user) {
        sendJSONResponse(array('success' => false, 'message' => 'Username atau kata sandi salah.'), 401);
    }
    
    if (!$user['is_active']) {
        sendJSONResponse(array('success' => false, 'message' => 'Akun Anda belum diaktifkan oleh Administrator.'), 403);
    }

    $password_match = password_verify($password, $user['password_hash']);

    if (!$password_match) {
        sendJSONResponse(array('success' => false, 'message' => 'Username atau kata sandi salah.'), 401);
    }

    $sql_roles = "SELECT role_name FROM user_roles WHERE user_id = :user_id";
    $stmt_roles = $pdo->prepare($sql_roles);
    // FIX KRITIS: Menggunakan $user['user_id']
    $stmt_roles->execute(['user_id' => $user['user_id']]);
    $roles_db = $stmt_roles->fetchAll(PDO::FETCH_COLUMN, 0);

    if (empty($roles_db)) {
         sendJSONResponse(array('success' => false, 'message' => 'Akun ini tidak memiliki peran yang terdaftar.'), 403);
    }
    
    // Regenerasi ID Sesi untuk keamanan (mencegah Session Fixation)
    session_regenerate_id(true);
    
    // FIX KRITIS: Menyimpan user_id ke sesi
    $_SESSION['user_id'] = $user['user_id'];
    $_SESSION['username'] = $user['username'];
    $_SESSION['roles'] = $roles_db; 
    
    sendJSONResponse(array(
        'success' => true, 
        'message' => 'Login berhasil!',
        'username' => $user['username'],
        'roles' => $roles_db,
        'redirect_path' => '../dashboard.html'
    ));

} catch (\PDOException $e) {
    error_log("Login DB Error: " . $e->getMessage());
    sendJSONResponse(array('success' => false, 'message' => 'Kesalahan database saat login.'), 500);
}