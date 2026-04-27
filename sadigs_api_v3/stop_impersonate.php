<?php
header('Content-Type: application/json');
require_once 'db_connect.php';

if (session_status() === PHP_SESSION_NONE) session_start();

if (isset($_SESSION['impersonator_user_id'])) {
    // Kembalikan Sesi Asli
    $_SESSION['user_id'] = $_SESSION['impersonator_user_id'];
    $_SESSION['username'] = $_SESSION['impersonator_username'];
    $_SESSION['roles'] = $_SESSION['impersonator_roles'];

    // Hapus Jejak Impersonate
    unset($_SESSION['impersonator_user_id']);
    unset($_SESSION['impersonator_username']);
    unset($_SESSION['impersonator_roles']);

    sendJSONResponse(['success' => true, 'message' => 'Kembali ke akun Admin.']);
} else {
    sendJSONResponse(['success' => false, 'message' => 'Tidak sedang dalam mode penyamaran.']);
}
?>