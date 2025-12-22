<?php
require_once 'db_connect.php';

try {
    $pdo = getDBConnection();
    echo "<h1>Diagnosa Data Mentoring (Lanjutan)</h1>";
    echo "<style>body{font-family:sans-serif; padding:20px;} table{border-collapse:collapse; width:100%; margin-top:10px;} th,td{border:1px solid #ddd; padding:8px; text-align:left;} th{background-color:#f2f2f2;}</style>";

    // 1. Cek Koneksi & Tabel
    echo "<h3>1. Cek Tabel Database</h3>";
    try {
        $pdo->query("SELECT 1 FROM mentoring_assignments LIMIT 1");
        echo "<p style='color:green'>✅ Tabel 'mentoring_assignments' ditemukan.</p>";
    } catch (Exception $e) {
        echo "<p style='color:red'>❌ Tabel 'mentoring_assignments' TIDAK ditemukan. Jalankan update_schema_v7.php dulu.</p>";
    }

    // 2. Cek Data Santri
    echo "<h3>2. Cek Data Santri</h3>";
    $santriTotal = $pdo->query("SELECT count(*) FROM user_roles WHERE role_name = 'Santri'")->fetchColumn();
    $santriAppr = $pdo->query("SELECT count(*) FROM user_roles WHERE role_name = 'Santri' AND status = 'approved'")->fetchColumn();
    
    echo "<ul>";
    echo "<li>Total User dengan role 'Santri': <strong>$santriTotal</strong></li>";
    echo "<li>Santri dengan status 'approved': <strong>$santriAppr</strong> " . ($santriAppr == 0 ? "<span style='color:red'>(Penyebab tabel kosong!)</span>" : "<span style='color:green'>(OK)</span>") . "</li>";
    echo "</ul>";

    // 3. Cek Data Musyrif
    echo "<h3>3. Cek Data Musyrif</h3>";
    $musyrifTotal = $pdo->query("SELECT count(*) FROM user_roles WHERE role_name = 'Musyrif'")->fetchColumn();
    $musyrifAppr = $pdo->query("SELECT count(*) FROM user_roles WHERE role_name = 'Musyrif' AND status = 'approved'")->fetchColumn();
    
    echo "<ul>";
    echo "<li>Total User dengan role 'Musyrif': <strong>$musyrifTotal</strong></li>";
    echo "<li>Musyrif dengan status 'approved': <strong>$musyrifAppr</strong></li>";
    echo "</ul>";

    if ($santriAppr == 0) {
        echo "<div style='background:#ffebee; padding:15px; border-left:5px solid red; margin-top:20px;'>";
        echo "<strong>Diagnosa:</strong> Tabel kosong karena belum ada Santri yang berstatus <em>approved</em>.<br>";
        echo "<strong>Solusi:</strong> Silakan login sebagai Admin/Yayasan, buka menu <strong>Verifikasi Pegawai</strong> (atau manajemen user), dan setujui akun Santri yang mendaftar.";
        echo "</div>";
    }

    // 4. Cek Konsistensi Data (JOIN)
    echo "<h3>4. Cek Konsistensi Data (JOIN users & user_roles)</h3>";
    
    // Cek apakah ada user_id di user_roles yang tidak ada di users
    $sqlOrphan = "SELECT ur.user_id, ur.role_name 
                  FROM user_roles ur 
                  LEFT JOIN users u ON ur.user_id = u.user_id 
                  WHERE u.user_id IS NULL AND ur.role_name = 'Santri'";
    $orphans = $pdo->query($sqlOrphan)->fetchAll(PDO::FETCH_ASSOC);
    
    if (count($orphans) > 0) {
        echo "<div style='background:#ffebee; padding:15px; border-left:5px solid red; margin-bottom:20px;'>";
        echo "<strong>MASALAH KRITIS DITEMUKAN:</strong> Ada " . count($orphans) . " data role 'Santri' yang tidak memiliki data user induk (Orphaned).<br>";
        echo "Ini menyebabkan data tidak muncul di aplikasi karena query menggunakan JOIN.<br>";
        echo "ID yang bermasalah: " . implode(", ", array_column($orphans, 'user_id'));
        echo "</div>";
    } else {
        echo "<p style='color:green'>✅ Tidak ada data role yang orphaned (semua role punya user).</p>";
    }

    // 5. Simulasi Query API
    echo "<h3>5. Simulasi Query API (Hasil Akhir)</h3>";
    $sqlApi = "SELECT 
                s.user_id,
                s.username,
                s.full_name,
                ur.role_name,
                ur.status
            FROM users s
            JOIN user_roles ur ON s.user_id = ur.user_id
            WHERE ur.role_name = 'Santri' AND ur.status = 'approved'
            LIMIT 5";
    $stmt = $pdo->query($sqlApi);
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (count($results) > 0) {
        echo "<p style='color:green'>✅ Query API berhasil mengambil data (" . count($results) . " sampel ditampilkan):</p>";
        echo "<table><thead><tr><th>User ID</th><th>Username</th><th>Full Name</th><th>Role</th><th>Status</th></tr></thead><tbody>";
        foreach ($results as $row) {
            echo "<tr><td>{$row['user_id']}</td><td>{$row['username']}</td><td>{$row['full_name']}</td><td>{$row['role_name']}</td><td>{$row['status']}</td></tr>";
        }
        echo "</tbody></table>";
    } else {
        echo "<p style='color:red'>❌ Query API mengembalikan 0 baris. Padahal di langkah 2 terdeteksi ada data approved.</p>";
        echo "<p><strong>Kesimpulan:</strong> Masalah ada pada ketidakcocokan ID antara tabel <code>users</code> dan <code>user_roles</code>.</p>";
    }

} catch (Exception $e) {
    echo "<h1>Error Sistem</h1><p>" . $e->getMessage() . "</p>";
}
?>