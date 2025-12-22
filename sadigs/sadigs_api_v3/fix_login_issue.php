<?php
require_once 'db_connect.php';

try {
    $pdo = getDBConnection();
    
    // Aktifkan semua user yang masih non-aktif (is_active = 0)
    // Ini memperbaiki akun yang baru saja Anda daftarkan tapi gagal login
    $sql = "UPDATE users SET is_active = 1 WHERE is_active = 0";
    $stmt = $pdo->query($sql);
    $count = $stmt->rowCount();
    
    echo "<h1>Perbaikan Selesai</h1><p>Berhasil mengaktifkan <strong>$count</strong> akun yang terkunci. Silakan coba login kembali.</p>";
    
} catch (PDOException $e) {
    echo "<h1>Gagal</h1><p>Error: " . $e->getMessage() . "</p>";
}
?>