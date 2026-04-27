<?php
require_once 'db_connect.php';

try {
    $pdo = getDBConnection();
    
    echo "<h1>Memperbarui Tabel Ibadah Harian (v6)...</h1>";

    // 1. Hapus kolom tilawah yang lama jika ada
    $old_quran_columns = ['baca_quran', 'juz_quran', 'surat_quran', 'ayat_quran'];
    foreach ($old_quran_columns as $col) {
        $check = $pdo->query("SHOW COLUMNS FROM `ibadah_harian` LIKE '$col'");
        if ($check->rowCount() > 0) {
            $pdo->exec("ALTER TABLE `ibadah_harian` DROP COLUMN `$col`");
            echo "<p>✅ Kolom lama '<strong>$col</strong>' berhasil dihapus.</p>";
        }
    }

    // 2. Tambahkan kolom baru untuk halaman terakhir dibaca
    $new_col = 'quran_last_page';
    $check_new = $pdo->query("SHOW COLUMNS FROM `ibadah_harian` LIKE '$new_col'");
    if ($check_new->rowCount() == 0) {
        // Tambahkan setelah kolom puasa_kamis
        $sql = "ALTER TABLE `ibadah_harian` ADD COLUMN `$new_col` INT NULL DEFAULT 0 AFTER `puasa_kamis`";
        $pdo->exec($sql);
        echo "<p>✅ Kolom baru '<strong>$new_col</strong>' berhasil ditambahkan.</p>";
    } else {
        echo "<p>ℹ️ Kolom '<strong>$new_col</strong>' sudah ada.</p>";
    }

    echo "<h1 style='color:green;'>Sukses!</h1><p>Tabel 'ibadah_harian' telah berhasil diperbarui untuk Tilawah.</p>";
    
} catch (PDOException $e) {
    echo "<h1>Gagal</h1><p>Error: " . $e->getMessage() . "</p>";
}
?>