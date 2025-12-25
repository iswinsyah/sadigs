<?php
require_once 'db_connect.php';

try {
    $pdo = getDBConnection();
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Menu baru yang akan ditambahkan
    $new_menu_id = 'navIzinPegawai';
    $menu_name = 'Izin Pegawai';
    
    // Peran yang secara default bisa melihat menu ini
    $default_roles = ['Kepala Sekolah', 'Kepala Asrama', 'Sekretaris Sekolah', 'Bendahara Sekolah', 'Musyrif', 'Ustadz'];

    // Cek apakah menu sudah ada
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM menus WHERE menu_id = ?");
    $stmt->execute([$new_menu_id]);
    if ($stmt->fetchColumn() == 0) {
        // Jika belum ada, tambahkan ke tabel menus
        $stmt = $pdo->prepare("INSERT INTO menus (menu_id, menu_name) VALUES (?, ?)");
        $stmt->execute([$new_menu_id, $menu_name]);
        echo "Menu '$menu_name' berhasil ditambahkan.<br>";
    } else {
        echo "Menu '$menu_name' sudah ada.<br>";
    }

    // Tambahkan permission untuk peran default
    $stmt = $pdo->prepare("INSERT IGNORE INTO menu_permissions (menu_id, role_name, can_view) VALUES (?, ?, 1)");
    foreach ($default_roles as $role) {
        $stmt->execute([$new_menu_id, $role]);
    }
    echo "Hak akses default untuk menu '$menu_name' berhasil diatur untuk peran: " . implode(', ', $default_roles) . ".<br>";
    
    echo "Setup selesai.";

} catch(PDOException $e) {
    die("ERROR: " . $e->getMessage());
}
?>