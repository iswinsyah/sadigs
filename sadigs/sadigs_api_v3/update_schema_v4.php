<?php
require_once 'db_connect.php';

try {
    $pdo = getDBConnection();
    
    $sql = "CREATE TABLE IF NOT EXISTS `ibadah_harian` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `user_id` INT NOT NULL,
        `report_date` DATE NOT NULL,
        `shalat_subuh` BOOLEAN DEFAULT FALSE,
        `shalat_dzuhur` BOOLEAN DEFAULT FALSE,
        `shalat_ashar` BOOLEAN DEFAULT FALSE,
        `shalat_maghrib` BOOLEAN DEFAULT FALSE,
        `shalat_isya` BOOLEAN DEFAULT FALSE,
        `shalat_tahajud` BOOLEAN DEFAULT FALSE,
        `shalat_dhuha` BOOLEAN DEFAULT FALSE,
        `baca_quran` BOOLEAN DEFAULT FALSE,
        `juz_quran` INT NULL,
        `surat_quran` VARCHAR(100) NULL,
        `ayat_quran` VARCHAR(50) NULL,
        `infaq` BOOLEAN DEFAULT FALSE,
        `notes` TEXT NULL,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY `unique_report` (`user_id`, `report_date`),
        FOREIGN KEY (`user_id`) REFERENCES `users`(`user_id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";

    $pdo->exec($sql);
    echo "<h1>Sukses!</h1><p>Tabel 'ibadah_harian' berhasil dibuat dan siap digunakan.</p>";
    
} catch (PDOException $e) {
    echo "<h1>Gagal</h1><p>Error: " . $e->getMessage() . "</p>";
}
?>