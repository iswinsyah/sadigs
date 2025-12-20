<?php
// SADIGS 3.0: GET MY PROFILE (Session Based)
ob_start();
require_once 'db_connect.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id'])) {
    sendJSONResponse(['success' => false, 'message' => 'Belum login.'], 401);
}

try {
    $pdo = getDBConnection();
    $sql = "SELECT username, email, full_name, bio FROM users WHERE user_id = :user_id";
    $stmt = $pdo->prepare($sql);
    $stmt->execute(['user_id' => $_SESSION['user_id']]);
    $user = $stmt->fetch();

    if ($user) {
        sendJSONResponse(['success' => true, 'data' => $user]);
    } else {
        sendJSONResponse(['success' => false, 'message' => 'User tidak ditemukan.'], 404);
    }
} catch (Exception $e) {
    sendJSONResponse(['success' => false, 'message' => 'Error server.'], 500);
}
?>