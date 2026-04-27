<?php
// =================================================================
// SADIGS 3.0: TES KONEKSI DATABASE (VERSI 3.0)
// File ini akan menampilkan status koneksi dengan lebih jelas.
// =================================================================

// Atur header agar mudah dibaca di browser (Plain Text)
header('Content-Type: text/plain; charset=utf-8');

// Tangkap output buffer untuk menganalisis apa yang dikirim oleh db_connect.php
ob_start();
require_once 'db_connect.php'; 
$output_from_db_connect = ob_get_clean();

try {
    // Panggil fungsi koneksi
    $pdo = getDBConnection();
    
    if ($pdo) {
        echo "✅ KONEKSI DATABASE BERHASIL!\n\n";
        echo "Detail:\n";
        echo "---------------------------------\n";
        echo "Host: " . DB_HOST . "\n";
        echo "Database: " . DB_NAME . "\n";
        echo "User: " . DB_USER . "\n";
        echo "---------------------------------\n";
        echo "Kredensial Anda benar. Aplikasi siap digunakan.";
    } else {
         // Ini seharusnya tidak pernah tercapai karena db_connect.php akan exit
         echo "❌ KONEKSI DATABASE GAGAL.\n";
         echo "Fungsi getDBConnection() tidak mengembalikan objek PDO yang valid.";
    }

} catch (Exception $e) {
    // Jika ada error lain di luar PDO, tangkap di sini.
    echo "❌ KONEKSI DATABASE GAGAL TOTAL (EXCEPTION).\n";
    echo "Detail Kesalahan: " . $e->getMessage();
}

// Jika db_connect.php mengirim output error JSON, tampilkan di sini.
if (!empty($output_from_db_connect)) {
    echo "❌ KONEKSI DATABASE GAGAL (OUTPUT DARI db_connect.php).\n\n";
    echo "Pesan yang diterima:\n";
    echo $output_from_db_connect . "\n\n";
    echo "Ini mengindikasikan kredensial (DB_USER, DB_PASS, DB_NAME) di file db_connect.php salah.";
}