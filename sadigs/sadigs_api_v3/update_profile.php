<?php
// =================================================================
// SADIGS 3.0: UPDATE PROFILE
// =================================================================
ob_start();
require_once 'db_connect.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Pastikan user login
if (!isset($_SESSION['user_id'])) {
    sendJSONResponse(['success' => false, 'message' => 'Akses ditolak.'], 401);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendJSONResponse(['success' => false, 'message' => 'Metode harus POST.'], 405);
}

$data = json_decode(file_get_contents("php://input"), true);
$user_id = $_SESSION['user_id']; // Ambil ID dari sesi demi keamanan

$full_name = $data['full_name'] ?? '';
$bio = $data['bio'] ?? '';
$password = $data['password'] ?? '';

try {
    $pdo = getDBConnection();
    
    // Cek apakah user ingin ganti password
    if (!empty($password)) {
        if (strlen($password) < 8) {
            sendJSONResponse(['success' => false, 'message' => 'Password baru minimal 8 karakter.'], 400);
        }
        $password_hash = password_hash($password, PASSWORD_BCRYPT);
        
        // Update Data + Password
        $sql = "UPDATE users SET full_name = :full_name, bio = :bio, password_hash = :password_hash WHERE user_id = :user_id";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            'full_name' => $full_name,
            'bio' => $bio,
            'password_hash' => $password_hash,
            'user_id' => $user_id
        ]);
    } else {
        // Update Data Saja (Tanpa Password)
        $sql = "UPDATE users SET full_name = :full_name, bio = :bio WHERE user_id = :user_id";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            'full_name' => $full_name,
            'bio' => $bio,
            'user_id' => $user_id
        ]);
    }

    sendJSONResponse([
        'success' => true, 
        'message' => 'Profil berhasil diperbarui.'
    ]);

} catch (\PDOException $e) {
    error_log("Update Profile Error: " . $e->getMessage());
    sendJSONResponse(['success' => false, 'message' => 'Gagal memperbarui profil.'], 500);
}
?>