<?php
require_once 'db_connect.php';

try {
    $pdo = getDBConnection();
    echo "<h1>Update Schema v9: Lengkapi Tabel Biodata Santri</h1>";
    
    // Pastikan tabel student_details ada
    $pdo->exec("CREATE TABLE IF NOT EXISTS student_details (
        user_id INT PRIMARY KEY,
        FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
    )");

    // Daftar kolom yang diperlukan sesuai student_data.php
    $columns = [
        'student_photo_path' => "VARCHAR(255) NULL",
        'ijazah_photo_path' => "VARCHAR(255) NULL",
        'kk_photo_path' => "VARCHAR(255) NULL",
        'birth_cert_photo_path' => "VARCHAR(255) NULL",
        'nik' => "VARCHAR(20) NULL",
        'nisn' => "VARCHAR(20) NULL",
        'birth_place' => "VARCHAR(100) NULL",
        'birth_date' => "DATE NULL",
        'student_phone' => "VARCHAR(20) NULL",
        'address' => "TEXT NULL",
        'previous_school' => "VARCHAR(100) NULL",
        'previous_school_address' => "TEXT NULL",
        'child_order' => "INT DEFAULT 0",
        'siblings_count' => "INT DEFAULT 0",
        'step_siblings_count' => "INT DEFAULT 0",
        'medical_history' => "TEXT NULL",
        'responsible_party' => "VARCHAR(50) NULL",
        'parent_username' => "VARCHAR(50) NULL",
        'father_name' => "VARCHAR(100) NULL",
        'father_phone' => "VARCHAR(20) NULL",
        'father_job' => "VARCHAR(100) NULL",
        'father_address' => "TEXT NULL",
        'mother_name' => "VARCHAR(100) NULL",
        'mother_phone' => "VARCHAR(20) NULL",
        'mother_job' => "VARCHAR(100) NULL",
        'mother_address' => "TEXT NULL",
        'parent_name' => "VARCHAR(100) NULL", // Walisantri Name
        'parent_phone' => "VARCHAR(20) NULL", // Walisantri Phone
        'guardian_job' => "VARCHAR(100) NULL",
        'guardian_address' => "TEXT NULL"
    ];
    
    foreach ($columns as $col => $def) {
        try {
            $stmt = $pdo->query("SHOW COLUMNS FROM `student_details` LIKE '$col'");
            if ($stmt->rowCount() == 0) {
                $pdo->exec("ALTER TABLE `student_details` ADD COLUMN `$col` $def");
                echo "<p style='color:green'>✅ Menambahkan kolom: <strong>$col</strong></p>";
            } else {
                echo "<p style='color:gray'>ℹ️ Kolom $col sudah ada.</p>";
            }
        } catch (Exception $e) {
            echo "<p style='color:red'>❌ Gagal cek/tambah kolom $col: " . $e->getMessage() . "</p>";
        }
    }
    echo "<h3>Selesai. Database siap untuk Biodata Santri Lengkap.</h3>";
} catch (Exception $e) {
    echo "<h3 style='color:red'>Error: " . $e->getMessage() . "</h3>";
}
?>