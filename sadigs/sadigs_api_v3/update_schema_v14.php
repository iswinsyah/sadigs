<?php
require_once 'db_connect.php';

try {
    $pdo = getDBConnection();
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $sql = "
    CREATE TABLE IF NOT EXISTS `leave_requests` (
      `id` int(11) NOT NULL AUTO_INCREMENT,
      `user_id` int(11) NOT NULL,
      `recipient` varchar(255) NOT NULL,
      `leave_type` varchar(255) NOT NULL,
      `description` text,
      `start_date` date NOT NULL,
      `end_date` date NOT NULL,
      `status` enum('pending','approved','rejected') NOT NULL DEFAULT 'pending',
      `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
      PRIMARY KEY (`id`),
      KEY `user_id` (`user_id`),
      CONSTRAINT `leave_requests_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ";

    $pdo->exec($sql);
    echo "Tabel 'leave_requests' berhasil dibuat atau sudah ada.";

} catch(PDOException $e) {
    die("ERROR: Could not execute $sql. " . $e->getMessage());
}
?>