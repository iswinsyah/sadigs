<?php
require_once 'db_connect.php';

try {
    $pdo = getDBConnection();
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    echo "<h1>Setup Menu Keuangan</h1>";

    $menus_to_add = [
        'navFormulirPembayaran' => 'Formulir Pembayaran',
        'navValidasiPembayaran' => 'Validasi Pembayaran',
        'navTabelPembayaran' => 'Data Pembayaran',
        'navRekapPembayaran' => 'Rekap Keuangan'
    ];

    $permissions = [
        'navFormulirPembayaran' => ['Walisantri'],
        'navValidasiPembayaran' => ['Bendahara Yayasan'],
        'navTabelPembayaran' => ['Bendahara Yayasan', 'Ketua Yayasan'],
        'navRekapPembayaran' => ['Bendahara Yayasan', 'Ketua Yayasan']
    ];

    foreach ($menus_to_add as $menu_id => $menu_name) {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM menus WHERE menu_id = ?");
        $stmt->execute([$menu_id]);
        if ($stmt->fetchColumn() == 0) {
            $stmt_insert = $pdo->prepare("INSERT INTO menus (menu_id, menu_name) VALUES (?, ?)");
            $stmt_insert->execute([$menu_id, $menu_name]);
            echo "<p style='color:green;'>✅ Menu '$menu_name' berhasil ditambahkan.</p>";
        }

        if (isset($permissions[$menu_id])) {
            $stmt_perm = $pdo->prepare("INSERT IGNORE INTO menu_permissions (menu_id, role_name, can_view) VALUES (?, ?, 1)");
            foreach ($permissions[$menu_id] as $role) {
                $stmt_perm->execute([$menu_id, $role]);
            }
            echo "<p>Hak akses default untuk '$menu_name' diatur untuk: " . implode(', ', $permissions[$menu_id]) . ".</p>";
        }
    }
    
    echo "<h3>Setup selesai.</h3>";

} catch(PDOException $e) {
    die("ERROR: " . $e->getMessage());
}
?>