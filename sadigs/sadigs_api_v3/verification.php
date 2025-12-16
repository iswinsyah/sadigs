<?php
// --- KODE DEBUG DARI HOSTINGER START (Wajib Ada di semua API) ---
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', $_SERVER['DOCUMENT_ROOT'] . '/php_error.log'); 
error_reporting(E_ALL);
// --- KODE DEBUG DARI HOSTINGER END ---

// =================================================================
// SADIGS 3.0: LOGIKA VERIFIKASI PEGAWAI - SELF-CONTAINED
// FIX KRITIS: Menggunakan u.user_id (kolom PK di users) dan bukan u.id.
// =================================================================

// Menggunakan satu sumber koneksi dan fungsi utility
require_once 'db_connect.php';

// =================================================================
// Fungsi Pembantu Cek Otorisasi Yayasan (Hanya Yayasan yang bisa memverifikasi)
// =================================================================
function checkYayasanRole() {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    if (!isset($_SESSION['roles'])) {
        return false;
    }
    
    $yayasan_roles = array('Ketua Yayasan', 'Sekretaris Yayasan', 'Bendahara Yayasan');
    
    foreach ($_SESSION['roles'] as $role) {
        if (in_array($role, $yayasan_roles)) {
            return true;
        }
    }
    return false;
}

// Memulai sesi PHP
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
date_default_timezone_set('Asia/Jakarta');

// Cek Otorisasi sebelum melanjutkan (Ini harus selalu diaktifkan untuk keamanan!)
if (!checkYayasanRole()) {
    sendJSONResponse(array('success' => false, 'message' => 'Akses ditolak. Hanya Pengurus Yayasan yang dapat melakukan verifikasi.'), 403);
}

try {
    $pdo = getDBConnection();
} catch (Exception $e) {
    exit; // Koneksi gagal ditangani di getDBConnection()
}

// Peran Pegawai yang Wajib diverifikasi saat pendaftaran
$employee_roles = array('Kepala Sekolah', 'Kepala Asrama', 'Sekretaris Sekolah', 'Bendahara Sekolah', 'Musyrif', 'Ustadz');
$employee_roles_placeholder = implode(',', array_fill(0, count($employee_roles), '?'));


// =================================================================
// LOGIKA POST (VERIFIKASI / AKTIVASI AKUN)
// =================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents("php://input"), true);
    $user_id = $data['user_id'] ?? null;

    if (empty($user_id)) {
        sendJSONResponse(array('success' => false, 'message' => 'ID pengguna wajib diisi.'), 400);
    }

    try {
        $pdo->beginTransaction();
        
        // 1. Aktivasi Akun
        // MENGGUNAKAN user_id (primary key di tabel users) untuk UPDATE
        $sql_activate = "UPDATE users SET is_active = 1 WHERE user_id = :user_id AND is_active = 0";
        $stmt_activate = $pdo->prepare($sql_activate);
        $stmt_activate->execute(['user_id' => $user_id]);

        if ($stmt_activate->rowCount() === 0) {
            $pdo->rollBack();
            sendJSONResponse(array('success' => false, 'message' => 'Akun tidak ditemukan atau sudah aktif.'), 404);
        }

        $pdo->commit();
        sendJSONResponse(array(
            'success' => true,
            'message' => 'Akun pegawai berhasil diaktifkan!'
        ));

    } catch (\PDOException $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log("Verification POST Error: " . $e->getMessage());
        sendJSONResponse(array('success' => false, 'message' => 'Kesalahan sistem saat aktivasi akun.'), 500);
    }
} 
// =================================================================
// LOGIKA GET (AMBIL DAFTAR PENDING)
// =================================================================
elseif ($_SERVER['REQUEST_METHOD'] === 'GET') {
    
    // Bergabung dengan user_roles untuk mengambil semua peran yang terdaftar dengan is_active = 0
    // MENGGUNAKAN u.user_id untuk SELECT, JOIN, dan GROUP BY (seperti yang dibutuhkan MySQL)
    $sql_get = "
        SELECT 
            u.user_id, 
            u.username, 
            u.email, 
            GROUP_CONCAT(ur.role_name SEPARATOR ', ') AS roles 
        FROM users u
        JOIN user_roles ur ON u.user_id = ur.user_id
        WHERE u.is_active = 0 
        GROUP BY u.user_id, u.username, u.email
        HAVING SUM(ur.role_name IN ({$employee_roles_placeholder})) > 0
        ORDER BY u.user_id ASC;
    ";
    
    try {
        $stmt_get = $pdo->prepare($sql_get);
        // Eksekusi dengan array $employee_roles
        $stmt_get->execute($employee_roles); 
        $pending_users = $stmt_get->fetchAll(PDO::FETCH_ASSOC);
        
        sendJSONResponse(array(
            'success' => true,
            'pending_users' => $pending_users
        ));
    } catch (\PDOException $e) {
        // Ini adalah error yang Anda dapatkan sebelumnya, yang seharusnya sudah teratasi dengan u.user_id
        error_log("Verification GET DB Error: " . $e->getMessage());
        sendJSONResponse(array('success' => false, 'message' => 'Gagal mengambil data dari database.'), 500);
    }
}
// =================================================================
// LOGIKA FALLBACK
// =================================================================
else {
    sendJSONResponse(array('success' => false, 'message' => 'Metode permintaan tidak didukung.'), 405);
}