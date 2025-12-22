<?php
require_once 'db_connect.php';

try {
    $pdo = getDBConnection();
    
    echo "<h1>Membuat Tabel Mentoring...</h1>";

    $sql = "CREATE TABLE IF NOT EXISTS `mentoring_assignments` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `student_id` INT NOT NULL,
        `musyrif_id` INT NOT NULL,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY `unique_student_mentor` (`student_id`),
        FOREIGN KEY (`student_id`) REFERENCES `users`(`user_id`) ON DELETE CASCADE,
        FOREIGN KEY (`musyrif_id`) REFERENCES `users`(`user_id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";

    $pdo->exec($sql);
    echo "<h1 style='color:green;'>Sukses!</h1><p>Tabel 'mentoring_assignments' berhasil dibuat.</p>";
    
} catch (PDOException $e) {
    echo "<h1>Gagal</h1><p>Error: " . $e->getMessage() . "</p>";
}
?>