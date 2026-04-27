<?php
// =================================================================
// SADIGS 3.0: SIGNUP API
// =================================================================
header('Content-Type: application/json');
require_once 'db_connect.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Ambil data JSON dari fetch()
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);

    $username = trim($data['username'] ?? '');
    $email = trim($data['email'] ?? '');
    $gender = trim($data['gender'] ?? ''); // Tangkap data gender
    $password = $data['password'] ?? '';

    // Validasi Input
    if (empty($username) || empty($email) || empty($gender) || empty($password)) {
        sendJSONResponse(['success' => false, 'message' => 'Semua kolom (termasuk Jenis Kelamin) wajib diisi.'], 400);
        exit;
    }

    try {
        $pdo = getDBConnection();

        // 1. Cek apakah username/email sudah ada
        $stmt = $pdo->prepare("SELECT user_id FROM users WHERE username = ? OR email = ?");
        $stmt->execute([$username, $email]);
        if ($stmt->fetch()) {
            sendJSONResponse(['success' => false, 'message' => 'Username atau Email sudah terdaftar.'], 409);
            exit;
        }

        // 2. Hash Password & Simpan Data
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        
        // UPDATE: Set is_active = 1 agar user bisa login untuk memilih peran
        $sql = "INSERT INTO users (username, email, gender, password_hash, is_active, created_at) VALUES (?, ?, ?, ?, 1, NOW())";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$username, $email, $gender, $hashed_password]);

        sendJSONResponse(['success' => true, 'message' => 'Akun berhasil dibuat. Silakan login.']);

    } catch (Exception $e) {
        sendJSONResponse(['success' => false, 'message' => 'Terjadi kesalahan server: ' . $e->getMessage()], 500);
    }
}
?>