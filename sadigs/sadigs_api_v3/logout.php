<?php
// =================================================================
// SADIGS 3.0: LOGIKA LOGOUT
// Menghapus semua data sesi dan mengarahkan ke halaman login.
// Path redirect: "../index.html" mengarah dari /sadigs_api_v3/ ke /sadigs/index.html
// =================================================================

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Hapus semua variabel sesi
$_SESSION = array();

// Hapus sesi cookie jika ada
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// Hancurkan sesi
session_destroy();

// Redirect ke halaman login (index.html)
header("Location: ../index.html"); 
exit;