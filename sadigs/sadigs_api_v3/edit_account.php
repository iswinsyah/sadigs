<?php
// sadigs_api_v3/edit_account.php
// Endpoint untuk memperbarui data profil pengguna.
// Menggunakan koneksi terpusat dan fungsi utility
require_once 'db_connect.php';

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

// --- Validasi Input Wajib ---
// user_id diperlukan untuk mengidentifikasi akun yang akan diubah
if (!isset($_POST['user_id']) || empty($_POST['user_id'])) {
    sendJSONResponse(['success' => false, 'message' => 'ID pengguna (user_id) wajib disertakan.'], 400);
}

$user_id = $_POST['user_id'];

// Data yang dapat diubah (ambil dari body POST)
$full_name = $_POST['full_name'] ?? null;
$bio = $_POST['bio'] ?? null;
$email = $_POST['email'] ?? null; // Hati-hati mengubah email, mungkin perlu verifikasi ulang

// Pastikan setidaknya satu field yang valid untuk diubah telah dikirim
if (is_null($full_name) && is_null($bio) && is_null($email)) {
    sendJSONResponse(['success' => false, 'message' => 'Tidak ada data yang valid untuk diperbarui. Sertakan full_name, bio, atau email.'], 400);
}

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
    if (!is_null($email)) {
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            sendJSONResponse(['success' => false, 'message' => 'Format email tidak valid.'], 400);
        }
        // Cek apakah email sudah digunakan oleh pengguna lain (kecuali diri sendiri)
        $check_sql = "SELECT COUNT(*) FROM users WHERE email = :email AND user_id != :user_id";
        $check_stmt = $pdo->prepare($check_sql);
        $check_stmt->execute(['email' => $email, 'user_id' => $user_id]);
        
        if ($check_stmt->fetchColumn() > 0) {
            sendJSONResponse(['success' => false, 'message' => 'Email sudah digunakan oleh akun lain.'], 409);
        }

        $updates[] = "email = :email";
        $params['email'] = $email;
    }

    // --- Eksekusi Query Update ---
    if (count($updates) > 0) {
        $set_clause = implode(', ', $updates);
        $sql = "UPDATE users SET $set_clause, updated_at = NOW() WHERE user_id = :user_id";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);

        if ($stmt->rowCount() > 0) {
            sendJSONResponse(['success' => true, 'message' => 'Akun berhasil diperbarui.']);
        } else {
            // Ini bisa berarti data yang dikirim sama dengan data yang sudah ada, atau user_id tidak ditemukan
            sendJSONResponse(['success' => true, 'message' => 'Tidak ada perubahan yang dilakukan (data mungkin sama).'], 200);
        }
    }

} catch (\PDOException $e) {
    error_log("Error di edit_account.php: " . $e->getMessage());
    sendJSONResponse(['success' => false, 'message' => 'Terjadi kesalahan pada server saat memperbarui data.'], 500);
}
?>