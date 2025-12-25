<?php
require_once 'db_connect.php';

try {
    $pdo = getDBConnection();
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    echo "<h1>Update Schema v17: Create 'payments' Table</h1>";

    $sql = "
    CREATE TABLE IF NOT EXISTS `payments` (
      `id` INT AUTO_INCREMENT PRIMARY KEY,
      `walisantri_user_id` INT NOT NULL,
      `student_user_id` INT NOT NULL,
      `payment_date` DATE NOT NULL,
      `details` JSON NOT NULL,
      `total_amount` DECIMAL(15, 2) NOT NULL,
      `proof_file` VARCHAR(255) NOT NULL,
      `notes` TEXT,
      `status` ENUM('pending', 'approved', 'rejected') NOT NULL DEFAULT 'pending',
      `validator_user_id` INT NULL,
      `validated_at` DATETIME NULL,
      `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ";

    $pdo->exec($sql);
    echo "<p style='color:green;'>✅ Tabel 'payments' berhasil dibuat atau sudah ada.</p>";
    echo "<h3>Setup Selesai.</h3>";

} catch(PDOException $e) {
    die("ERROR: " . $e->getMessage());
}
?>