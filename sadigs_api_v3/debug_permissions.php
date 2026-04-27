<?php
header('Content-Type: text/html; charset=utf-8');
require_once 'db_connect.php';

echo "<!DOCTYPE html><html><head><title>Debug Permissions</title><style>body{font-family: sans-serif; padding: 20px; line-height: 1.6;} h1,h2{color: #26667F;} code{background: #f1f1f1; padding: 2px 5px; border-radius: 4px;}</style></head><body>";
echo "<h1>Alat Diagnosa Hak Akses Menu</h1>";

try {
    $pdo = getDBConnection();

    // Form untuk memilih role
    $roles = $pdo->query("SELECT DISTINCT role_name FROM user_roles ORDER BY role_name ASC")->fetchAll(PDO::FETCH_COLUMN);
    echo '<form method="GET">';
    echo '<label for="role">Pilih Peran untuk Diperiksa:</label> ';
    echo '<select name="role" id="role" onchange="this.form.submit()">';
    echo '<option value="">-- Pilih Peran --</option>';
    foreach ($roles as $r) {
        $selected = (isset($_GET['role']) && $_GET['role'] === $r) ? 'selected' : '';
        echo "<option value=\"".htmlspecialchars($r)."\" $selected>".htmlspecialchars($r)."</option>";
    }
    echo '</select>';
    echo '<noscript><input type="submit" value="Periksa"></noscript>';
    echo '</form><hr>';


    if (isset($_GET['role']) && !empty($_GET['role'])) {
        $role_to_check = $_GET['role'];
        echo "<h2>Hasil untuk Peran: <code>" . htmlspecialchars($role_to_check) . "</code></h2>";

        $stmt = $pdo->prepare("SELECT menu_id FROM menu_permissions WHERE role_name = ? AND can_view = 1 ORDER BY menu_id ASC");
        $stmt->execute([$role_to_check]);
        $permissions = $stmt->fetchAll(PDO::FETCH_COLUMN);

        if (count($permissions) > 0) {
            echo "<p>Peran <strong>" . htmlspecialchars($role_to_check) . "</strong> memiliki akses ke menu berikut:</p>";
            echo "<ul>";
            foreach ($permissions as $menu_id) {
                $is_target = ($menu_id === 'navRekapPembayaran');
                $style = $is_target ? 'style="background-color: yellow; font-weight: bold;"' : '';
                echo "<li $style><code>" . htmlspecialchars($menu_id) . "</code></li>";
            }
            echo "</ul>";

            if (in_array('navRekapPembayaran', $permissions)) {
                echo "<p style='color:green; font-weight:bold;'>✅ KESIMPULAN: Pengaturan di database SUDAH BENAR. Peran ini seharusnya bisa mengakses 'Rekap Keuangan'. Jika masih gagal, masalahnya 99% ada di cache browser atau cache server. Lakukan Hard Refresh (Ctrl+F5) atau clear cache browser Anda.</p>";
            } else {
                echo "<p style='color:red; font-weight:bold;'>❌ KESIMPULAN: Pengaturan di database SALAH. Peran ini TIDAK memiliki izin untuk <code>navRekapPembayaran</code>. Silakan buka halaman 'Manajemen Akses', centang kotak untuk 'Rekap Keuangan' pada kolom 'Kepala Sekolah', lalu klik 'Simpan Perubahan'.</p>";
            }

        } else {
            echo "<p style='color:red;'>Peran <strong>" . htmlspecialchars($role_to_check) . "</strong> tidak memiliki akses ke menu manapun.</p>";
        }
    } else {
        echo "<p>Silakan pilih peran dari daftar di atas untuk melihat hak aksesnya.</p>";
    }

} catch (Exception $e) {
    echo "<h2>Error</h2>";
    echo "<p style='color:red;'>Gagal terhubung atau menjalankan query: " . $e->getMessage() . "</p>";
}

echo "</body></html>";
?>