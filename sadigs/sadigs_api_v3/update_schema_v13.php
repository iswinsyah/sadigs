<?php
require_once 'db_connect.php';

try {
    $pdo = getDBConnection();
    echo "<h1>Update Schema v13: Tambah Kolom Tanggal Masuk</h1>";

    // 1. Tambah kolom di tabel employee_details
    try {
        $check_stmt_emp = $pdo->query("SHOW COLUMNS FROM `employee_details` LIKE 'entry_date'");
        if (!$check_stmt_emp->fetch(PDO::FETCH_ASSOC)) {
            $pdo->exec("ALTER TABLE `employee_details` ADD COLUMN `entry_date` DATE NULL AFTER `graduation_year`");
            echo "<p style='color:green;'>✅ Kolom 'entry_date' berhasil ditambahkan ke tabel 'employee_details'.</p>";
        } else {
            echo "<p style='color:blue;'>ℹ️ Kolom 'entry_date' di 'employee_details' sudah ada.</p>";
        }
    } catch (Exception $e) {
        echo "<p style='color:red'>❌ Gagal mengubah tabel 'employee_details': " . $e->getMessage() . "</p>";
    }

    // 2. Tambah kolom di tabel student_details
    try {
        $check_stmt_std = $pdo->query("SHOW COLUMNS FROM `student_details` LIKE 'entry_date'");
        if (!$check_stmt_std->fetch(PDO::FETCH_ASSOC)) {
            $pdo->exec("ALTER TABLE `student_details` ADD COLUMN `entry_date` DATE NULL AFTER `previous_school_address`");
            echo "<p style='color:green;'>✅ Kolom 'entry_date' berhasil ditambahkan ke tabel 'student_details'.</p>";
        } else {
            echo "<p style='color:blue;'>ℹ️ Kolom 'entry_date' di 'student_details' sudah ada.</p>";
        }
    } catch (Exception $e) {
        echo "<p style='color:red'>❌ Gagal mengubah tabel 'student_details': " . $e->getMessage() . "</p>";
    }

    echo "<h3>Selesai.</h3>";

} catch (Exception $e) {
    echo "<h3 style='color:red'>❌ Error: " . $e->getMessage() . "</h3>";
}
?>