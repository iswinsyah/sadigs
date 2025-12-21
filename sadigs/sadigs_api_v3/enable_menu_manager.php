<?php
require_once 'db_connect.php';

try {
    $pdo = getDBConnection();
    
    // Tambahkan izin untuk Ketua Yayasan melihat menu 'navMenuManagement'
    $sql = "INSERT INTO menu_permissions (role_name, menu_id, can_view) 
            VALUES ('Ketua Yayasan', 'navMenuManagement', 1)
            ON DUPLICATE KEY UPDATE can_view = 1";
            
    $pdo->exec($sql);
    
    echo "<h1>Sukses!</h1>";
    echo "<p>Menu 'Manajemen Akses' (navMenuManagement) telah diaktifkan untuk <strong>Ketua Yayasan</strong>.</p>";
    echo "<p><a href='../dashboard.html'>Kembali ke Dashboard</a></p>";
    
} catch (PDOException $e) {
    echo "<h1>Gagal</h1><p>Error: " . $e->getMessage() . "</p>";
}
?>