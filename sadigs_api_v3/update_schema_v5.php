<?php
require_once 'db_connect.php';

try {
    $pdo = getDBConnection();
    
    echo "<h1>Memperbarui Tabel Ibadah Harian...</h1>";

    // 1. Ubah kolom shalat menjadi VARCHAR untuk menampung pilihan dropdown
    $shalat_columns = ['shalat_subuh', 'shalat_dzuhur', 'shalat_ashar', 'shalat_maghrib', 'shalat_isya'];
    
    foreach ($shalat_columns as $col) {
        // Menggunakan VARCHAR(50) lebih fleksibel daripada ENUM jika ada perubahan opsi di masa depan
        $sql = "ALTER TABLE `ibadah_harian` MODIFY COLUMN `$col` VARCHAR(50) NULL DEFAULT NULL";
        $pdo->exec($sql);
        echo "<p>✅ Kolom '<strong>$col</strong>' berhasil diubah untuk mendukung isian dropdown.</p>";
    }

    // 2. Tambahkan kolom untuk Puasa Sunnah
    $puasa_columns = [
        'puasa_senin' => 'AFTER infaq',
        'puasa_kamis' => 'AFTER puasa_senin'
    ];

    foreach ($puasa_columns as $col => $position) {
        $check = $pdo->query("SHOW COLUMNS FROM `ibadah_harian` LIKE '$col'");
        if ($check->rowCount() == 0) {
            $sql = "ALTER TABLE `ibadah_harian` ADD COLUMN `$col` BOOLEAN DEFAULT FALSE $position";
            $pdo->exec($sql);
            echo "<p>✅ Kolom '<strong>$col</strong>' berhasil ditambahkan.</p>";
        } else {
            echo "<p>ℹ️ Kolom '<strong>$col</strong>' sudah ada.</p>";
        }
    }

    echo "<h1 style='color:green;'>Sukses!</h1><p>Tabel 'ibadah_harian' telah berhasil diperbarui.</p>";
    
} catch (PDOException $e) {
    echo "<h1>Gagal</h1><p>Error: " . $e->getMessage() . "</p>";
}
?>