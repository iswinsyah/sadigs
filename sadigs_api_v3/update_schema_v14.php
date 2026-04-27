<?php
require_once 'db_connect.php';

try {
    $pdo = getDBConnection();
    $pdo->exec("ALTER TABLE `saved_promes` ADD COLUMN `status` ENUM('draft', 'submitted') NOT NULL DEFAULT 'draft' AFTER `promes_data`;");
    echo "<h1>✅ Schema updated successfully. 'status' column added to 'saved_promes'.</h1>";
} catch (Exception $e) {
    // Check if column already exists to prevent error on re-run
    if (strpos($e->getMessage(), 'Duplicate column name') !== false) {
        echo "<h1>ℹ️ Schema already up-to-date. 'status' column already exists.</h1>";
    } else {
        echo "<h1>❌ Error: " . $e->getMessage() . "</h1>";
    }
}
?>