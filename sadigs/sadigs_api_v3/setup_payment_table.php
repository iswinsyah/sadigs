<?php
// Setup Payment Table - Force Update
require_once 'db_connect.php';

try {
    $pdo = getDBConnection();
    
    $sql = "CREATE TABLE IF NOT EXISTS payments (
        id INT AUTO_INCREMENT PRIMARY KEY,
        walisantri_user_id INT NOT NULL,
        student_user_id INT NOT NULL,
        payment_date DATE NOT NULL,
        details JSON NOT NULL,
        total_amount DECIMAL(15, 2) NOT NULL,
        proof_file VARCHAR(255) NULL,
        notes TEXT NULL,
        status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
        validator_user_id INT NULL,
        validated_at DATETIME NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (student_user_id) REFERENCES users(user_id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";

    $pdo->exec($sql);
    echo "<h3>Berhasil! Tabel 'payments' telah dibuat/diperbarui.</h3>";
    echo "<p>Kolom: id, walisantri_user_id, student_user_id, payment_date, details, total_amount, proof_file, notes, status, validator_user_id, validated_at, created_at</p>";

} catch (Exception $e) {
    echo "<h3>Gagal: " . $e->getMessage() . "</h3>";
}
?>