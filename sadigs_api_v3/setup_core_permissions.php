<?php
require_once 'db_connect.php';

try {
    $pdo = getDBConnection();
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    echo "<h1>Setup Izin Akses Inti untuk Ketua Yayasan</h1>";

    // Menu inti yang harus bisa diakses oleh Ketua Yayasan
    $core_menus = [
        'navMenuManagement' => 'Manajemen Akses',
        'navVerifikasi' => 'Verifikasi Pengguna',
        'navQuota' => 'Atur Kuota',
        'navCalendarSettings' => 'Atur Kalender',
        'navGrafikIbadah' => 'Grafik Rekap Ibadah'
    ];

    $admin_role = 'Ketua Yayasan';

    foreach ($core_menus as $menu_id => $menu_name) {
        // 1. Pastikan menu ada di tabel 'menus'
        $stmt_menu = $pdo->prepare("SELECT COUNT(*) FROM menus WHERE menu_id = ?");
        $stmt_menu->execute([$menu_id]);
        if ($stmt_menu->fetchColumn() == 0) {
            $pdo->prepare("INSERT INTO menus (menu_id, menu_name) VALUES (?, ?)")->execute([$menu_id, $menu_name]);
            echo "<p style='color:blue;'>ℹ️ Menu '{$menu_name}' ditambahkan ke tabel master.</p>";
        }

        // 2. Berikan izin ke Ketua Yayasan (Gunakan ON DUPLICATE KEY UPDATE untuk memastikan izin selalu ada dan aktif)
        $stmt_perm = $pdo->prepare("INSERT INTO menu_permissions (menu_id, role_name, can_view) VALUES (?, ?, 1) ON DUPLICATE KEY UPDATE can_view = 1");
        $stmt_perm->execute([$menu_id, $admin_role]);
        echo "<p style='color:green;'>✅ Izin untuk menu '{$menu_name}' dipastikan aktif untuk '{$admin_role}'.</p>";
    }

    echo "<h3>Setup Selesai. Ketua Yayasan sekarang memiliki akses ke menu-menu inti.</h3>";

} catch(PDOException $e) {
    die("ERROR: " . $e->getMessage());
}
?>