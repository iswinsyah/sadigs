<?php
// =================================================================
// SADIGS 3.0: SESSION CHECK (CLEAN VERSION)
// =================================================================
ob_start();

// Menggunakan satu sumber koneksi dan fungsi utility
require_once 'db_connect.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Logika di bawah ini tidak memerlukan koneksi DB, hanya cek sesi yang sudah dibuat saat login
// Namun, dengan require_once 'db_connect.php', kita sudah mendapatkan fungsi sendJSONResponse()
// dan siap jika di masa depan perlu validasi ke database.
if (isset($_SESSION['user_id'], $_SESSION['username'], $_SESSION['roles']) && !empty($_SESSION['roles'])) {
    
    // Sesi valid, kirimkan data ke dashboard.html
    sendJSONResponse(array(
        'success' => true,
        'username' => $_SESSION['username'],
        'roles' => $_SESSION['roles'] 
    ));
    
} else {
    // Sesi tidak valid atau telah berakhir
    sendJSONResponse(array('success' => false, 'message' => 'Sesi berakhir atau tidak valid.'), 401);
}