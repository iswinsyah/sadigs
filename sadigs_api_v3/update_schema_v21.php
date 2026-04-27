<?php
require_once 'db_connect.php';

try {
    $pdo = getDBConnection();
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    echo "<h1>Update Schema v21: Tambah Kolom NPWP</h1>";

    $check_stmt = $pdo->query("SHOW COLUMNS FROM `employee_details` LIKE 'npwp'");
    if (!$check_stmt->fetch(PDO::FETCH_ASSOC)) {
        $pdo->exec("ALTER TABLE `employee_details` ADD COLUMN `npwp` VARCHAR(25) NULL AFTER `nik`;");
        echo "<p style='color:green;'>✅ Kolom 'npwp' berhasil ditambahkan ke tabel 'employee_details'.</p>";
    } else {
        echo "<p style='color:blue;'>ℹ️ Kolom 'npwp' di 'employee_details' sudah ada.</p>";
    }

    echo "<h3>Selesai.</h3>";

} catch (Exception $e) {
    echo "<h3 style='color:red'>❌ Error: " . $e->getMessage() . "</h3>";
}
?>