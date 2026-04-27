<?php
require_once 'db_connect.php';

try {
    $pdo = getDBConnection();
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    echo "<h1>Suntik Database: Grafik Rekap Ibadah</h1>";

    $menu_id = 'navGrafikIbadah';
    $menu_name = 'Grafik Rekap Ibadah';
    
    // 1. Masukkan ke tabel menus agar muncul di halaman Manajemen Akses
    try {
        // Coba dengan kolom lengkap (agar muncul rapi di sidebar)
        // Catatan: Pastikan file HTML Bos bernama grafik_rekap_ibadah.html
        $stmt = $pdo->prepare("INSERT INTO menus (menu_id, menu_name, category, link, icon, order_no) VALUES (?, ?, 'Manajemen Yayasan', 'grafik_rekap_ibadah.html', 'bar-chart-2', 99) ON DUPLICATE KEY UPDATE menu_name = ?");
        $stmt->execute([$menu_id, $menu_name, $menu_name]);
    } catch (Exception $e) {
        // Fallback jika struktur tabel menus berbeda
        $stmt = $pdo->prepare("INSERT IGNORE INTO menus (menu_id, menu_name) VALUES (?, ?)");
        $stmt->execute([$menu_id, $menu_name]);
    }
    echo "<p>✅ Menu <b>'$menu_name'</b> berhasil didaftarkan ke Master Menu (akan muncul di Manajemen Akses).</p>";

    // 2. Berikan izin langsung ke Ketua Yayasan agar muncul di Aplikasi
    $admin_roles = ['Ketua Yayasan', 'Sekretaris Yayasan'];
    $stmt_perm = $pdo->prepare("INSERT INTO menu_permissions (menu_id, role_name, can_view) VALUES (?, ?, 1) ON DUPLICATE KEY UPDATE can_view = 1");
    
    foreach ($admin_roles as $role) {
        $stmt_perm->execute([$menu_id, $role]);
        echo "<p>✅ Akses sidebar diberikan otomatis kepada peran: <b>$role</b></p>";
    }

    echo "<hr><h3 style='color:green;'>Selesai! Silakan kembali ke web SADIGS dan tekan Ctrl+F5.</h3>";

} catch(PDOException $e) {
    die("ERROR: " . $e->getMessage());
}
?>