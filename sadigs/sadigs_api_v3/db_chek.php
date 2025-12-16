<?php
// =================================================================
// SADIGS 3.0: TES KONEKSI DATABASE (VERSI BARU)
// File ini akan menampilkan error detail dari db_connect.php
// =================================================================

// Harap pastikan db_connect.php sudah ada di folder yang sama!
require_once 'db_connect.php'; 

// Atur header agar mudah dibaca di browser (Plain Text)
header('Content-Type: text/plain');

try {
    // Panggil fungsi koneksi
    $pdo = getDBConnection();
    
    if ($pdo) {
        echo "✅ KONEKSI DATABASE BERHASIL!\n";
        echo "PDO berhasil terhubung ke database " . DB_NAME . ".\n";
        echo "Kredensial Anda benar! Sekarang Anda bisa mencoba login di index.html.";
    } else {
         echo "❌ KONEKSI DATABASE GAGAL. Harap periksa detail error dari PDO.";
    }

} catch (Exception $e) {
    // Jika ada error lain selain PDO, tampilkan di sini
    echo "❌ KONEKSI DATABASE GAGAL TOTAL KARENA EXCEPTION LAIN.\n";
    echo "Detail Kesalahan: " . $e->getMessage() . "\n";
}

// Tidak perlu tag penutup