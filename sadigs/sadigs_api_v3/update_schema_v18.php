<?php
require_once 'db_connect.php';

try {
    $pdo = getDBConnection();
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    echo "<h1>Update Schema v18: Tabel Transaksi Harian</h1>";

    $sql = "
    CREATE TABLE IF NOT EXISTS `daily_transactions` (
      `id` INT AUTO_INCREMENT PRIMARY KEY,
      `transaction_date` DATE NOT NULL,
      `type` ENUM('Pemasukan', 'Pengeluaran') NOT NULL,
      `category` VARCHAR(100) NOT NULL,
      `description` TEXT NOT NULL,
      `amount` DECIMAL(15, 2) NOT NULL,
      `proof_file` VARCHAR(255) NULL,
      `created_by` INT NOT NULL,
      `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
      FOREIGN KEY (`created_by`) REFERENCES `users`(`user_id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ";

    $pdo->exec($sql);
    echo "<p style='color:green;'>✅ Tabel 'daily_transactions' berhasil dibuat.</p>";

} catch(PDOException $e) {
    die("ERROR: " . $e->getMessage());
}
?>