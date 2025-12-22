<?php
require_once 'db_connect.php';

try {
    $pdo = getDBConnection();
    
    // Daftar kolom baru untuk path file
    $columns = [
        "student_photo_path VARCHAR(255) AFTER user_id",
        "ijazah_photo_path VARCHAR(255) AFTER student_photo_path",
        "kk_photo_path VARCHAR(255) AFTER ijazah_photo_path",
        "birth_cert_photo_path VARCHAR(255) AFTER kk_photo_path"
    ];

    foreach ($columns as $col) {
        $colName = explode(' ', $col)[0];
        $check = $pdo->query("SHOW COLUMNS FROM student_details LIKE '$colName'");
        if ($check->rowCount() == 0) {
            $pdo->exec("ALTER TABLE student_details ADD COLUMN $col");
            echo "<p>✅ Kolom '$colName' berhasil ditambahkan.</p>";
        } else {
            echo "<p>ℹ️ Kolom '$colName' sudah ada.</p>";
        }
    }
    
    echo "<h1>Sukses!</h1><p>Database siap untuk fitur Upload Dokumen Santri.</p>";
    
} catch (PDOException $e) {
    echo "<h1>Gagal</h1><p>Error: " . $e->getMessage() . "</p>";
}
?>