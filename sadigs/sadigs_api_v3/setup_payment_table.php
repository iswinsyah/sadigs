<?php
// Setup Payment Table - Force Update
require_once 'db_connect.php';

try {
    $pdo = getDBConnection();
    
    $sql = "CREATE TABLE IF NOT EXISTS payments (
        id INT AUTO_INCREMENT PRIMARY KEY,
        student_id INT NOT NULL,
        payment_type VARCHAR(50) NOT NULL,
        amount DECIMAL(15, 2) NOT NULL,
        payment_date DATE NOT NULL,
        proof_file VARCHAR(255) NULL,
        notes TEXT NULL,
        status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
        validated_by INT NULL,
        validated_at DATETIME NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (student_id) REFERENCES users(user_id) ON DELETE CASCADE
    )";

    $pdo->exec($sql);
    echo "<h3>Berhasil! Tabel 'payments' telah dibuat/diperbarui.</h3>";
    echo "<p>Kolom: id, student_id, payment_type, amount, payment_date, proof_file, notes, status, validated_by, validated_at, created_at</p>";

} catch (Exception $e) {
    echo "<h3>Gagal: " . $e->getMessage() . "</h3>";
}
?>