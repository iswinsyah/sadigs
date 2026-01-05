<?php
header('Content-Type: text/plain; charset=utf-8');

echo "Memulai proses pembersihan file debug...\n\n";

$files_to_delete = [
    __DIR__ . '/debug_biodata.php',
    __DIR__ . '/debug_employee_save.log',
    __DIR__ . '/test_write.php',
    __DIR__ . '/test_write.log',
    __DIR__ . '/test_curl_google.php',
    __DIR__ . '/test_curl_google.log',
    __DIR__ . '/test_employee.log',
    __DIR__ . '/employee_data_test.php',
    __DIR__ . '/../test_form_debug.html',
];

foreach ($files_to_delete as $file) {
    if (file_exists($file)) {
        if (unlink($file)) {
            echo "✅ Dihapus: " . basename($file) . "\n";
        } else {
            echo "❌ Gagal menghapus: " . basename($file) . " (Periksa izin file).\n";
        }
    } else {
        echo "ℹ️ Tidak ditemukan (sudah bersih): " . basename($file) . "\n";
    }
}

echo "\nProses pembersihan selesai.\n";
?>