<?php
require_once 'db_connect.php';

try {
    $pdo = getDBConnection();
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $role_to_remove = 'Santri';

    echo "<h1>Pembersihan Role '{$role_to_remove}'</h1>";

    // 1. Hapus dari menu_permissions
    $stmt1 = $pdo->prepare("DELETE FROM menu_permissions WHERE role_name = ?");
    $count1 = $stmt1->execute([$role_to_remove]) ? $stmt1->rowCount() : 0;
    echo "<p style='color:green;'>✅ Dihapus {$count1} entri dari `menu_permissions` untuk peran '{$role_to_remove}'.</p>";

    // 2. Hapus dari user_roles
    $stmt2 = $pdo->prepare("DELETE FROM user_roles WHERE role_name = ?");
    $count2 = $stmt2->execute([$role_to_remove]) ? $stmt2->rowCount() : 0;
    echo "<p style='color:green;'>✅ Dihapus {$count2} entri dari `user_roles` untuk peran '{$role_to_remove}'.</p>";
    
    // 3. Hapus dari quota_settings
    $stmt3 = $pdo->prepare("DELETE FROM quota_settings WHERE role_name = ?");
    $count3 = $stmt3->execute([$role_to_remove]) ? $stmt3->rowCount() : 0;
    echo "<p style='color:green;'>✅ Dihapus {$count3} entri dari `quota_settings` untuk peran '{$role_to_remove}'.</p>";

    echo "<h3>Pembersihan Selesai. Role '{$role_to_remove}' telah dihapus dari sistem.</h3>";

} catch(PDOException $e) {
    die("ERROR: " . $e->getMessage());
}
?>