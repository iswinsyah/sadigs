<?php
require_once 'db_connect.php';

try {
    $pdo = getDBConnection();
    $pdo->exec("ALTER TABLE `saved_promes` ADD COLUMN `cp` TEXT NULL AFTER `grade`;");
    echo "<h1>✅ Schema updated successfully. 'cp' column added to 'saved_promes'.</h1>";
} catch (Exception $e) {
    // Check if column already exists to prevent error on re-run
    if (strpos($e->getMessage(), 'Duplicate column name') !== false) {
        echo "<h1>ℹ️ Schema already up-to-date. 'cp' column already exists.</h1>";
    } else {
        echo "<h1>❌ Error: " . $e->getMessage() . "</h1>";
    }
}
?>