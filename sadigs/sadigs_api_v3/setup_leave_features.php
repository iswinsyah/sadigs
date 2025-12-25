<?php
require_once 'db_connect.php';

try {
    $pdo = getDBConnection();
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    echo "<h1>Setup Menu Validasi & Daftar Izin</h1>";

    $menus_to_add = [
        'navValidasiIzin' => 'Validasi Izin',
        'navDaftarIzin' => 'Daftar Izin'
    ];

    $default_roles = ['Ketua Yayasan', 'Kepala Sekolah', 'Kepala Asrama Putra', 'Kepala Asrama Putri'];

    foreach ($menus_to_add as $menu_id => $menu_name) {
        // Cek apakah menu sudah ada di tabel 'menus'
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM menus WHERE menu_id = ?");
        $stmt->execute([$menu_id]);
        if ($stmt->fetchColumn() == 0) {
            $stmt_insert = $pdo->prepare("INSERT INTO menus (menu_id, menu_name) VALUES (?, ?)");
            $stmt_insert->execute([$menu_id, $menu_name]);
            echo "<p style='color:green;'>✅ Menu '$menu_name' berhasil ditambahkan ke tabel master.</p>";
        } else {
            echo "<p style='color:blue;'>ℹ️ Menu '$menu_name' sudah ada di tabel master.</p>";
        }

        // Tambahkan permission untuk peran default
        $stmt_perm = $pdo->prepare("INSERT IGNORE INTO menu_permissions (menu_id, role_name, can_view) VALUES (?, ?, 1)");
        foreach ($default_roles as $role) {
            $stmt_perm->execute([$menu_id, $role]);
        }
    }
    
    echo "<p>Hak akses default untuk menu-menu di atas berhasil diatur untuk peran: " . implode(', ', $default_roles) . ".</p>";
    echo "<h3>Setup selesai.</h3>";

} catch(PDOException $e) {
    die("ERROR: " . $e->getMessage());
}
?>