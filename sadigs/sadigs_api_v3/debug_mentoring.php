<?php
require_once 'db_connect.php';

try {
    $pdo = getDBConnection();
    echo "<h1>Diagnosa Data Mentoring</h1>";
    echo "<style>body{font-family:sans-serif; padding:20px;}</style>";

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

} catch (Exception $e) {
    echo "<h1>Error Sistem</h1><p>" . $e->getMessage() . "</p>";
}
?>