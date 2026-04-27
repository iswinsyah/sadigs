<?php
require_once 'db_connect.php';

try {
    $pdo = getDBConnection();
    echo "<h1>Force Fix: Manajemen Kelas</h1>";

    // 1. Paksa Insert Menu ke Tabel Master
    $sql = "INSERT INTO menus (menu_id, menu_name, category_id, icon, link) 
            VALUES ('navManajemenKelas', 'Manajemen Kelas', 'ManajemenSekolah', 'users', 'class_management.html') 
            ON DUPLICATE KEY UPDATE menu_name='Manajemen Kelas', category_id='ManajemenSekolah', icon='users', link='class_management.html'";
    $pdo->exec($sql);
    echo "<p style='color:green'>✅ Menu 'Manajemen Kelas' berhasil disuntikkan ke database.</p>";

    // 2. Paksa Beri Izin Akses
    $roles = ['Ketua Yayasan', 'Kepala Sekolah', 'Sekretaris Sekolah'];
    $stmt = $pdo->prepare("INSERT IGNORE INTO menu_permissions (role_name, menu_id, is_allowed) VALUES (?, 'navManajemenKelas', 1)");
    
    foreach($roles as $role) {
        $stmt->execute([$role]);
        echo "<p style='color:green'>✅ Izin akses diberikan ke: <strong>$role</strong></p>";
    }

    echo "<h3>Selesai. Silakan refresh halaman Manajemen Akses sekarang.</h3>";

} catch (Exception $e) {
    echo "<h3 style='color:red'>Error: " . $e->getMessage() . "</h3>";
}
?>