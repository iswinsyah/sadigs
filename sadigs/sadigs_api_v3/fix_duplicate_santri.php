<?php
require_once 'db_connect.php';

try {
    $pdo = getDBConnection();
    echo "<h1>Pembersihan Total Peran 'Santri'</h1>";

    // 1. Hapus role 'Santri' JIKA user tersebut SUDAH punya 'Santri Rijal' atau 'Santri Nisa'
    // (Menghindari duplikat sebelum migrasi)
    $sql_clean = "DELETE ur_general 
                  FROM user_roles ur_general
                  JOIN user_roles ur_specific ON ur_general.user_id = ur_specific.user_id
                  WHERE ur_general.role_name = 'Santri' 
                  AND ur_specific.role_name IN ('Santri Rijal', 'Santri Nisa\'')";
    
    $stmt = $pdo->prepare($sql_clean);
    $stmt->execute();
    $count_clean = $stmt->rowCount();
    echo "<p>✅ Menghapus <b>$count_clean</b> role 'Santri' yang redundan (karena user sudah punya peran spesifik).</p>";

    // 2. Migrasi 'Santri' yang tersisa (yang BELUM punya peran spesifik)
    // Berdasarkan Gender
    
    // A. Laki-laki -> Santri Rijal
    $sql_mig_l = "UPDATE user_roles ur
                  JOIN users u ON ur.user_id = u.user_id
                  SET ur.role_name = 'Santri Rijal'
                  WHERE ur.role_name = 'Santri' AND (u.gender = 'Laki-laki' OR u.gender = 'L')";
    $stmt_l = $pdo->prepare($sql_mig_l);
    $stmt_l->execute();
    $count_l = $stmt_l->rowCount();
    echo "<p>✅ Migrasi <b>$count_l</b> role 'Santri' (Laki-laki) menjadi 'Santri Rijal'.</p>";

    // B. Perempuan -> Santri Nisa'
    $sql_mig_p = "UPDATE user_roles ur
                  JOIN users u ON ur.user_id = u.user_id
                  SET ur.role_name = 'Santri Nisa\''
                  WHERE ur.role_name = 'Santri' AND (u.gender = 'Perempuan' OR u.gender = 'P')";
    $stmt_p = $pdo->prepare($sql_mig_p);
    $stmt_p->execute();
    $count_p = $stmt_p->rowCount();
    echo "<p>✅ Migrasi <b>$count_p</b> role 'Santri' (Perempuan) menjadi 'Santri Nisa''.</p>";

    // C. Sisanya (Gender tidak diketahui) -> Default ke Santri Rijal
    $sql_mig_def = "UPDATE user_roles SET role_name = 'Santri Rijal' WHERE role_name = 'Santri'";
    $stmt_def = $pdo->prepare($sql_mig_def);
    $stmt_def->execute();
    $count_def = $stmt_def->rowCount();
    if ($count_def > 0) {
        echo "<p>⚠️ Migrasi <b>$count_def</b> role 'Santri' (Gender tidak diketahui) menjadi 'Santri Rijal' (Default).</p>";
    }

    // 3. Hapus 'Santri' dari konfigurasi lain (Menu & Quota)
    $pdo->exec("DELETE FROM menu_permissions WHERE role_name = 'Santri'");
    $pdo->exec("DELETE FROM quota_settings WHERE role_name = 'Santri'");
    echo "<p>✅ Menghapus konfigurasi menu & kuota untuk role 'Santri'.</p>";

    echo "<h3>Selesai. Peran 'Santri' sudah sepenuhnya dihapus/digantikan.</h3>";
    echo "<p>Silakan refresh halaman Kelompok Mentoring.</p>";

} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
?>
