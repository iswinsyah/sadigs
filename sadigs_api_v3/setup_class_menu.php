<?php
require_once 'db_connect.php';

try {
    $pdo = getDBConnection();
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    echo "<h1>Setup Menu Manajemen Kelas</h1>";

    $menu_id = 'navManajemenKelas';
    $menu_name = 'Manajemen Kelas';
    $roles = ['Kepala Sekolah', 'Sekretaris Sekolah', 'Ketua Yayasan'];

    // 1. Tambah ke tabel menus
    $stmt = $pdo->prepare("INSERT INTO menus (menu_id, menu_name, category_id, icon, link) VALUES (?, ?, 'ManajemenSekolah', 'users', 'class_management.html') ON DUPLICATE KEY UPDATE menu_name=VALUES(menu_name), link=VALUES(link)");
    $stmt->execute([$menu_id, $menu_name]);
    echo "<p>✅ Menu '$menu_name' berhasil ditambahkan.</p>";

    // 2. Beri izin akses
    $stmtPerm = $pdo->prepare("INSERT IGNORE INTO menu_permissions (role_name, menu_id, is_allowed) VALUES (?, ?, 1)");
    foreach ($roles as $role) {
        $stmtPerm->execute([$role, $menu_id]);
        echo "<p>✅ Akses diberikan ke: $role</p>";
    }

} catch(PDOException $e) {
    die("ERROR: " . $e->getMessage());
}
?>