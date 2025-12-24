<?php
require_once 'db_connect.php';

try {
    $pdo = getDBConnection();
    echo "<h1>Update Schema v12: Fix Table & Columns</h1>";

    // 1. Buat Tabel jika belum ada (Gabungan dari v11)
    $sql_create = "CREATE TABLE IF NOT EXISTS `employee_details` (
        `user_id` INT PRIMARY KEY,
        `nik` VARCHAR(20) NULL,
        `birth_place` VARCHAR(100) NULL,
        `birth_date` DATE NULL,
        `marital_status` ENUM('Menikah', 'Pernah Menikah', 'Belum Menikah') NULL,
        `phone` VARCHAR(20) NULL,
        `address` TEXT NULL,
        `last_education` VARCHAR(50) NULL,
        `graduation_year` INT NULL,
        `application_letter_path` VARCHAR(255) NULL,
        `cv_path` VARCHAR(255) NULL,
        `ijazah_path` VARCHAR(255) NULL,
        `kk_path` VARCHAR(255) NULL,
        `ktp_path` VARCHAR(255) NULL,
        `certificate_skill_path` VARCHAR(255) NULL,
        `certificate_award_path` VARCHAR(255) NULL,
        FOREIGN KEY (`user_id`) REFERENCES `users`(`user_id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";
    
    $pdo->exec($sql_create);
    echo "<p style='color:green;'>✅ Tabel 'employee_details' siap (Dibuat/Sudah Ada).</p>";

    // 2. Tambahkan Kolom Media Sosial (v12)
    $columns = [
        'facebook_url' => 'VARCHAR(255) NULL',
        'instagram_url' => 'VARCHAR(255) NULL',
        'tiktok_url' => 'VARCHAR(255) NULL',
        'threads_url' => 'VARCHAR(255) NULL',
        'youtube_url' => 'VARCHAR(255) NULL'
    ];

    $success_count = 0;
    foreach ($columns as $column => $type) {
        $check_stmt = $pdo->query("SHOW COLUMNS FROM `employee_details` LIKE '$column'");
        if (!$check_stmt->fetch(PDO::FETCH_ASSOC)) {
            $pdo->exec("ALTER TABLE `employee_details` ADD COLUMN `$column` $type");
            echo "<p style='color:green;'>✅ Kolom '{$column}' berhasil ditambahkan.</p>";
            $success_count++;
        } else {
            echo "<p style='color:blue;'>ℹ️ Kolom '{$column}' sudah ada.</p>";
        }
    }

    echo "<h3>Selesai. " . ($success_count > 0 ? "Skema berhasil diperbarui!" : "Struktur database sudah sesuai.") . "</h3>";

} catch (Exception $e) {
    echo "<h3 style='color:red'>❌ Error: " . $e->getMessage() . "</h3>";
}
?>