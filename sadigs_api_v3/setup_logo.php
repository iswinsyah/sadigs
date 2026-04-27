<?php
// sadigs_api_v3/setup_logo.php
// Script bantu untuk menyiapkan folder aset dan mengganti link logo lama

$rootDir = __DIR__ . '/../'; // Folder sadigs utama
$assetsDir = $rootDir . 'assets/img/';

echo "<h1>Setup Logo Sekolah</h1>";

// 1. Buat Folder jika belum ada
if (!file_exists($assetsDir)) {
    if (mkdir($assetsDir, 0777, true)) {
        echo "<p style='color:green'>✅ Folder <code>assets/img</code> berhasil dibuat.</p>";
    } else {
        echo "<p style='color:red'>❌ Gagal membuat folder. Silakan buat folder <code>assets/img</code> secara manual di dalam folder <code>sadigs</code>.</p>";
    }
} else {
    echo "<p style='color:blue'>ℹ️ Folder <code>assets/img</code> sudah ada.</p>";
}

// 2. Update Link di semua file HTML
$oldUrl = 'https://raw.githubusercontent.com/villaqurankotabatu-commits/gambarvqbm/f44bf009884af11aba2c967a2401026225f7b02b/vila%20quran.png';
$newPath = 'assets/img/logo.png';

$files = glob($rootDir . '*.html');
$count = 0;

echo "<h3>Memperbarui Link Logo di File HTML...</h3><ul>";

foreach ($files as $file) {
    $content = file_get_contents($file);
    
    // Cek keberadaan URL lama
    if (strpos($content, $oldUrl) !== false) {
        $newContent = str_replace($oldUrl, $newPath, $content);
        file_put_contents($file, $newContent);
        echo "<li>✅ Diperbarui: <strong>" . basename($file) . "</strong></li>";
        $count++;
    }
}

echo "</ul>";
echo "<div style='background:#f0f9ff; padding:15px; border:1px solid #bae6fd; border-radius:8px; margin-top:20px;'>";
echo "<strong>LANGKAH SELANJUTNYA (WAJIB):</strong><br>";
echo "1. Siapkan file logo resmi sekolah Anda.<br>";
echo "2. Ubah nama filenya menjadi <strong>logo.png</strong>.<br>";
echo "3. Simpan file tersebut ke dalam folder: <code>" . realpath($assetsDir) . "</code><br>";
echo "4. Refresh aplikasi Anda.";
echo "</div>";
?>