<?php
require_once 'db_connect.php';

echo "<h1>Perbaikan Database Menu</h1>";

try {
    $pdo = getDBConnection();
    
    // Cek apakah kolom 'can_view' ada (versi lama)
    $check = $pdo->query("SHOW COLUMNS FROM menu_permissions LIKE 'can_view'");
    
    if ($check->rowCount() > 0) {
        // Ubah menjadi 'is_allowed'
        $pdo->exec("ALTER TABLE menu_permissions CHANGE COLUMN can_view is_allowed TINYINT(1) DEFAULT 0");
        echo "<p style='color:green'>✅ Berhasil mengubah kolom 'can_view' menjadi 'is_allowed'.</p>";
    } else {
        // Cek apakah 'is_allowed' sudah ada
        $check2 = $pdo->query("SHOW COLUMNS FROM menu_permissions LIKE 'is_allowed'");
        if ($check2->rowCount() > 0) {
            echo "<p style='color:blue'>ℹ️ Kolom 'is_allowed' sudah ada. Database aman.</p>";
        } else {
            echo "<p style='color:red'>❌ Kolom 'can_view' maupun 'is_allowed' tidak ditemukan.</p>";
        }
    }
} catch (Exception $e) {
    echo "<p style='color:red'>Error: " . $e->getMessage() . "</p>";
}
?>