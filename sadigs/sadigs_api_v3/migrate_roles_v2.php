<?php
require_once 'db_connect.php';

try {
    $pdo = getDBConnection();
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    echo "<h1>Migrasi Peran v2</h1>";

    $renames = [
        'Kepala Asrama' => "Kepala Ma'had",
        'Kepala Asrama Putra' => "Kepala Ma'had",
        'Kepala Asrama Putri' => "Kepala Ma'had",
        'Santri' => 'Santri Rijal',
    ];

    $new_roles = [
        'Santri Nisa\'' => 0, // Kuota 0 by default
    ];

    $tables = ['user_roles', 'menu_permissions', 'quota_settings'];

    $pdo->beginTransaction();

    // Lakukan penggantian nama
    foreach ($renames as $old_name => $new_name) {
        foreach ($tables as $table) {
            // Gunakan IGNORE untuk update di `quota_settings` untuk menghindari duplicate key error jika 'Kepala Ma'had' sudah ada
            $update_sql = "UPDATE IGNORE {$table} SET role_name = ? WHERE role_name = ?";
            $stmt = $pdo->prepare($update_sql);
            $stmt->execute([$new_name, $old_name]);
            if ($stmt->rowCount() > 0) {
                echo "<p>✅ Di tabel '{$table}', peran '{$old_name}' diubah menjadi '{$new_name}'.</p>";
            }
        }
        // Hapus entri lama yang mungkin tersisa (jika IGNORE digunakan)
        $pdo->prepare("DELETE FROM quota_settings WHERE role_name = ?")->execute([$old_name]);
    }

    // Tambahkan peran baru
    $stmt_insert = $pdo->prepare("INSERT IGNORE INTO quota_settings (role_name, max_limit) VALUES (?, ?)");
    foreach ($new_roles as $role => $limit) {
        $stmt_insert->execute([$role, $limit]);
        if ($stmt_insert->rowCount() > 0) {
            echo "<p style='color:green;'>✅ Peran baru '{$role}' berhasil ditambahkan.</p>";
        } else {
            echo "<p style='color:blue;'>ℹ️ Peran '{$role}' sudah ada.</p>";
        }
    }

    $pdo->commit();
    echo "<h3>Migrasi Selesai.</h3>";

} catch(PDOException $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    die("ERROR: " . $e->getMessage());
}
?>