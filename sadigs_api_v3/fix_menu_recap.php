<?php
require_once 'db_connect.php';

try {
    $pdo = getDBConnection();
    echo "<h1>Perbaikan Menu Rekap Ibadah (Fix Link)</h1>";

    // Update link menu agar mengarah ke halaman pemilihan santri
    $sql = "INSERT INTO menus (menu_id, menu_name, category_id, icon, link) 
            VALUES ('navRekapIbadahAnak', 'Rekap Ibadah Anak', 'Walisantri', 'clipboard-check', 'select_student.html') 
            ON DUPLICATE KEY UPDATE link = 'select_student.html'";
    
    $pdo->exec($sql);
    echo "<p style='color:green'>✅ Berhasil! Menu 'Rekap Ibadah Anak' sekarang mengarah ke halaman Pilih Santri.</p>";
    echo "<p>Silakan kembali ke Dashboard dan refresh.</p>";

} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
?>