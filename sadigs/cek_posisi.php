<?php
header('Content-Type: text/plain');
echo "=== DIAGNOSA POSISI FILE ===\n";
echo "Lokasi script ini: " . __DIR__ . "\n";

$target_folder = 'sadigs_api_v3';
$target_file = 'setup_payment_table.php';
$full_path = __DIR__ . DIRECTORY_SEPARATOR . $target_folder . DIRECTORY_SEPARATOR . $target_file;

echo "Mencari file target...\n";
echo "Path: $full_path\n\n";

if (file_exists($full_path)) {
    echo "[BERHASIL] File DITEMUKAN!\n";
    echo "URL yang benar seharusnya:\n";
    // Estimasi URL
    $protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http");
    $url = "$protocol://$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]";
    echo str_replace('cek_posisi.php', $target_folder . '/' . $target_file, $url);
} else {
    echo "[GAGAL] File TIDAK DITEMUKAN di lokasi tersebut.\n";
    
    if (is_dir(__DIR__ . DIRECTORY_SEPARATOR . $target_folder)) {
        echo "\nIsi folder $target_folder:\n";
        print_r(scandir(__DIR__ . DIRECTORY_SEPARATOR . $target_folder));
    } else {
        echo "\nFolder '$target_folder' juga tidak ditemukan di sini.\n";
    }
}
?>