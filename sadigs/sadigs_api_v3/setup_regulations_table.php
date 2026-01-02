<?php
require_once 'db_connect.php';

try {
    $pdo = getDBConnection();
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $sql = "CREATE TABLE IF NOT EXISTS regulations (
        id INT AUTO_INCREMENT PRIMARY KEY,
        created_by INT NOT NULL,
        target_role VARCHAR(50) NOT NULL, -- 'Semua', 'Musyrif', 'Walisantri', dll
        title VARCHAR(255) NOT NULL,
        content TEXT NOT NULL,
        status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (created_by) REFERENCES users(user_id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";
    
    $pdo->exec($sql);
    echo "<h1>✅ Tabel 'regulations' berhasil dibuat.</h1>";
} catch (Exception $e) { echo "Error: " . $e->getMessage(); }
?>