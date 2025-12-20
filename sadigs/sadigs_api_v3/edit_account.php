<?php
// sadigs_api_v3/edit_account.php
// Endpoint untuk memperbarui data profil pengguna.
// Menggunakan koneksi terpusat dan fungsi utility
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

// Validasi body request: cek apakah JSON valid dan tidak kosong
// json_last_error() !== JSON_ERROR_NONE menangkap JSON yang cacat.
// is_null($data) menangkap body yang kosong atau berisi "null".
if (json_last_error() !== JSON_ERROR_NONE || is_null($data)) {
    sendJSONResponse(['success' => false, 'message' => 'Request body JSON tidak valid atau kosong.'], 400);
}

// user_id diperlukan untuk mengidentifikasi akun yang akan diubah
if (!isset($data['user_id']) || empty($data['user_id'])) {
    sendJSONResponse(['success' => false, 'message' => 'ID pengguna (user_id) wajib disertakan.'], 400);
}

$user_id = $data['user_id'];

// --- PENINGKATAN KEAMANAN KRITIS ---
// Verifikasi bahwa user_id yang akan diubah sama dengan user_id yang ada di sesi.
if (!isset($_SESSION['user_id']) || $_SESSION['user_id'] != $user_id) {
    sendJSONResponse(['success' => false, 'message' => 'Akses ditolak. Anda hanya dapat mengubah profil Anda sendiri.'], 403);
}

// Data yang dapat diubah (ambil dari body JSON)
$full_name = $data['full_name'] ?? null;
$bio = $data['bio'] ?? null;
$email = $data['email'] ?? null; // Hati-hati mengubah email, mungkin perlu verifikasi ulang

try {
    // Persiapan untuk membangun query UPDATE secara dinamis
    $updates = [];
    $params = ['user_id' => $user_id];

    // 1. Full Name
    if (!is_null($full_name)) {
        $updates[] = "full_name = :full_name";
        $params['full_name'] = $full_name;
    }

    // 2. Bio
    if (!is_null($bio)) {
        $updates[] = "bio = :bio";
        $params['bio'] = $bio;
    }

    // 3. Email (Jika email disediakan, lakukan validasi dasar)
    // Optimasi: Hanya proses jika email yang dikirim berbeda dari yang ada di sesi
    if (!is_null($email) && $email !== ($_SESSION['email'] ?? '')) {
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            sendJSONResponse(['success' => false, 'message' => 'Format email tidak valid.'], 400);
        }
        
        // Cek apakah email baru sudah digunakan oleh pengguna lain.
        // Tidak perlu klausa `AND user_id != :user_id` karena kita sudah memastikan emailnya baru.
        $check_sql = "SELECT COUNT(*) FROM users WHERE email = :email";
        $check_stmt = $pdo->prepare($check_sql);
        $check_stmt->execute(['email' => $email]);
        
        if ($check_stmt->fetchColumn() > 0) {
            sendJSONResponse(['success' => false, 'message' => 'Email sudah digunakan oleh akun lain.'], 409);
        }

        $updates[] = "email = :email";
        $params['email'] = $email;

        // Simpan juga email baru ke sesi agar pengecekan berikutnya konsisten
        $_SESSION['email'] = $email;
    }

    // --- Eksekusi Query Update ---
    // Pastikan setidaknya satu field yang valid untuk diubah telah dikirim
    if (empty($updates)) {
        // Jika tidak ada field yang diubah (karena data yang dikirim sama atau kosong), kirim pesan yang informatif.
        // Kode status 200 OK karena tidak ada error, hanya tidak ada perubahan.
        sendJSONResponse(['success' => true, 'message' => 'Tidak ada perubahan yang dilakukan (data yang dikirim sama dengan data saat ini).'], 200);
    }

    // Karena sudah ada pengecekan `empty($updates)` di atas, blok `if (count($updates) > 0)` tidak lagi diperlukan.
    $set_clause = implode(', ', $updates);
    $sql = "UPDATE users SET $set_clause, updated_at = NOW() WHERE user_id = :user_id";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    // --- PENINGKATAN: Kirim kembali data profil yang diperbarui ---
    // Setelah update, ambil data terbaru dari database untuk dikirim kembali ke frontend.
    // Ini memungkinkan UI untuk refresh tanpa perlu request tambahan.
    $sql_fetch = "SELECT user_id, username, email, full_name, bio, updated_at FROM users WHERE user_id = :user_id";
    $stmt_fetch = $pdo->prepare($sql_fetch);
    $stmt_fetch->execute(['user_id' => $user_id]);
    $updated_user = $stmt_fetch->fetch();

    sendJSONResponse([
        'success' => true, 
        'message' => 'Akun berhasil diperbarui.',
        'user' => $updated_user
    ]);

} catch (\PDOException $e) {
    // Menangkap semua jenis kesalahan database yang mungkin terjadi selama proses:
    // 1. Pengecekan duplikasi email.
    // 2. Eksekusi query UPDATE utama.
    // 3. Pengambilan data profil yang telah diperbarui.
    error_log("Error di edit_account.php: " . $e->getMessage());
    sendJSONResponse(['success' => false, 'message' => 'Terjadi kesalahan pada server saat memperbarui data.'], 500);
}
?>