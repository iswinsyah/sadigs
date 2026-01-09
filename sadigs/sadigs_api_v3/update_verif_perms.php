<?php
require_once 'db_connect.php';

try {
    $pdo = getDBConnection();
    echo "<h1>Update Izin Verifikasi</h1>";

    $menu_id = 'navVerifikasi';
    $new_roles = ['Kepala Sekolah', 'Sekretaris Sekolah', 'Bendahara Sekolah'];

    $stmt = $pdo->prepare("INSERT IGNORE INTO menu_permissions (role_name, menu_id, is_allowed) VALUES (?, ?, 1)");

    foreach ($new_roles as $role) {
        $stmt->execute([$role, $menu_id]);
        echo "<p>✅ Akses <strong>Verifikasi Pengguna</strong> diberikan ke: $role</p>";
    }

    echo "<h3>Selesai. Pihak sekolah sekarang bisa mengakses menu verifikasi.</h3>";

} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
?>