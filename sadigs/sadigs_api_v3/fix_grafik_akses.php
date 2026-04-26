<?php
require_once 'db_connect.php';

try {
    $pdo = getDBConnection();
    
    // 1. Pastikan Kategori Manajemen Yayasan Ada
    $pdo->exec("INSERT INTO menu_categories (category_id, label, sort_order) VALUES ('ManajemenYayasan', '2. MANAJEMEN YAYASAN', 2) ON DUPLICATE KEY UPDATE label=VALUES(label)");
    
    // 2. Suntik paksa menu ke tabel menus
    $pdo->exec("INSERT INTO menus (menu_id, menu_name, category_id, link, icon) 
                VALUES ('navGrafikIbadah', 'Grafik Ibadah Santri', 'ManajemenYayasan', 'worship_recap_chart.html', 'pie-chart') 
                ON DUPLICATE KEY UPDATE menu_name = 'Grafik Ibadah Santri', category_id = 'ManajemenYayasan'");
                
    // 3. Berikan izin default
    $roles = ['Ketua Yayasan', 'Sekretaris Yayasan', 'Bendahara Yayasan'];
    $stmt = $pdo->prepare("INSERT INTO menu_permissions (role_name, menu_id, is_allowed) VALUES (?, 'navGrafikIbadah', 1) ON DUPLICATE KEY UPDATE is_allowed = 1");
    foreach ($roles as $role) { $stmt->execute([$role]); }
    
    echo "<h1 style='color:green'>✅ BERHASIL!</h1><p>Menu <b>Grafik Ibadah Santri</b> telah disuntik paksa ke dalam database Manajemen Akses.</p>";
} catch (Exception $e) {
    echo "<h1 style='color:red'>❌ ERROR</h1><p>" . $e->getMessage() . "</p>";
}
?>