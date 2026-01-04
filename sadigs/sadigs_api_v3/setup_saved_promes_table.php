<?php
require_once 'db_connect.php';

try {
    $pdo = getDBConnection();
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $sql = "CREATE TABLE IF NOT EXISTS `saved_promes` (
      `id` INT AUTO_INCREMENT PRIMARY KEY,
      `user_id` INT NOT NULL,
      `subject` VARCHAR(255) NOT NULL,
      `grade` VARCHAR(50) NOT NULL,
      `academic_year` VARCHAR(20) NOT NULL,
      `promes_data` JSON NOT NULL,
      `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
      FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
      UNIQUE KEY `unique_promes` (`user_id`, `subject`, `grade`, `academic_year`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";
    
    $pdo->exec($sql);
    echo "<h1>✅ Tabel 'saved_promes' berhasil dibuat atau sudah ada.</h1>";
} catch (Exception $e) {
    echo "<h1>❌ Error: " . $e->getMessage() . "</h1>";
}
?>