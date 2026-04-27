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
        $stmt = $pdo->prepare("INSERT INTO menus (menu_id, menu_name, category_id, link, icon) VALUES (?, ?, 'ManajemenYayasan', 'grafik_rekap_ibadah.html', 'pie-chart') ON DUPLICATE KEY UPDATE link = 'grafik_rekap_ibadah.html', icon = 'pie-chart', category_id = 'ManajemenYayasan'");
        $stmt->execute([$menu_id, $menu_name]);
    } catch (Exception $e) {
        // Fallback jika struktur tabel menus berbeda
        $stmt = $pdo->prepare("UPDATE menus SET link = 'grafik_rekap_ibadah.html', icon = 'pie-chart' WHERE menu_id = ?");
        $stmt->execute([$menu_id]);
    }
    echo "<p>✅ Menu <b>'$menu_name'</b> berhasil didaftarkan ke Master Menu (akan muncul di Manajemen Akses).</p>";

    // 2. Berikan izin langsung ke Ketua Yayasan agar muncul di Aplikasi
    $admin_roles = ['Ketua Yayasan', 'Sekretaris Yayasan'];
    
    try {
        $stmt_perm = $pdo->prepare("INSERT INTO menu_permissions (menu_id, role_name, is_allowed) VALUES (?, ?, 1) ON DUPLICATE KEY UPDATE is_allowed = 1");
        foreach ($admin_roles as $role) {
            $stmt_perm->execute([$menu_id, $role]);
            echo "<p>✅ Akses sidebar diberikan otomatis kepada peran: <b>$role</b></p>";
        }
    } catch (Exception $e) {
        $stmt_perm = $pdo->prepare("INSERT INTO menu_permissions (menu_id, role_name, can_view) VALUES (?, ?, 1) ON DUPLICATE KEY UPDATE can_view = 1");
        foreach ($admin_roles as $role) {
            $stmt_perm->execute([$menu_id, $role]);
            echo "<p>✅ Akses sidebar diberikan otomatis kepada peran: <b>$role</b></p>";
        }
    }

    echo "<hr><h3 style='color:green;'>Selesai! Silakan kembali ke web SADIGS dan tekan Ctrl+F5.</h3>";

} catch(PDOException $e) {
    die("ERROR: " . $e->getMessage());
}
?>