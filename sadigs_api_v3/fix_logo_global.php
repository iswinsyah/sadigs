<?php
// sadigs_api_v3/fix_logo_global.php
// Script untuk memaksa penggantian logo di SEMUA file HTML

$rootDir = __DIR__ . '/../'; // Folder utama sadigs
$files = glob($rootDir . '*.html'); // Ambil semua file .html

$oldUrl = 'https://raw.githubusercontent.com/villaqurankotabatu-commits/gambarvqbm/f44bf009884af11aba2c967a2401026225f7b02b/vila%20quran.png';
$newPath = 'assets/img/logo.png';

echo "<h1>Perbaikan Logo Global</h1>";
echo "<p>Sedang memindai file HTML di: <code>$rootDir</code></p><ul>";

$count = 0;
foreach ($files as $file) {
    $content = file_get_contents($file);
    $filename = basename($file);
    
    // Cek apakah file mengandung URL logo lama
    if (strpos($content, $oldUrl) !== false) {
        $newContent = str_replace($oldUrl, $newPath, $content);
        file_put_contents($file, $newContent);
        echo "<li>✅ Berhasil memperbaiki logo di: <strong>$filename</strong></li>";
        $count++;
    } else {
        echo "<li>⚪ $filename: Aman (tidak ditemukan link lama)</li>";
    }
}

echo "</ul>";
echo "<h3>Selesai. $count file diperbarui.</h3>";
echo "<p><strong>PENTING:</strong> Setelah ini, kembali ke aplikasi dan tekan <code>Ctrl + F5</code> (Hard Refresh) untuk melihat hasilnya.</p>";
?>