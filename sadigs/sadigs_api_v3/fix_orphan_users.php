<?php
// Script untuk mereset user yang 'nyangkut' (Aktif tapi tanpa peran)
require_once 'db_connect.php';

try {
    $pdo = getDBConnection();
    echo "<h1>Perbaikan User 'Nyangkut'</h1>";
    
    // Cari user yang aktif tapi tidak punya role
    $sql = "SELECT u.user_id, u.username 
            FROM users u 
            LEFT JOIN user_roles ur ON u.user_id = ur.user_id 
            WHERE ur.role_name IS NULL AND u.is_active = 1";
    
    $stmt = $pdo->query($sql);
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (count($users) > 0) {
        echo "<p>Ditemukan " . count($users) . " user yang aktif tapi tanpa peran:</p><ul>";
        foreach($users as $u) {
            echo "<li>" . htmlspecialchars($u['username']) . "</li>";
        }
        echo "</ul>";
        
        // Reset mereka jadi non-aktif agar muncul di verifikasi
        $pdo->exec("UPDATE users u 
                    LEFT JOIN user_roles ur ON u.user_id = ur.user_id 
                    SET u.is_active = 0 
                    WHERE ur.role_name IS NULL AND u.is_active = 1");
                    
        echo "<h3 style='color:green'>✅ Berhasil di-reset!</h3>";
        echo "<p>User-user tersebut sekarang sudah Non-Aktif dan seharusnya <strong>muncul kembali</strong> di halaman Verifikasi Pengguna.</p>";
    } else {
        echo "<h3 style='color:blue'>ℹ️ Tidak ada user yang bermasalah (Aktif tanpa peran).</h3>";
        echo "<p>Silakan cek <strong>debug_verification.php</strong> untuk diagnosa lebih lanjut.</p>";
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
?>