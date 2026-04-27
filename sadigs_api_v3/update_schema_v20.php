<?php
require_once 'db_connect.php';

try {
    $pdo = getDBConnection();
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    echo "<h1>Update Schema v20: Add 'fase' to saved_promes</h1>";

    // 1. Add column if not exists
    $check_col = $pdo->query("SHOW COLUMNS FROM `saved_promes` LIKE 'fase'");
    if (!$check_col->fetch(PDO::FETCH_ASSOC)) {
        $pdo->exec("ALTER TABLE `saved_promes` ADD COLUMN `fase` VARCHAR(5) NULL AFTER `grade`;");
        echo "<p style='color:green;'>✅ Kolom 'fase' berhasil ditambahkan.</p>";
    } else {
        echo "<p style='color:blue;'>ℹ️ Kolom 'fase' sudah ada.</p>";
    }

    // 2. Drop old unique key and add new one
    $check_key = $pdo->query("SHOW INDEX FROM `saved_promes` WHERE Key_name = 'unique_promes'");
    $index_info = $check_key->fetchAll(PDO::FETCH_ASSOC);
    
    $has_fase_in_key = false;
    foreach($index_info as $index) { if ($index['Column_name'] == 'fase') $has_fase_in_key = true; }

    if (!$has_fase_in_key && !empty($index_info)) {
        $pdo->exec("ALTER TABLE `saved_promes` DROP INDEX `unique_promes`;");
        $pdo->exec("ALTER TABLE `saved_promes` ADD UNIQUE KEY `unique_promes` (`user_id`, `subject`, `fase`, `grade`, `academic_year`);");
        echo "<p style='color:green;'>✅ UNIQUE KEY 'unique_promes' diperbarui untuk menyertakan 'fase'.</p>";
    } else {
        echo "<p style='color:blue;'>ℹ️ UNIQUE KEY 'unique_promes' sudah sesuai.</p>";
    }
    
    echo "<h3>Selesai.</h3>";

} catch (Exception $e) {
    echo "<h3 style='color:red'>❌ Error: " . $e->getMessage() . "</h3>";
}
?>