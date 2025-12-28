<?php
// Paksa tampilkan error
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Paksa header HTML
header('Content-Type: text/html; charset=utf-8');

echo "<h1>Diagnosa Awal</h1>";
echo "<p>Script berhasil dimuat. Mencoba menghubungkan database...</p>";

if (!file_exists(__DIR__ . '/db_connect.php')) {
    die("<h2 style='color:red'>Error: File db_connect.php tidak ditemukan!</h2><p>Kemungkinan file ini tidak ter-upload karena masuk dalam .gitignore. Silakan upload manual via File Manager Hostinger.</p>");
}

require_once 'db_connect.php';

try {
    if (!function_exists('getDBConnection')) {
        die("<h2 style='color:red'>Error: Fungsi getDBConnection() tidak ditemukan di db_connect.php</h2>");
    }

    $pdo = getDBConnection();
    echo "<p>✅ Koneksi Database Berhasil.</p>";
    
    // 1. Update semua user yang is_active = 0 atau NULL menjadi 1
    $sql = "UPDATE users SET is_active = 1 WHERE is_active = 0 OR is_active IS NULL";
    $stmt = $pdo->query($sql);
    $count = $stmt->rowCount();
    
    echo "<h1>Laporan Perbaikan Akun</h1>";
    echo "<p>Jumlah akun yang berhasil diaktifkan: <strong>$count</strong></p>";
    
    echo "<h2 style='color:green'>SUKSES! Database telah diperbarui.</h2>";
    echo "<p>Silakan kembali ke halaman Login dan coba masuk.</p>";

    // Tampilkan daftar user untuk verifikasi visual
    echo "<hr><h3>Daftar User di Database Saat Ini:</h3>";
    $users = $pdo->query("SELECT user_id, username, is_active FROM users")->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<table border='1' cellpadding='5' style='border-collapse:collapse;'><tr><th>ID</th><th>Username</th><th>Status (is_active)</th></tr>";
    foreach($users as $u) {
        $status = ($u['is_active'] == 1) ? '<span style="color:green;font-weight:bold">Aktif (1)</span>' : '<span style="color:red;font-weight:bold">Non-Aktif ('.$u['is_active'].')</span>';
        echo "<tr><td>{$u['user_id']}</td><td>{$u['username']}</td><td>$status</td></tr>";
    }
    echo "</table>";
    
} catch (PDOException $e) {
    echo "<h1>Gagal Koneksi/Query</h1><p>Error: " . $e->getMessage() . "</p>";
} catch (Exception $e) {
    echo "<h1>Error Lain</h1><p>" . $e->getMessage() . "</p>";
}
?>
