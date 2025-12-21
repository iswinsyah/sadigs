<?php
require_once 'db_connect.php';

try {
    $pdo = getDBConnection();
    
    $sql = "CREATE TABLE IF NOT EXISTS student_details (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        nisn VARCHAR(20),
        birth_place VARCHAR(50),
        birth_date DATE,
        address TEXT,
        parent_name VARCHAR(100),
        parent_phone VARCHAR(20),
        previous_school VARCHAR(100),
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
        UNIQUE KEY unique_user (user_id)
    )";
    
    $pdo->exec($sql);
    echo "<h1>Sukses!</h1><p>Tabel 'student_details' berhasil dibuat.</p>";
    
} catch (PDOException $e) {
    echo "<h1>Gagal</h1><p>Error: " . $e->getMessage() . "</p>";
}
?>