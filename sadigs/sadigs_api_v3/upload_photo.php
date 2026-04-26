<?php
header('Content-Type: application/json');
require_once 'db_connect.php';

if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$user_id = $_SESSION['user_id'];
$pdo = getDBConnection();

// 1. AUTO-HEALING: Tambah kolom profile_photo ke tabel users jika belum ada
try {
    $pdo->query("SELECT profile_photo FROM users LIMIT 1");
} catch (Exception $e) {
    $pdo->exec("ALTER TABLE users ADD COLUMN profile_photo VARCHAR(255) NULL DEFAULT NULL AFTER bio");
}

// 2. Siapkan Folder Upload di server
$target_dir = "../assets/uploads/profiles/";
if (!file_exists($target_dir)) {
    mkdir($target_dir, 0777, true);
}

// 3. Proses Penerimaan File
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['photo'])) {
    $file = $_FILES['photo'];
    
    // Validasi Error
    if ($file['error'] !== UPLOAD_ERR_OK) {
        echo json_encode(['success' => false, 'message' => 'Gagal mengunggah. Kode error: ' . $file['error']]);
        exit;
    }

    // Validasi Ukuran (Maksimal 2MB)
    if ($file['size'] > 2 * 1024 * 1024) {
        echo json_encode(['success' => false, 'message' => 'Ukuran foto terlalu besar. Maksimal 2MB.']);
        exit;
    }

    // Validasi Tipe (Hanya Gambar)
    $allowed_types = ['image/jpeg', 'image/png', 'image/webp'];
    $mime_type = mime_content_type($file['tmp_name']);
    if (!in_array($mime_type, $allowed_types)) {
        echo json_encode(['success' => false, 'message' => 'Format file tidak diizinkan. Hanya JPG, PNG, atau WEBP.']);
        exit;
    }

    // Buat Nama File Unik (Bebas Spasi)
    $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
    $new_filename = "user_" . $user_id . "_" . time() . "." . $extension;
    $target_file = $target_dir . $new_filename;

    // Pindahkan File ke Folder
    if (move_uploaded_file($file['tmp_name'], $target_file)) {
        $db_path = "assets/uploads/profiles/" . $new_filename;
        
        // Update Database
        $stmtUpdate = $pdo->prepare("UPDATE users SET profile_photo = ? WHERE user_id = ?");
        $stmtUpdate->execute([$db_path, $user_id]);

        echo json_encode(['success' => true, 'message' => 'Foto profil berhasil diperbarui!', 'photo_url' => $db_path]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Gagal menyimpan file ke server Hostinger.']);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Tidak ada file gambar yang terdeteksi.']);
}
?>