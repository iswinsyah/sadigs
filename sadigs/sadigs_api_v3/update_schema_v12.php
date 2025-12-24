<?php
require_once 'db_connect.php';

try {
    $pdo = getDBConnection();
    echo "<h1>Update Schema v12: Tambah Kolom Media Sosial Pegawai</h1>";

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

    echo "<h3>Selesai. " . ($success_count > 0 ? "Skema berhasil diperbarui!" : "Tidak ada perubahan pada skema.") . "</h3>";

} catch (Exception $e) {
    echo "<h3 style='color:red'>❌ Error: " . $e->getMessage() . "</h3>";
}
?>