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
if (isset($_SESSION['user_id'], $_SESSION['username'])) {
    
    // Ambil data peran mentah (termasuk status) untuk halaman profil
    $pdo = getDBConnection();
    $stmt = $pdo->prepare("SELECT role_name, status FROM user_roles WHERE user_id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $raw_roles = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Ambil peran yang sudah disetujui untuk otorisasi menu
    $approved_roles = [];
    foreach ($raw_roles as $role) {
        if ($role['status'] === 'approved') {
            $approved_roles[] = $role['role_name'];
        }
    }

    // Sesi valid, kirimkan data ke dashboard.html
    sendJSONResponse(array(
        'success' => true,
        'username' => $_SESSION['username'],
        'roles' => $approved_roles, // Hanya kirim peran yang sudah disetujui
        'raw_roles' => $raw_roles // Kirim data mentah untuk UI profil
    ));
    
} else {
    // Sesi tidak valid atau telah berakhir
    sendJSONResponse(array('success' => false, 'message' => 'Sesi berakhir atau tidak valid.'), 401);
}