<?php
// File: test_curl_google.php
// Purpose: To test if the server can make outgoing cURL connections to Google's API endpoint.

// Hide errors from the browser output, we will log them.
ini_set('display_errors', 0);
error_reporting(0);

header('Content-Type: text/plain; charset=utf-8');

$logFile = __DIR__ . '/test_curl_google.log';
$endpoint = "https://generativelanguage.googleapis.com";

echo "Mencoba koneksi cURL ke: $endpoint\n";

$ch = curl_init($endpoint);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10); // Timeout 10 detik
curl_setopt($ch, CURLOPT_TIMEOUT, 20); // Total timeout 20 detik
curl_setopt($ch, CURLOPT_HEADER, true); 
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);

$response = curl_exec($ch);
$error = curl_error($ch);
$info = curl_getinfo($ch);
curl_close($ch);

$logContent = "--- Log Waktu: " . date('c') . " ---\n\n";
$logContent .= "INFO KONEKSI:\n" . print_r($info, true) . "\n\n";
$logContent .= "PESAN ERROR (jika ada):\n" . print_r($error, true) . "\n\n";
$logContent .= "RESPONS DARI SERVER (termasuk header):\n" . print_r($response, true) . "\n\n";

$bytesWritten = @file_put_contents($logFile, $logContent, FILE_APPEND);

if ($bytesWritten === false) {
    echo "\nHASIL: GAGAL MENULIS LOG.\n";
    echo "Pastikan file 'test_curl_google.log' sudah dibuat dan memiliki izin tulis (permission 666).\n";
} else {
    echo "\nHASIL: TES SELESAI.\n";
    echo "Hasil tes telah ditulis ke dalam file 'test_curl_google.log'.\n";
    echo "Silakan kirimkan isi dari file log tersebut ke asisten Anda untuk dianalisis.\n";
}

if (!empty($error)) {
    echo "\n\nPERINGATAN: Ditemukan error saat koneksi cURL!\n";
    echo "Error: $error\n";
    echo "Ini mengindikasikan koneksi keluar dari server Anda ke Google diblokir.\n";
} elseif (isset($info['http_code']) && $info['http_code'] != 0 && $info['http_code'] < 400) {
    echo "\n\nINFO: Koneksi cURL sepertinya BERHASIL (HTTP Code: {$info['http_code']}).\n";
    echo "Ini berarti server Anda bisa terhubung ke Google. Masalahnya kemungkinan besar ada pada detail request (API Key, format data, dll).\n";
} elseif (isset($info['http_code'])) {
    echo "\n\nPERINGATAN: Koneksi cURL gagal dengan HTTP Code: {$info['http_code']}.\n";
    echo "Ini bisa berarti koneksi diblokir atau ada masalah lain.\n";
}
?>