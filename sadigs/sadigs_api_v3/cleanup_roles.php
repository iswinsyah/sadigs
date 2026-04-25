<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
require_once 'db_connect.php';

try {
    $pdo = getDBConnection();
    
    // 1. Hapus duplikat peran yang sama persis (Sisakan 1 saja)
    $sql = "DELETE t1 FROM user_roles t1
            INNER JOIN user_roles t2 
            WHERE t1.id > t2.id 
              AND t1.user_id = t2.user_id 
              AND t1.role_name = t2.role_name";
    $deleted = $pdo->exec($sql);

    // 2. Kunci tabel agar tidak bisa ganda lagi ke depannya
    try { $pdo->exec("ALTER TABLE user_roles ADD UNIQUE KEY unique_user_role (user_id, role_name)"); } catch (Exception $e) {}

    echo "<h3 style='color:green'>✅ Sukses! Berhasil membersihkan $deleted data peran yang menumpuk di database.</h3>";
} catch (Exception $e) {
    echo "<h3 style='color:red'>❌ Error: " . $e->getMessage() . "</h3>";
}
?>