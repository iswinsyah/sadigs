<?php
// --- DEBUGGING: Force display errors ---
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once 'db_connect.php';

try {
    $pdo = getDBConnection();
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    echo "<h1>Update Schema v15: Create 'menus' Table</h1>";

    $sql = "
    CREATE TABLE IF NOT EXISTS `menus` (
      `menu_id` VARCHAR(50) NOT NULL,
      `menu_name` VARCHAR(100) NOT NULL,
      PRIMARY KEY (`menu_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ";

    $pdo->exec($sql);
    echo "<p style='color:green;'>✅ Tabel 'menus' berhasil dibuat atau sudah ada.</p>";
    echo "<h3>Setup Selesai. Anda sekarang bisa menjalankan script setup menu lainnya.</h3>";

} catch(PDOException $e) {
    die("ERROR: Could not execute $sql. " . $e->getMessage());
}
?>