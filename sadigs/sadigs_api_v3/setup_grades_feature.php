<?php
// File: setup_grades_feature.php
// Script untuk membuat tabel nilai (raport) di database.
require_once 'db_connect.php';

try {
    $pdo = getDBConnection();
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    echo "<h1>Setup Fitur Nilai Rapot</h1>";

    // 1. Buat Tabel Nilai
    $sql = "CREATE TABLE IF NOT EXISTS student_grades (
        id INT AUTO_INCREMENT PRIMARY KEY,
        student_id INT NOT NULL,
        academic_year VARCHAR(20) NOT NULL, -- Contoh: 2024/2025
        semester ENUM('Ganjil', 'Genap') NOT NULL,
        subject VARCHAR(100) NOT NULL,
        score DECIMAL(5,2) NOT NULL,
        grade CHAR(2), -- A, B, C, D
        notes TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (student_id) REFERENCES users(user_id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";
    
    $pdo->exec($sql);
    echo "<p style='color:green'>✅ Tabel 'student_grades' berhasil dibuat atau sudah ada.</p>";

    // 2. Isi Data Dummy (Opsional, agar tidak kosong saat dites)
    $stmt = $pdo->query("SELECT user_id FROM user_roles WHERE role_name LIKE 'Santri%' LIMIT 1");
    $santri_id = $stmt->fetchColumn();

    if ($santri_id) {
        $check = $pdo->prepare("SELECT COUNT(*) FROM student_grades WHERE student_id = ?");
        $check->execute([$santri_id]);
        if ($check->fetchColumn() == 0) {
            $pdo->prepare("INSERT INTO student_grades (student_id, academic_year, semester, subject, score, grade, notes) VALUES (?, '2024/2025', 'Ganjil', 'Tahfizh Al-Quran', 95, 'A', 'Sangat baik dalam hafalan')")->execute([$santri_id]);
            echo "<p>ℹ️ Data nilai dummy ditambahkan untuk Santri ID: $santri_id</p>";
        }
    }

    echo "<h3>Setup Selesai. Fitur Nilai Rapot siap digunakan.</h3>";

} catch(PDOException $e) {
    die("ERROR: " . $e->getMessage());
}
?>