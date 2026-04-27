<?php
require_once 'db_connect.php';
try {
    $pdo = getDBConnection();
    
    // 1. Pastikan kategori ManajemenYayasan ada
    $pdo->exec("INSERT INTO menu_categories (category_id, label, sort_order) VALUES ('ManajemenYayasan', '2. MANAJEMEN YAYASAN', 2) ON DUPLICATE KEY UPDATE label=VALUES(label)");
    
    // 2. Tambah / Paksa menu Grafik Rekap Ibadah masuk ke kategori ManajemenYayasan
    $pdo->exec("INSERT INTO menus (menu_id, menu_name, category_id, link, icon) 
                VALUES ('navGrafikIbadah', 'Grafik Rekap Ibadah', 'ManajemenYayasan', 'worship_recap_chart.html', 'pie-chart') 
                ON DUPLICATE KEY UPDATE category_id = 'ManajemenYayasan', menu_name = 'Grafik Rekap Ibadah'");
    
    // 3. Beri Izin ke Ketua, Sekretaris, Bendahara Yayasan
    $roles = ['Ketua Yayasan', 'Sekretaris Yayasan', 'Bendahara Yayasan'];
    $stmt = $pdo->prepare("INSERT INTO menu_permissions (role_name, menu_id, is_allowed) VALUES (?, 'navGrafikIbadah', 1) ON DUPLICATE KEY UPDATE is_allowed = 1");
    foreach ($roles as $role) {
        $stmt->execute([$role]);
    }
    
    echo "<h1 style='color:green'>✅ BERHASIL DIPERBAIKI!</h1>";
    echo "<p>Menu 'Grafik Rekap Ibadah' telah ditambahkan secara paksa ke dalam kategori '2. MANAJEMEN YAYASAN'.</p>";
} catch (Exception $e) {
    echo "<h1 style='color:red'>❌ ERROR:</h1> <p>" . $e->getMessage() . "</p>";
}
?>