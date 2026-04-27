<?php
require_once 'db_connect.php';

try {
    $pdo = getDBConnection();
    echo "<h1>Update Schema v10: Laporan Tahfizh</h1>";

    $sql = "CREATE TABLE IF NOT EXISTS `tahfizh_reports` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `student_id` INT NOT NULL,
        `musyrif_id` INT NOT NULL,
        `report_date` DATE NOT NULL,
        `last_surah_name` VARCHAR(50) NULL,
        `last_ayah_number` INT NULL,
        `last_juz_number` INT NULL,
        `last_page_number` INT NULL,
        `fluency_grade` ENUM('A', 'B', 'C', 'D') NULL,
        `tajwid_grade` ENUM('A', 'B', 'C', 'D') NULL,
        `adab_grade` ENUM('A', 'B', 'C', 'D') NULL,
        `murajaah_notes` TEXT NULL,
        `musyrif_notes` TEXT NULL,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY `unique_report_per_day` (`student_id`, `report_date`),
        FOREIGN KEY (`student_id`) REFERENCES `users`(`user_id`) ON DELETE CASCADE,
        FOREIGN KEY (`musyrif_id`) REFERENCES `users`(`user_id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";

    $pdo->exec($sql);
    echo "<h3 style='color:green;'>✅ Sukses! Tabel 'tahfizh_reports' berhasil dibuat.</h3>";

} catch (Exception $e) {
    echo "<h3 style='color:red'>❌ Error: " . $e->getMessage() . "</h3>";
}
?>