<?php
require_once 'db_connect.php';

try {
    $pdo = getDBConnection();
    echo "<h1>Pembersihan Data Peran Ganda</h1>";

    // 1. Hapus duplikat (Simpan ID terbesar/terbaru)
    // Logika: Hapus baris t1 jika ada baris t2 dengan user & role sama tapi ID t2 lebih besar
    // (Atau t1.id < t2.id untuk menyisakan yang terakhir, tergantung preferensi. Di sini kita sisakan salah satu saja).
    $sql = "DELETE t1 FROM user_roles t1
            INNER JOIN user_roles t2 
            WHERE 
                t1.id < t2.id AND 
                t1.user_id = t2.user_id AND 
                t1.role_name = t2.role_name";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    $count = $stmt->rowCount();
    
    echo "<p>✅ Berhasil menghapus <b>$count</b> data peran yang duplikat/berulang.</p>";

    // 2. Pasang Pengaman Permanen (Unique Key)
    try {
        $pdo->exec("ALTER TABLE user_roles ADD UNIQUE KEY unique_user_role (user_id, role_name)");
        echo "<p>✅ Pengaman database (Unique Key) berhasil dipasang. Duplikasi tidak akan terjadi lagi.</p>";
    } catch (Exception $e) {
        echo "<p>ℹ️ Pengaman database sudah terpasang.</p>";
    }

    echo "<h3>Selesai. Silakan tutup halaman ini dan refresh Data Akun Pengguna.</h3>";

} catch (Exception $e) {
    echo "<h3 style='color:red'>Error: " . $e->getMessage() . "</h3>";
}
?>