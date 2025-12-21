<?php
require_once 'db_connect.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id'])) {
    sendJSONResponse(['success' => false, 'message' => 'Sesi tidak valid.'], 401);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);

    $full_name = $data['full_name'] ?? null;
    $gender = $data['gender'] ?? null;
    $bio = $data['bio'] ?? null;
    $password = $data['password'] ?? null;

    if (empty($gender) || !in_array($gender, ['Laki-laki', 'Perempuan'])) {
        sendJSONResponse(['success' => false, 'message' => 'Jenis kelamin tidak valid.'], 400);
        exit;
    }

    try {
        $pdo = getDBConnection();
        
        $sql = "UPDATE users SET full_name = ?, gender = ?, bio = ? WHERE user_id = ?";
        $params = [$full_name, $gender, $bio, $_SESSION['user_id']];

        if (!empty($password)) {
            if (strlen($password) < 8) {
                sendJSONResponse(['success' => false, 'message' => 'Password baru harus minimal 8 karakter.'], 400);
                exit;
            }
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $sql = "UPDATE users SET full_name = ?, gender = ?, bio = ?, password_hash = ? WHERE user_id = ?";
            $params = [$full_name, $gender, $bio, $hashed_password, $_SESSION['user_id']];
        }
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);

        sendJSONResponse(['success' => true, 'message' => 'Profil berhasil diperbarui.']);

    } catch (Exception $e) {
        sendJSONResponse(['success' => false, 'message' => 'Terjadi kesalahan server: ' . $e->getMessage()], 500);
    }
}
?>