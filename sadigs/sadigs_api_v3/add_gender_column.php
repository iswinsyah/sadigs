<?php
require_once 'db_connect.php';

try {
    $pdo = getDBConnection();
    
    // Cek apakah kolom gender sudah ada
    $check = $pdo->query("SHOW COLUMNS FROM users LIKE 'gender'");
    
    if ($check->rowCount() == 0) {
        // Tambahkan kolom gender jika belum ada
        $sql = "ALTER TABLE users ADD COLUMN gender VARCHAR(20) AFTER email";
        $pdo->exec($sql);
        echo "<h1>Sukses!</h1><p>Kolom 'gender' berhasil ditambahkan ke tabel 'users'. Silakan coba daftar lagi.</p>";
    } else {
        echo "<h1>Info</h1><p>Kolom 'gender' sudah ada di tabel 'users'.</p>";
    }
    
} catch (PDOException $e) {
    echo "<h1>Gagal</h1><p>Error: " . $e->getMessage() . "</p>";
}
?>