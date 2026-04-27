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

// --- CEK OTORITAS (Admin/Yayasan boleh edit siapa saja) ---
$stmtRoles = $pdo->prepare("SELECT role_name FROM user_roles WHERE user_id = ? AND status = 'approved'");
$stmtRoles->execute([$_SESSION['user_id']]);
$my_roles = $stmtRoles->fetchAll(PDO::FETCH_COLUMN);

$is_admin = !empty(array_intersect(['Ketua Yayasan', 'Sekretaris Yayasan', 'Admin Sekolah', 'Kepala Sekolah'], $my_roles));

// Jika bukan admin, hanya boleh edit diri sendiri
if ((!isset($_SESSION['user_id']) || $_SESSION['user_id'] != $user_id) && !$is_admin) {
    sendJSONResponse(['success' => false, 'message' => 'Akses ditolak. Anda hanya dapat mengubah profil Anda sendiri.'], 403);
}

// Data yang dapat diubah (ambil dari body JSON)
$full_name = $data['full_name'] ?? null;
$bio = $data['bio'] ?? null;
$email = $data['email'] ?? null; // Hati-hati mengubah email, mungkin perlu verifikasi ulang
$username = $data['username'] ?? null; // Admin only
$password = $data['password'] ?? null; // Admin only

// Ambil data saat ini untuk perbandingan (agar tidak error duplicate jika nilai sama)
$stmtCurr = $pdo->prepare("SELECT email, username FROM users WHERE user_id = ?");
$stmtCurr->execute([$user_id]);
$currUser = $stmtCurr->fetch(PDO::FETCH_ASSOC);

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
    if (!is_null($email) && $email !== ($currUser['email'] ?? '')) {
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
        if ($_SESSION['user_id'] == $user_id) {
            $_SESSION['email'] = $email;
        }
    }

    // 3b. Username (Khusus Admin)
    if ($is_admin && !is_null($username) && $username !== ($currUser['username'] ?? '')) {
        // Cek duplikat
        $chk = $pdo->prepare("SELECT COUNT(*) FROM users WHERE username = ? AND user_id != ?");
        $chk->execute([$username, $user_id]);
        if ($chk->fetchColumn() > 0) {
            sendJSONResponse(['success' => false, 'message' => 'Username sudah digunakan user lain.'], 409);
        }
        $updates[] = "username = :username";
        $params['username'] = $username;
    }

    // 3c. Password (Khusus Admin)
    if ($is_admin && !empty($password)) {
        $updates[] = "password_hash = :password";
        $params['password'] = password_hash($password, PASSWORD_DEFAULT);
    }

    // 4. Child Names (Logika Penghubungan Walisantri)
    // Fitur ini mengecek nama anak yang dikirim dan menghubungkannya ke akun ini
    $child_names = $data['child_names'] ?? null;
    $linking_messages = [];
    
    if (!is_null($child_names) && is_array($child_names)) {
        // Ambil username walisantri saat ini
        $stmtUser = $pdo->prepare("SELECT username FROM users WHERE user_id = ?");
        $stmtUser->execute([$user_id]);
        $parent_username = $stmtUser->fetchColumn();

        if ($parent_username) {
            foreach ($child_names as $childName) {
                $childName = trim($childName);
                if (empty($childName)) continue;

                // Cari santri berdasarkan nama lengkap
                $stmtFind = $pdo->prepare("SELECT user_id FROM users WHERE full_name = ?");
                $stmtFind->execute([$childName]);
                $student = $stmtFind->fetch(PDO::FETCH_ASSOC);

                if ($student) {
                    // REVISI: Gunakan tabel student_guardians untuk mendukung Multi-Parent (Ayah & Ibu)
                    // Jangan menimpa parent_username di student_details agar data lama tidak hilang
                    $stmtLink = $pdo->prepare("INSERT IGNORE INTO student_guardians (student_id, walisantri_id) VALUES (?, ?)");
                    $stmtLink->execute([$student['user_id'], $user_id]);
                    $linking_messages[] = "Berhasil menghubungkan santri: $childName";
                } else {
                    $linking_messages[] = "PERINGATAN: Santri '$childName' tidak ditemukan (Cek ejaan)";
                }
            }
        }
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

    $finalMsg = 'Akun berhasil diperbarui.';
    if (!empty($linking_messages)) {
        $finalMsg .= ' ' . implode('. ', $linking_messages);
    }

    sendJSONResponse([
        'success' => true, 
        'message' => $finalMsg,
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