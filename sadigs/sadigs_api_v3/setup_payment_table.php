<?php
require_once 'db_connect.php';

try {
    $pdo = getDBConnection();
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    echo "<h1>Perbaikan Struktur Tabel Payments (Force Update)</h1>";

    // 1. Cek apakah tabel 'payments' ada
    $tableExists = $pdo->query("SHOW TABLES LIKE 'payments'")->rowCount() > 0;

    if (!$tableExists) {
        // Buat baru jika belum ada
        $sql = "CREATE TABLE payments (
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
        echo "<p style='color:green'>✅ Tabel 'payments' berhasil dibuat baru.</p>";
    } else {
        echo "<p>ℹ️ Tabel 'payments' sudah ada. Memeriksa kelengkapan kolom...</p>";

        // 2. Cek dan Tambah Kolom 'walisantri_user_id'
        try {
            $pdo->query("SELECT walisantri_user_id FROM payments LIMIT 1");
            echo "<p style='color:blue'>ℹ️ Kolom 'walisantri_user_id' sudah ada.</p>";
        } catch (Exception $e) {
            $pdo->exec("ALTER TABLE payments ADD COLUMN walisantri_user_id INT NOT NULL AFTER id");
            echo "<p style='color:green'>✅ Kolom 'walisantri_user_id' berhasil ditambahkan.</p>";
        }

        // 3. Cek dan Perbaiki 'student_user_id'
        try {
            $pdo->query("SELECT student_user_id FROM payments LIMIT 1");
            echo "<p style='color:blue'>ℹ️ Kolom 'student_user_id' sudah ada.</p>";
        } catch (Exception $e) {
            // Cek apakah ada student_id (nama lama)
            try {
                $pdo->query("SELECT student_id FROM payments LIMIT 1");
                // Rename
                $pdo->exec("ALTER TABLE payments CHANGE COLUMN student_id student_user_id INT NOT NULL");
                echo "<p style='color:green'>✅ Kolom 'student_id' diubah menjadi 'student_user_id'.</p>";
            } catch (Exception $ex) {
                // Jika tidak ada keduanya, buat baru
                $pdo->exec("ALTER TABLE payments ADD COLUMN student_user_id INT NOT NULL AFTER walisantri_user_id");
                echo "<p style='color:green'>✅ Kolom 'student_user_id' berhasil ditambahkan.</p>";
            }
        }

        // 4. Cek 'details'
        try {
            $pdo->query("SELECT details FROM payments LIMIT 1");
        } catch (Exception $e) {
            $pdo->exec("ALTER TABLE payments ADD COLUMN details JSON NOT NULL AFTER payment_date");
            echo "<p style='color:green'>✅ Kolom 'details' berhasil ditambahkan.</p>";
        }

        // 5. Cek 'total_amount'
        try {
            $pdo->query("SELECT total_amount FROM payments LIMIT 1");
        } catch (Exception $e) {
            try {
                $pdo->query("SELECT amount FROM payments LIMIT 1");
                $pdo->exec("ALTER TABLE payments CHANGE COLUMN amount total_amount DECIMAL(15, 2) NOT NULL");
                echo "<p style='color:green'>✅ Kolom 'amount' diubah menjadi 'total_amount'.</p>";
            } catch (Exception $ex) {
                $pdo->exec("ALTER TABLE payments ADD COLUMN total_amount DECIMAL(15, 2) NOT NULL AFTER details");
                echo "<p style='color:green'>✅ Kolom 'total_amount' berhasil ditambahkan.</p>";
            }
        }

        // 6. Cek 'validator_user_id'
        try {
            $pdo->query("SELECT validator_user_id FROM payments LIMIT 1");
        } catch (Exception $e) {
            try {
                $pdo->query("SELECT validated_by FROM payments LIMIT 1");
                $pdo->exec("ALTER TABLE payments CHANGE COLUMN validated_by validator_user_id INT NULL");
                echo "<p style='color:green'>✅ Kolom 'validated_by' diubah menjadi 'validator_user_id'.</p>";
            } catch (Exception $ex) {
                $pdo->exec("ALTER TABLE payments ADD COLUMN validator_user_id INT NULL AFTER status");
                echo "<p style='color:green'>✅ Kolom 'validator_user_id' berhasil ditambahkan.</p>";
            }
        }
    }
    
    echo "<h3>Selesai. Silakan coba kirim formulir lagi.</h3>";

} catch (Exception $e) {
    echo "<h3>Error: " . $e->getMessage() . "</h3>";
}
?>