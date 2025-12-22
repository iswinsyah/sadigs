<?php
require_once 'db_connect.php';

try {
    $pdo = getDBConnection();
    
    // 1. Tambah kolom 'status' ke tabel user_roles jika belum ada
    // Default 'approved' agar user lama tidak terkunci
    $check = $pdo->query("SHOW COLUMNS FROM user_roles LIKE 'status'");
    if ($check->rowCount() == 0) {
        $sql = "ALTER TABLE user_roles ADD COLUMN status ENUM('pending', 'approved') DEFAULT 'approved'";
        $pdo->exec($sql);
        echo "<p>✅ Kolom 'status' berhasil ditambahkan ke tabel user_roles.</p>";
    } else {
        echo "<p>ℹ️ Kolom 'status' sudah ada.</p>";
    }

    echo "<h1>Sukses!</h1><p>Database siap untuk fitur Verifikasi User.</p>";
    
} catch (PDOException $e) {
    echo "<h1>Gagal</h1><p>Error: " . $e->getMessage() . "</p>";
}
?>