<?php
// File: test_write.php
// Purpose: To test if the server can execute a simple PHP script and write to a file.

header('Content-Type: text/plain; charset=utf-8');

$logFile = __DIR__ . '/test_write.log';
$timestamp = date('Y-m-d H:i:s');
$content = "SUCCESS: The test_write.php script was executed at $timestamp.\n";

echo "Mencoba menulis ke file log: $logFile\n\n";

// The @ symbol suppresses warnings if the write fails, we'll check manually.
$bytesWritten = @file_put_contents($logFile, $content, FILE_APPEND);

if ($bytesWritten === false) {
    echo "HASIL: GAGAL.\n";
    echo "Skrip tidak dapat menulis ke file log.\n\n";
    echo "PENYEBAB MUNGKIN:\n";
    echo "1. File 'test_write.log' belum dibuat secara manual di folder 'sadigs_api_v3'.\n";
    echo "2. File 'test_write.log' tidak memiliki izin (permission) 666.\n";
    echo "3. Ada konfigurasi keamanan server yang sangat ketat.\n\n";
    echo "AKSI: Pastikan file log ada dan izinnya benar. Jika masih gagal, hubungi support Hostinger dengan menunjukkan hasil ini.";
} else {
    echo "HASIL: SUKSES!\n";
    echo "$bytesWritten bytes berhasil ditulis ke file log.\n\n";
    echo "Ini berarti izin penulisan file di server Anda sudah benar.\n";
    echo "Masalahnya spesifik pada pemblokiran request ke 'employee_data.php', kemungkinan oleh aturan keamanan server (mod_security).";
}
?>