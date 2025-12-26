<?php
require_once 'db_connect.php';

try {
    $pdo = getDBConnection();
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    echo "<h1>Penambahan Role Kepala Asrama Putra & Putri</h1>";

    $new_roles = [
        'Kepala Asrama Putra' => 1, // Kuota 1 by default
        'Kepala Asrama Putri' => 1, // Kuota 1 by default
    ];
    $old_role_to_remove = 'Kepala Ma\'had';

    $pdo->beginTransaction();

    // Hapus role lama dari quota_settings
    $stmt_delete = $pdo->prepare("DELETE FROM quota_settings WHERE role_name = ?");
    if ($stmt_delete->execute([$old_role_to_remove])) {
        echo "<p>✅ Peran lama '{$old_role_to_remove}' berhasil dihapus dari `quota_settings`.</p>";
    }

    // Tambahkan peran baru
    $stmt_insert = $pdo->prepare("INSERT INTO quota_settings (role_name, max_limit) VALUES (?, ?) ON DUPLICATE KEY UPDATE max_limit=VALUES(max_limit)");
    foreach ($new_roles as $role => $limit) {
        $stmt_insert->execute([$role, $limit]);
        echo "<p style='color:green;'>✅ Peran '{$role}' berhasil ditambahkan/diperbarui di `quota_settings`.</p>";
    }
    
    $pdo->commit();
    echo "<h3>Setup Selesai.</h3>";
    echo "<p>Silakan periksa halaman 'Atur Kuota' untuk melihat perubahan.</p>";
    echo "<p style='color:orange; font-weight:bold;'>PENTING: Jika ada pengguna dengan peran 'Kepala Ma'had', harap perbarui perannya secara manual ke 'Kepala Asrama Putra' atau 'Kepala Asrama Putri' melalui halaman Profil.</p>";


} catch(PDOException $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    die("ERROR: " . $e->getMessage());
}
?>