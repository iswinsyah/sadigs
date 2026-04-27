<?php
require_once 'db_connect.php';

try {
    $pdo = getDBConnection();
    echo "<h1>Perbaikan Struktur Database</h1>";

    // 1. Pastikan tabel user_roles ada
    $pdo->exec("CREATE TABLE IF NOT EXISTS user_roles (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        role_name VARCHAR(50) NOT NULL,
        status VARCHAR(20) DEFAULT 'pending',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY unique_user_role (user_id, role_name)
    )");
    echo "<p>✅ Tabel 'user_roles' siap.</p>";

    // 2. Pastikan Unique Key ada (untuk tabel lama)
    try {
        $pdo->exec("ALTER TABLE user_roles ADD UNIQUE KEY unique_user_role (user_id, role_name)");
        echo "<p>✅ Unique Key berhasil ditambahkan.</p>";
    } catch (Exception $e) {
        echo "<p>ℹ️ Unique Key sudah ada (Aman).</p>";
    }

    echo "<h3>Selesai. Silakan tutup halaman ini.</h3>";

} catch (Exception $e) {
    echo "<h3 style='color:red'>Error: " . $e->getMessage() . "</h3>";
}
?>