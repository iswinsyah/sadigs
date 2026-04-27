<?php
require_once 'db_connect.php';

try {
    $pdo = getDBConnection();
    echo "<h1>Fix Missing Menus</h1>";

    // 1. Paksa Masukkan Menu Manajemen Kelas
    $sql = "INSERT INTO menus (menu_id, menu_name, category_id, icon, link) 
            VALUES ('navManajemenKelas', 'Manajemen Kelas', 'ManajemenSekolah', 'users', 'class_management.html') 
            ON DUPLICATE KEY UPDATE menu_name=VALUES(menu_name), category_id=VALUES(category_id)";
    $pdo->exec($sql);
    echo "<p>✅ Menu 'Manajemen Kelas' berhasil didaftarkan ke database.</p>";

    // 2. Beri Akses ke Ketua Yayasan & Kepala Sekolah
    $roles = ['Ketua Yayasan', 'Kepala Sekolah', 'Sekretaris Sekolah'];
    $stmt = $pdo->prepare("INSERT IGNORE INTO menu_permissions (role_name, menu_id, is_allowed) VALUES (?, 'navManajemenKelas', 1)");
    
    foreach($roles as $role) {
        $stmt->execute([$role]);
        echo "<p>✅ Akses diberikan ke: $role</p>";
    }

    echo "<h3>Selesai. Silakan refresh Dashboard.</h3>";
} catch (Exception $e) { echo "Error: " . $e->getMessage(); }
?>