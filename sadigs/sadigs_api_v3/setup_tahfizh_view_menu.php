<?php
require_once 'db_connect.php';

try {
    $pdo = getDBConnection();
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    echo "<h1>Setup Menu Laporan Tahfizh Santri</h1>";

    $menu_id = 'navViewTahfizh';
    $menu_name = 'Laporan Tahfizh';
    $roles_with_access = ['Santri Rijal', 'Santri Nisa\''];

    // 1. Pastikan menu ada di tabel 'menus'
    $stmt_menu_check = $pdo->prepare("SELECT COUNT(*) FROM menus WHERE menu_id = ?");
    $stmt_menu_check->execute([$menu_id]);
    if ($stmt_menu_check->fetchColumn() == 0) {
        $pdo->prepare("INSERT INTO menus (menu_id, menu_name) VALUES (?, ?)")->execute([$menu_id, $menu_name]);
        echo "<p style='color:green;'>✅ Menu '{$menu_name}' ditambahkan ke tabel master.</p>";
    } else {
        echo "<p style='color:blue;'>ℹ️ Menu '{$menu_name}' sudah ada.</p>";
    }

    // 2. Berikan izin ke peran yang ditentukan
    $stmt_perm = $pdo->prepare("INSERT IGNORE INTO menu_permissions (menu_id, role_name, can_view) VALUES (?, ?, 1)");
    foreach ($roles_with_access as $role) {
        if ($stmt_perm->execute([$menu_id, $role]) && $stmt_perm->rowCount() > 0) {
            echo "<p style='color:green;'>✅ Izin untuk menu '{$menu_name}' diberikan kepada '{$role}'.</p>";
        }
    }

    echo "<h3>Setup Selesai.</h3>";

} catch(PDOException $e) {
    die("ERROR: " . $e->getMessage());
}
?>