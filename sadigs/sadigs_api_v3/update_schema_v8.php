<?php
require_once 'db_connect.php';

try {
    $pdo = getDBConnection();
    echo "<h1>Update Schema v8: Validasi Ibadah</h1>";
    
    // Tambahkan kolom validasi jika belum ada
    $cols = [
        'validation_status' => "ENUM('pending', 'approved', 'rejected') DEFAULT 'pending'",
        'validated_at' => "DATETIME NULL",
        'validator_id' => "INT NULL"
    ];
    
    foreach ($cols as $col => $def) {
        $stmt = $pdo->query("SHOW COLUMNS FROM `ibadah_harian` LIKE '$col'");
        if ($stmt->rowCount() == 0) {
            $pdo->exec("ALTER TABLE `ibadah_harian` ADD COLUMN `$col` $def");
            echo "<p style='color:green'>✅ Menambahkan kolom: <strong>$col</strong></p>";
        } else {
            echo "<p style='color:gray'>ℹ️ Kolom $col sudah ada.</p>";
        }
    }
    echo "<h3>Selesai. Database siap untuk fitur validasi.</h3>";
} catch (Exception $e) {
    echo "<h3 style='color:red'>Error: " . $e->getMessage() . "</h3>";
}
?>