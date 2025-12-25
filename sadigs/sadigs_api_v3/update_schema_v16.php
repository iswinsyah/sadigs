<?php
require_once 'db_connect.php';

try {
    $pdo = getDBConnection();
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    echo "<h1>Update Schema v16: Modify 'leave_requests' Table</h1>";

    // Cek apakah kolom status masih ada
    $check_status = $pdo->query("SHOW COLUMNS FROM `leave_requests` LIKE 'status'");
    if ($check_status->rowCount() > 0) {
        $pdo->exec("ALTER TABLE `leave_requests` DROP COLUMN `status`;");
        echo "<p style='color:green;'>✅ Kolom 'status' berhasil dihapus.</p>";
    } else {
        echo "<p style='color:blue;'>ℹ️ Kolom 'status' sudah tidak ada.</p>";
    }

    // Cek apakah kolom recipient masih ada
    $check_recipient = $pdo->query("SHOW COLUMNS FROM `leave_requests` LIKE 'recipient'");
    if ($check_recipient->rowCount() > 0) {
        $pdo->exec("ALTER TABLE `leave_requests` CHANGE `recipient` `approvals` TEXT NOT NULL;");
        echo "<p style='color:green;'>✅ Kolom 'recipient' berhasil diubah menjadi 'approvals' (TEXT).</p>";
    } else {
        echo "<p style='color:blue;'>ℹ️ Kolom 'approvals' sepertinya sudah ada.</p>";
    }

    echo "<h3>Setup Selesai. Struktur tabel 'leave_requests' telah diperbarui.</h3>";

} catch (Exception $e) {
    die("<h3 style='color:red'>❌ Error: " . $e->getMessage() . "</h3>");
}
?>