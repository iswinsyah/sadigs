<?php
header('Content-Type: text/html; charset=utf-8');
echo "<h1>🕵️‍♂️ Cek Status Server & File</h1>";

// 1. Cek Lingkungan Server
echo "<p><strong>Software Server:</strong> " . $_SERVER['SERVER_SOFTWARE'] . "</p>";
echo "<p><strong>Lokasi Folder:</strong> " . __DIR__ . "</p>";

// 2. Cek Keberadaan File Penting
$files_to_check = [
    'debug_verification.php',
    'verification.php',
    'db_connect.php'
];

echo "<h3>Status File di Folder Ini:</h3><ul>";
foreach ($files_to_check as $file) {
    if (file_exists(__DIR__ . '/' . $file)) {
        echo "<li style='color:green'>✅ <strong>$file</strong> - DITEMUKAN (Aman)</li>";
    } else {
        echo "<li style='color:red'>❌ <strong>$file</strong> - TIDAK DITEMUKAN (Belum ter-upload)</li>";
    }
}
echo "</ul>";
echo "<p><em>Jika ada file bertanda ❌, silakan upload ulang file tersebut ke Hostinger.</em></p>";
?>