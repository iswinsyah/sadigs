<?php
// sadigs_api_v3/get_profile.php
// Endpoint untuk mengambil data profil pengguna berdasarkan user_id.

// Mengatur header agar respons selalu berupa JSON
header('Content-Type: application/json');

// Memuat file koneksi database terpusat (PDO)
require_once 'db_connect.php';

// Memulai sesi untuk verifikasi otorisasi
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Hanya mengizinkan metode POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendJSONResponse(['success' => false, 'message' => 'Metode tidak diizinkan. Gunakan POST.'], 405);
}

// Mendapatkan koneksi database
try {
    $pdo = getDBConnection();
} catch (\PDOException $e) {
    exit; // Error sudah ditangani di db_connect.php
}

// Mendapatkan data dari body request JSON
$data = json_decode(file_get_contents("php://input"), true);

// Validasi input
if (!isset($data['user_id']) || empty($data['user_id'])) {
    sendJSONResponse(['success' => false, 'message' => 'ID pengguna (user_id) diperlukan.'], 400);
}

$user_id = $data['user_id'];

// Verifikasi bahwa pengguna sudah login sebelum memberikan data
if (!isset($_SESSION['user_id'])) {
    sendJSONResponse(['success' => false, 'message' => 'Akses ditolak. Silakan login terlebih dahulu.'], 401);
}

try {
    // Query untuk mengambil detail profil pengguna
    // PERHATIAN: Ganti nama tabel dan kolom sesuai dengan skema database Anda.
    // Menggunakan user_id sebagai kolom kunci
    $sql = "SELECT user_id, username, email, full_name, bio, created_at FROM users WHERE user_id = :user_id";
    
    // Menggunakan prepared statement untuk mencegah SQL Injection
    $stmt = $pdo->prepare($sql);
    $stmt->execute(['user_id' => $user_id]);
    
    $profile = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($profile) {
        // Data pengguna ditemukan
        sendJSONResponse(['success' => true, 'message' => 'Data profil berhasil diambil.', 'data' => $profile]);
    } else {
        // Pengguna tidak ditemukan
        sendJSONResponse(['success' => false, 'message' => 'Pengguna dengan ID tersebut tidak ditemukan.'], 404);
    }

} catch (Exception $e) {
    // Tangani exception dan kirim respons error
    error_log("Error di get_profile.php: " . $e->getMessage());
    sendJSONResponse(['success' => false, 'message' => 'Terjadi kesalahan pada server.'], 500);
}
?>