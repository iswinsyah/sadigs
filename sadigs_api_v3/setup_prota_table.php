<?php
require_once 'db_connect.php';

try {
    $pdo = getDBConnection();
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $sql = "CREATE TABLE IF NOT EXISTS annual_programs (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        subject VARCHAR(100) NOT NULL,
        grade VARCHAR(50) NOT NULL,
        academic_year VARCHAR(20) NOT NULL,
        semester ENUM('Ganjil', 'Genap') NOT NULL,
        learning_objective TEXT NOT NULL,
        estimated_hours INT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";
    
    $pdo->exec($sql);
    echo "<h1>✅ Tabel 'annual_programs' (Prota) berhasil dibuat.</h1>";
} catch (Exception $e) { echo "Error: " . $e->getMessage(); }
?>