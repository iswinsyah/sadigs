<?php
require_once 'db_connect.php';

echo "<!DOCTYPE html><html><head><title>Diagnosa Biodata Pegawai</title><style>body{font-family: sans-serif; padding: 20px; line-height: 1.6;} h1{color: #26667F;} .ok{color:green; font-weight:bold;} .err{color:red; font-weight:bold;} .info{background:#eef; padding:15px; border-left: 5px solid #66f;}</style></head><body>";
echo "<h1>Alat Diagnosa Tabel Biodata Pegawai</h1>";

try {
    $pdo = getDBConnection();
    echo "<p class='ok'>✅ Koneksi Database Berhasil.</p><hr>";

    // Cek tabel employee_details
    $stmt_table = $pdo->query("SHOW TABLES LIKE 'employee_details'");
    if ($stmt_table->rowCount() > 0) {
        echo "<p class='ok'>✅ Tabel `employee_details` ditemukan.</p>";

        // Cek kolom npwp
        $stmt_column = $pdo->query("SHOW COLUMNS FROM `employee_details` LIKE 'npwp'");
        if ($stmt_column->rowCount() > 0) {
            echo "<p class='ok'>✅ Kolom `npwp` ditemukan di dalam tabel.</p>";
            echo "<div class='info'><strong>KESIMPULAN:</strong> Struktur database Anda sudah benar. Jika masih gagal, masalahnya kemungkinan ada pada cache server. Coba bersihkan cache di Hostinger atau tunggu beberapa saat.</div>";
        } else {
            echo "<p class='err'>❌ MASALAH DITEMUKAN: Kolom `npwp` TIDAK ADA di dalam tabel `employee_details`.</p>";
            echo "<div class='info'><strong>SOLUSI:</strong> Ini adalah penyebab utama kenapa data NPWP gagal disimpan. Silakan buka tautan di bawah ini untuk menambahkan kolom NPWP ke database Anda.<br/><br/>";
            echo "<a href='update_schema_v21.php' target='_blank' style='background:orange; color:white; padding:10px 15px; text-decoration:none; border-radius:5px;'>Jalankan Perbaikan Database (update_schema_v21.php)</a></div>";
        }
    } else {
        echo "<p class='err'>❌ MASALAH KRITIS: Tabel `employee_details` TIDAK DITEMUKAN.</p>";
    }

} catch (Exception $e) {
    echo "<h2>Error</h2>";
    echo "<p class='err'>Gagal terhubung atau menjalankan query: " . $e->getMessage() . "</p>";
}

echo "</body></html>";
?>