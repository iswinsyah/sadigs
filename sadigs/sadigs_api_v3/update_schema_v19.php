<?php
require_once 'db_connect.php';

try {
    $pdo = getDBConnection();
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    echo "<h1>Update Schema v19: Tabel Rencana Anggaran Operasional</h1>";

    $sql = "
    CREATE TABLE IF NOT EXISTS `operational_budgets` (
      `id` INT AUTO_INCREMENT PRIMARY KEY,
      `user_id` INT NOT NULL,
      `role_sender` VARCHAR(50) NOT NULL,
      `category` ENUM('Sekolah', 'Asrama Putra', 'Asrama Putri') NOT NULL,
      `period_type` ENUM('Bulanan', 'Semester', 'Tahun Ajaran') NOT NULL,
      `period_name` VARCHAR(50) NULL,
      `year` VARCHAR(20) NOT NULL,
      `details` JSON NOT NULL,
      `total_amount` DECIMAL(15, 2) NOT NULL,
      `status_unit` ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
      `status_foundation` ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
      `final_status` ENUM('draft', 'proposed', 'established') DEFAULT 'draft',
      `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
      `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
      FOREIGN KEY (`user_id`) REFERENCES `users`(`user_id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ";

    $pdo->exec($sql);
    echo "<p style='color:green;'>✅ Tabel 'operational_budgets' berhasil dibuat.</p>";

} catch(PDOException $e) {
    die("ERROR: " . $e->getMessage());
}
?>