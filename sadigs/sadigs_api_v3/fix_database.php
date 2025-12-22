<?php
require_once 'db_connect.php';

try {
    $pdo = getDBConnection();
    echo "<h1>Perbaikan Struktur Database</h1>";

    // Daftar kolom yang wajib ada untuk fitur Ibadah Harian saat ini
    $required_columns = [
        'shalat_subuh' => "VARCHAR(50) NULL DEFAULT NULL",
        'shalat_dzuhur' => "VARCHAR(50) NULL DEFAULT NULL",
        'shalat_ashar' => "VARCHAR(50) NULL DEFAULT NULL",
        'shalat_maghrib' => "VARCHAR(50) NULL DEFAULT NULL",
        'shalat_isya' => "VARCHAR(50) NULL DEFAULT NULL",
        'shalat_tahajud' => "BOOLEAN DEFAULT FALSE",
        'shalat_dhuha' => "BOOLEAN DEFAULT FALSE",
        'infaq' => "BOOLEAN DEFAULT FALSE",
        'puasa_senin' => "BOOLEAN DEFAULT FALSE",
        'puasa_kamis' => "BOOLEAN DEFAULT FALSE",
        'quran_last_page' => "INT NULL DEFAULT 0",
        'notes' => "TEXT NULL"
    ];

    foreach ($required_columns as $col => $definition) {
        // Cek eksistensi kolom
        $stmt = $pdo->query("SHOW COLUMNS FROM `ibadah_harian` LIKE '$col'");
        if ($stmt->rowCount() == 0) {
            // Jika tidak ada, tambahkan
            $pdo->exec("ALTER TABLE `ibadah_harian` ADD COLUMN `$col` $definition");
            echo "<p>✅ Menambahkan kolom: <strong>$col</strong></p>";
        } else {
            // Jika ada, pastikan tipenya benar (MODIFY)
            $pdo->exec("ALTER TABLE `ibadah_harian` MODIFY COLUMN `$col` $definition");
            echo "<p>ℹ️ Memperbarui kolom: <strong>$col</strong></p>";
        }
    }

    // Hapus kolom lama yang tidak terpakai (opsional, biar bersih)
    $deprecated_columns = ['baca_quran', 'juz_quran', 'surat_quran', 'ayat_quran'];
    foreach ($deprecated_columns as $col) {
        $stmt = $pdo->query("SHOW COLUMNS FROM `ibadah_harian` LIKE '$col'");
        if ($stmt->rowCount() > 0) {
            $pdo->exec("ALTER TABLE `ibadah_harian` DROP COLUMN `$col`");
            echo "<p>🗑️ Menghapus kolom lama: <strong>$col</strong></p>";
        }
    }

    echo "<h2 style='color:green'>Database Berhasil Diperbaiki! Silakan coba simpan formulir lagi.</h2>";

} catch (Exception $e) {
    echo "<h2 style='color:red'>Error: " . $e->getMessage() . "</h2>";
}
?>