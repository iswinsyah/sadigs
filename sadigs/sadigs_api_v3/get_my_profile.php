<?php
require_once 'db_connect.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id'])) {
    sendJSONResponse(['success' => false, 'message' => 'Sesi tidak valid.'], 401);
    exit;
}

try {
    $pdo = getDBConnection();
    $stmt = $pdo->prepare("SELECT username, email, gender, full_name, bio FROM users WHERE user_id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user) {
        sendJSONResponse(['success' => true, 'data' => $user]);
    } else {
        sendJSONResponse(['success' => false, 'message' => 'Pengguna tidak ditemukan.'], 404);
    }

} catch (Exception $e) {
    sendJSONResponse(['success' => false, 'message' => 'Terjadi kesalahan server: ' . $e->getMessage()], 500);
}
?>