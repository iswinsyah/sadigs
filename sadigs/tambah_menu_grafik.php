<?php
require_once 'sadigs_api_v3/db_connect.php';

try {
    $pdo = getDBConnection();
    
    // 1. Paksa buat kategori Manajemen Yayasan
    $pdo->exec("INSERT INTO menu_categories (category_id, label, sort_order) VALUES ('ManajemenYayasan', '2. MANAJEMEN YAYASAN', 2) ON DUPLICATE KEY UPDATE label='2. MANAJEMEN YAYASAN'");
    
    // 2. Suntik menu langsung ke tabel menus
    $sql = "INSERT INTO menus (menu_id, menu_name, category_id, link, icon) 
            VALUES ('navGrafikIbadah', 'Grafik Rekap Ibadah', 'ManajemenYayasan', 'worship_recap_chart.html', 'pie-chart') 
            ON DUPLICATE KEY UPDATE menu_name='Grafik Rekap Ibadah', category_id='ManajemenYayasan', link='worship_recap_chart.html'";
    $pdo->exec($sql);
    
    // 3. Beri izin akses ke pengurus yayasan
    $roles = ['Ketua Yayasan', 'Sekretaris Yayasan', 'Bendahara Yayasan'];
    $stmt = $pdo->prepare("INSERT INTO menu_permissions (role_name, menu_id, is_allowed) VALUES (?, 'navGrafikIbadah', 1) ON DUPLICATE KEY UPDATE is_allowed=1");
    foreach ($roles as $r) {
        $stmt->execute([$r]);
    }
    
    echo "<h1 style='color:green'>✅ BERHASIL 100%!</h1>";
    echo "<p>Menu <b>Grafik Rekap Ibadah</b> telah ditembak langsung ke Database.</p>";
    echo "<p>Silakan tutup halaman ini, kembali ke Manajemen Akses, dan lakukan Refresh (Ctrl+F5).</p>";
    
} catch (Exception $e) {
    echo "<h1 style='color:red'>❌ ERROR</h1><p>" . $e->getMessage() . "</p>";
}
?>