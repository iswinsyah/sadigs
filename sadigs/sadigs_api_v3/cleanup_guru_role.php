<?php
require_once 'db_connect.php';

try {
    $pdo = getDBConnection();
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    echo "<h1>Pembersihan Role 'Guru'</h1>";

    // 1. Hapus dari menu_permissions
    $stmt1 = $pdo->prepare("DELETE FROM menu_permissions WHERE role_name = 'Guru'");
    $count1 = $stmt1->execute() ? $stmt1->rowCount() : 0;
    echo "<p style='color:green;'>✅ Dihapus $count1 entri dari `menu_permissions` untuk peran 'Guru'.</p>";

    // 2. Hapus dari user_roles (jika ada user yang terlanjur diberi peran ini)
    $stmt2 = $pdo->prepare("DELETE FROM user_roles WHERE role_name = 'Guru'");
    $count2 = $stmt2->execute() ? $stmt2->rowCount() : 0;
    echo "<p style='color:green;'>✅ Dihapus $count2 entri dari `user_roles` untuk peran 'Guru'.</p>";
    
    // 3. Hapus dari quotas (jika ada)
    $stmt3 = $pdo->prepare("DELETE FROM quotas WHERE role_name = 'Guru'");
    $count3 = $stmt3->execute() ? $stmt3->rowCount() : 0;
    echo "<p style='color:green;'>✅ Dihapus $count3 entri dari `quotas` untuk peran 'Guru'.</p>";

    echo "<h3>Pembersihan Selesai. Role 'Guru' telah dihapus dari sistem. Silakan refresh halaman Manajemen Akses.</h3>";

} catch(PDOException $e) {
    die("ERROR: " . $e->getMessage());
}
?>