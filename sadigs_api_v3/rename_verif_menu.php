<?php
require_once 'db_connect.php';

try {
    $pdo = getDBConnection();
    // Ubah nama menu agar lebih umum
    $pdo->exec("UPDATE menus SET menu_name = 'Verifikasi Pengguna' WHERE menu_id = 'navVerifikasi'");
    echo "<h1>Berhasil!</h1>";
    echo "<p>Nama menu 'navVerifikasi' telah diubah menjadi <strong>Verifikasi Pengguna</strong>.</p>";
    echo "<p>Silakan refresh halaman Dashboard/Manajemen Akses.</p>";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
?>