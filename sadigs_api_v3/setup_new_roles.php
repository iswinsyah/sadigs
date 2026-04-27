<?php
require_once 'db_connect.php';

try {
    $pdo = getDBConnection();
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    echo "<h1>Penambahan Role Baru (Musyrifah & Ustadzah)</h1>";

    $new_roles = ['Musyrifah', 'Ustadzah'];

    $stmt_check = $pdo->prepare("SELECT COUNT(*) FROM quota_settings WHERE role_name = ?");
    $stmt_insert = $pdo->prepare("INSERT INTO quota_settings (role_name, max_limit) VALUES (?, 0)");

    foreach ($new_roles as $role) {
        $stmt_check->execute([$role]);
        if ($stmt_check->fetchColumn() == 0) {
            $stmt_insert->execute([$role]);
            echo "<p style='color:green;'>✅ Role '{$role}' berhasil ditambahkan ke `quota_settings`.</p>";
        } else {
            echo "<p style='color:blue;'>ℹ️ Role '{$role}' sudah ada di `quota_settings`.</p>";
        }
    }

    echo "<h3>Selesai. Peran baru sekarang tersedia di halaman Pengaturan Kuota dan Manajemen Akses.</h3>";

} catch(PDOException $e) {
    die("ERROR: " . $e->getMessage());
}
?>