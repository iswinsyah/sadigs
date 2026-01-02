<?php
// SADIGS Tool: Reset Permissions
header('Content-Type: text/html; charset=utf-8');
require_once 'db_connect.php';

echo "<!DOCTYPE html><html><head><title>Reset Izin Akses - READY</title><style>body{font-family: sans-serif; padding: 20px; line-height: 1.6;} h1{color: #26667F;} code{background: #f1f1f1; padding: 2px 5px; border-radius: 4px;} .ok{color:green;} .err{color:red;}</style></head><body>";
echo "<h1>Alat Reset Izin Akses Menu</h1>";

try {
    $pdo = getDBConnection();

    // 1. Pastikan tabel ada
    $pdo->exec("CREATE TABLE IF NOT EXISTS menu_permissions (
        id INT AUTO_INCREMENT PRIMARY KEY,
        role_name VARCHAR(50) NOT NULL,
        menu_id VARCHAR(100) NOT NULL,
        is_allowed TINYINT(1) DEFAULT 0,
        UNIQUE KEY unique_perm (role_name, menu_id)
    )");

    // 2. Definisi Izin Standar (Default Pabrik)
    $default_perms = [
        // Ketua Yayasan (Akses Penuh)
        'Ketua Yayasan' => ['navDashboard', 'navProfil', 'navKalender', 'navVerifikasi', 'navQuota', 'navCalendarSettings', 'navMenuManagement', 'navBiodataPegawai', 'navBukuIndukPegawai', 'navValidasiIzin', 'navDaftarIzin', 'navRapat', 'navJadwalRapat', 'navBukuIndukSantri', 'navMentoring', 'navValidasiPembayaran', 'navTabelPembayaran', 'navRekapPembayaran', 'navPocketMoneyValidation', 'navTabelTransaksi', 'navValidasiPeraturan', 'navBuatPeraturan', 'navYayasanWidget', 'navMusyrifWidget'],
        
        // Bendahara Yayasan
        'Bendahara Yayasan' => ['navDashboard', 'navProfil', 'navKalender', 'navValidasiPembayaran', 'navTabelPembayaran', 'navRekapPembayaran', 'navPocketMoneyValidation', 'navTabelTransaksi', 'navYayasanWidget'],
        
        // Musyrif/Musyrifah
        'Musyrif' => ['navDashboard', 'navProfil', 'navKalender', 'navAbsensi', 'navIzinPegawai', 'navJadwalRapat', 'navValidasiIbadah', 'navGuardianLeaveValidation', 'navOnLeaveList', 'navInputTahfizh', 'navInputNilai', 'navMentoring', 'navMusyrifPocketMoney', 'navMusyrifWithdrawalValidation', 'navMusyrifWidget'],
        'Musyrifah' => ['navDashboard', 'navProfil', 'navKalender', 'navAbsensi', 'navIzinPegawai', 'navJadwalRapat', 'navValidasiIbadah', 'navGuardianLeaveValidation', 'navOnLeaveList', 'navInputTahfizh', 'navInputNilai', 'navMentoring', 'navMusyrifPocketMoney', 'navMusyrifWithdrawalValidation', 'navMusyrifWidget'],

        // Walisantri
        'Walisantri' => ['navDashboard', 'navProfil', 'navKalender', 'navRekapIbadahAnak', 'navViewTahfizh', 'navIzinWalisantri', 'navPocketMoneyDeposit', 'navMonitoringAnak'],

        // Santri
        'Santri Rijal' => ['navDashboard', 'navProfil', 'navKalender', 'navBiodataSantri', 'navIbadahHarian', 'navViewTahfizh', 'navSantriPocketMoney'],
        'Santri Nisa\'' => ['navDashboard', 'navProfil', 'navKalender', 'navBiodataSantri', 'navIbadahHarian', 'navViewTahfizh', 'navSantriPocketMoney'],
    ];

    // Cek apakah ada aksi
    if (isset($_GET['run']) && $_GET['run'] === 'true') {
        $pdo->beginTransaction();

        // Hapus semua izin yang ada
        $pdo->exec("TRUNCATE TABLE menu_permissions");
        echo "<p class='ok'>✅ Tabel `menu_permissions` berhasil dikosongkan.</p>";

        // Masukkan izin standar
        $stmt = $pdo->prepare("INSERT INTO menu_permissions (role_name, menu_id, is_allowed) VALUES (?, ?, 1)");
        $total_inserted = 0;
        foreach ($default_perms as $role => $menus) {
            foreach ($menus as $menu) {
                $stmt->execute([$role, $menu]);
                $total_inserted++;
            }
        }

        $pdo->commit();

        echo "<h2 class='ok'>✅ RESET BERHASIL!</h2>";
        echo "<p>Sebanyak <strong>$total_inserted</strong> aturan izin standar telah berhasil dimuat ulang ke database.</p>";
        echo "<p>Silakan <a href='../dashboard.html'><strong>kembali ke Dashboard</strong></a> dan lakukan hard refresh (Ctrl + F5). Semua menu dan widget seharusnya sudah kembali normal.</p>";

    } else {
        echo "<h2>Konfirmasi Reset Izin Akses</h2>";
        echo "<p>Tindakan ini akan <strong>MENGHAPUS SEMUA</strong> pengaturan izin akses yang ada saat ini dan menggantinya dengan pengaturan standar (default).</p>";
        echo "<p>Ini adalah langkah yang aman dan direkomendasikan jika menu atau widget Anda tidak muncul.</p>";
        echo "<div style='background:#ffebee; border:1px solid #ef5350; padding:15px; margin-top:20px;'>";
        echo "<h3>PERINGATAN!</h3>";
        echo "<p>Setelah menekan tombol di bawah, semua customisasi izin yang pernah Anda buat akan hilang dan kembali ke pengaturan awal.</p>";
        echo "<a href='?run=true' style='display:inline-block; background:red; color:white; padding:10px 15px; text-decoration:none; border-radius:5px; font-weight:bold;'>YA, SAYA YAKIN, RESET SEMUA IZIN SEKARANG</a>";
        echo "</div>";
    }

} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo "<h2>Error</h2>";
    echo "<p class='err'>Gagal menjalankan script: " . $e->getMessage() . "</p>";
}

echo "</body></html>";
?>