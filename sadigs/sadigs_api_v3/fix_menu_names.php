<?php
header('Content-Type: text/html; charset=utf-8');
require_once 'db_connect.php';

echo "<!DOCTYPE html><html><head><title>Perbaikan Nama Menu</title><style>body{font-family: sans-serif; padding: 20px; line-height: 1.6;} h1{color: #26667F;} code{background: #f1f1f1; padding: 2px 5px; border-radius: 4px;} .ok{color:green;} .warn{color:orange;}</style></head><body>";
echo "<h1>Alat Perbaikan Nama Menu</h1>";

try {
    $pdo = getDBConnection();
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Pastikan tabel 'menus' ada
    $pdo->exec("CREATE TABLE IF NOT EXISTS `menus` (
      `menu_id` VARCHAR(50) NOT NULL,
      `menu_name` VARCHAR(100) NOT NULL,
      PRIMARY KEY (`menu_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");
    echo "<p class='ok'>✅ Tabel 'menus' siap digunakan.</p><hr>";

    // Daftar nama menu yang benar sesuai permintaan
    $correct_names = [
        'navAbsensi' => 'Absensi Pegawai',
        'navBuatPeraturan' => 'Penerbitan Peraturan',
        'navCalendarSettings' => 'Atur Kalender Pendidikan',
        'navFormulirPembayaran' => 'Pembayaran Keuangan',
        'navFormulirTransaksi' => 'Transaksi Harian',
        'navIbadahHarian' => 'Mukhasabah Harian Santri',
        'navInputTahfizh' => 'Capaian Hafalan Santri',
        'navIzinWalisantri' => 'Permohonan Izin Walisantri',
        'navPocketMoneyDeposit' => 'Topup Uang Saku',
        'navQuota' => 'Atur Kuota Pegawai',
        'navRapat' => 'Undangan Rapat',
        'navGuardianLeaveValidation' => 'Permohonan Izin walisantri',
        'navMusyrifWithdrawalValidation' => 'Penarikan Uang Saku',
        'navPocketMoneyValidation' => 'Cek Kiriman Uang Saku',
        'navValidasiIbadah' => 'Cek Mukhasabah Harian Santri',
        'navValidasiIzin' => 'Izin Pegawai',
        'navValidasiPembayaran' => 'Pembayaran Keuangan',
        'navValidasiPeraturan' => 'Setujui Peraturan',
        'navVerifikasi' => 'Verifikasi Peran Pegawai',
        'navDaftarIzin' => 'Daftar Santri Izin Tidak Masuk',
        'navKalender' => 'Kalender Pendidikan',
        'navMentoring' => 'Daftar Kelompok Mentoring',
        'navMonitoringAnak' => 'Monitoring Santri',
        'navMusyrifPocketMoney' => 'Monitoring Uang Saku Santri',
        'navOnLeaveList' => 'Daftar Santri Pulang',
        'navRekapIbadahAnak' => 'Rekap Mukhasabah Santri',
        'navRppAlbum' => 'Album RPP',
        'navSantriPocketMoney' => 'Uang Saku Santri',
        'navViewTahfizh' => 'Capaian Hafalan',
        'navRekapPembayaran' => 'Rekap Pembayaran Keuangan',
        // Menu lain yang ada di sistem
        'navDashboard' => 'Dasbor',
        'navProfil' => 'Profil Saya',
        'navBiodataPegawai' => 'Biodata Pegawai',
        'navBiodataSantri' => 'Biodata Santri',
        'navInputNilai' => 'Input Nilai Rapot',
        'navMenuManagement' => 'Manajemen Akses',
        'navJadwalRapat' => 'Jadwal Rapat',
        'navBukuIndukPegawai' => 'Buku Induk Pegawai',
        'navBukuIndukSantri' => 'Buku Induk Santri',
        'navTabelPembayaran' => 'Data Pembayaran',
        'navTabelTransaksi' => 'Buku Transaksi Harian',
        'navRppGenerator' => 'RPP Generator (AI)',
        'navYayasanWidget' => 'Ringkasan Manajemen',
        'navMusyrifWidget' => 'Ringkasan Musyrif',
        'navPerencanaanAkademik' => 'Buku Kerja Ustadz',
    ];

    $stmt = $pdo->prepare("INSERT INTO menus (menu_id, menu_name) VALUES (?, ?) ON DUPLICATE KEY UPDATE menu_name = VALUES(menu_name)");
    $count = 0;
    foreach ($correct_names as $id => $name) {
        $stmt->execute([$id, $name]);
        $count += $stmt->rowCount(); // rowCount akan 1 untuk INSERT, 2 untuk UPDATE, 0 jika tidak ada perubahan
        echo "Memperbarui: <code>$id</code> -> <strong>$name</strong><br>";
    }

    echo "<h2 class='ok'>✅ Selesai! Total $count perubahan telah diterapkan ke database.</h2>";

} catch (Exception $e) {
    echo "<h2>Error</h2><p style='color:red;'>Gagal menjalankan script: " . $e->getMessage() . "</p>";
}

echo "</body></html>";
?>