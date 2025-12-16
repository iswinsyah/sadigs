<?php
// =================================================================
// SADIGS 3.0: TES KONEKSI DATABASE
// File ini HARUS dimulai dengan '<?php' tanpa spasi/baris kosong sebelumnya.
// =================================================================

// Pastikan db_connect.php ada di folder yang sama (sadigs_api_v3)
require_once 'db_connect.php'; 

// Atur header agar mudah dibaca di browser (Plain Text)
header('Content-Type: text/plain');

try {
    // Panggil fungsi koneksi
    $pdo = getDBConnection();
    
    if ($pdo) {
        echo "✅ KONEKSI DATABASE BERHASIL!\n";
        echo "PDO berhasil terhubung ke database " . DB_NAME . ".\n";
        echo "Anda sekarang dapat melanjutkan pengujian login di index.html.";
    } else {
         echo "❌ KONEKSI DATABASE GAGAL. Periksa log atau kredensial.";
    }

} catch (Exception $e) {
    echo "❌ KONEKSI DATABASE GAGAL TOTAL.\n";
    echo "Detail Kesalahan: " . $e->getMessage() . "\n";
    echo "PERIKSA KEMBALI DB_USER, DB_PASS, dan DB_NAME di file db_connect.php.";
}

// Tidak perlu tag penutup untuk menghindari output yang tidak disengaja