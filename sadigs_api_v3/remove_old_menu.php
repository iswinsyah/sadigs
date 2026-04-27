<?php
require_once 'db_connect.php';

try {
    $pdo = getDBConnection();
    echo "<h1>Pembersihan Menu Lama</h1>";

    // Hapus menu 'navVerifikasi' dari tabel permissions dan menus
    $menu_id = 'navVerifikasi';

    $pdo->exec("DELETE FROM menu_permissions WHERE menu_id = '$menu_id'");
    echo "<p style='color:green'>✅ Izin akses untuk menu lama '$menu_id' berhasil dihapus.</p>";

    $pdo->exec("DELETE FROM menus WHERE menu_id = '$menu_id'");
    echo "<p style='color:green'>✅ Menu lama '$menu_id' berhasil dihapus dari daftar menu.</p>";

    echo "<h3>Selesai. Silakan refresh halaman Dashboard Anda.</h3>";

} catch (Exception $e) {
    echo "<h3 style='color:red'>Error: " . $e->getMessage() . "</h3>";
}
?>