<?php
require_once 'db_connect.php';

try {
    $pdo = getDBConnection();
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    echo "<h1>Setup Standar KPI Pegawai (Revisi)</h1>";

    // Bersihkan data lama agar tidak duplikat
    $pdo->exec("TRUNCATE TABLE performance_kpi");
    echo "<p>✅ Tabel KPI lama dibersihkan.</p>";

    // Definisi KPI sesuai diskusi
    $kpi_data = [
        // 1. Kepala Sekolah
        ['Kepala Sekolah', 'Kedisiplinan Kehadiran', 'automatic', 20, 'Dihitung dari absensi harian.'],
        ['Kepala Sekolah', 'Pencapaian Program Sekolah', 'manual', 40, 'Evaluasi Yayasan terhadap target tahunan.'],
        ['Kepala Sekolah', 'Supervisi Akademik', 'manual', 40, 'Kualitas pembinaan terhadap guru.'],

        // 2. Sekretaris Sekolah
        ['Sekretaris Sekolah', 'Kedisiplinan Kehadiran', 'automatic', 30, 'Dihitung dari absensi harian.'],
        ['Sekretaris Sekolah', 'Kelengkapan Arsip & Data', 'manual', 40, 'Kerapian Buku Induk dan surat menyurat.'],
        ['Sekretaris Sekolah', 'Pelayanan Tamu & Wali', 'manual', 30, 'Respon cepat dan keramahan.'],

        // 3. Bendahara Sekolah
        ['Bendahara Sekolah', 'Kedisiplinan Kehadiran', 'automatic', 30, 'Dihitung dari absensi harian.'],
        ['Bendahara Sekolah', 'Ketepatan Laporan Keuangan', 'manual', 40, 'Laporan bulanan balance dan tepat waktu.'],
        ['Bendahara Sekolah', 'Transparansi & Integritas', 'manual', 30, 'Kejujuran pengelolaan dana.'],

        // 4. Kepala Asrama (Putra & Putri)
        ['Kepala Asrama Putra', 'Kedisiplinan Kehadiran', 'automatic', 20, 'Dihitung dari absensi harian.'],
        ['Kepala Asrama Putra', 'Rata-rata Hafalan Santri', 'automatic', 20, 'Rata-rata nilai kualitas hafalan seluruh santri.'],
        ['Kepala Asrama Putra', 'Kebersihan & Ketertiban', 'manual', 30, 'Kondisi fisik asrama.'],
        ['Kepala Asrama Putra', 'Pembinaan Musyrif', 'manual', 30, 'Kemampuan memimpin tim.'],

        ['Kepala Asrama Putri', 'Kedisiplinan Kehadiran', 'automatic', 20, 'Dihitung dari absensi harian.'],
        ['Kepala Asrama Putri', 'Rata-rata Hafalan Santri', 'automatic', 20, 'Rata-rata nilai kualitas hafalan seluruh santri.'],
        ['Kepala Asrama Putri', 'Kebersihan & Ketertiban', 'manual', 30, 'Kondisi fisik asrama.'],
        ['Kepala Asrama Putri', 'Pembinaan Musyrifah', 'manual', 30, 'Kemampuan memimpin tim.'],

        // 5. Musyrif / Musyrifah (REVISI: Fokus Utama Simakan Hafalan)
        ['Musyrif', 'Intensitas Simakan Hafalan', 'automatic', 40, 'Tugas Utama: Mengawal dan menyimak setoran hafalan santri.'],
        ['Musyrif', 'Kedisiplinan Kehadiran', 'automatic', 20, 'Absensi jam kerja wajib (07.00-14.00).'],
        ['Musyrif', 'Kehadiran Rapat', 'automatic', 10, 'Absensi kehadiran rapat.'],
        ['Musyrif', 'Akhlak & Keteladanan', 'manual', 15, 'Menjadi contoh baik bagi santri.'],
        ['Musyrif', 'Kebersihan Kamar Santri', 'manual', 15, 'Tanggung jawab area binaan.'],

        ['Musyrifah', 'Intensitas Simakan Hafalan', 'automatic', 40, 'Tugas Utama: Mengawal dan menyimak setoran hafalan santri.'],
        ['Musyrifah', 'Kedisiplinan Kehadiran', 'automatic', 20, 'Absensi jam kerja wajib (07.00-14.00).'],
        ['Musyrifah', 'Kehadiran Rapat', 'automatic', 10, 'Absensi kehadiran rapat.'],
        ['Musyrifah', 'Akhlak & Keteladanan', 'manual', 15, 'Menjadi contoh baik bagi santri.'],
        ['Musyrifah', 'Kebersihan Kamar Santri', 'manual', 15, 'Tanggung jawab area binaan.'],

        // 6. Ustadz / Ustadzah
        ['Ustadz', 'Kedisiplinan Kehadiran', 'automatic', 25, 'Absensi kehadiran mengajar.'],
        ['Ustadz', 'Kelengkapan Administrasi', 'automatic', 25, 'Upload Modul Ajar/RPP dan Input Nilai.'],
        ['Ustadz', 'Kehadiran Rapat', 'automatic', 10, 'Absensi rapat dewan guru.'],
        ['Ustadz', 'Penguasaan Kelas & Materi', 'manual', 40, 'Penilaian supervisi Kepala Sekolah.'],

        ['Ustadzah', 'Kedisiplinan Kehadiran', 'automatic', 25, 'Absensi kehadiran mengajar.'],
        ['Ustadzah', 'Kelengkapan Administrasi', 'automatic', 25, 'Upload Modul Ajar/RPP dan Input Nilai.'],
        ['Ustadzah', 'Kehadiran Rapat', 'automatic', 10, 'Absensi rapat dewan guru.'],
        ['Ustadzah', 'Penguasaan Kelas & Materi', 'manual', 40, 'Penilaian supervisi Kepala Sekolah.'],
    ];

    $stmt = $pdo->prepare("INSERT INTO performance_kpi (role_name, kpi_name, kpi_type, weight, description) VALUES (?, ?, ?, ?, ?)");
    
    foreach ($kpi_data as $kpi) {
        $stmt->execute($kpi);
    }

    echo "<p>✅ Berhasil menambahkan <strong>" . count($kpi_data) . "</strong> indikator KPI baru.</p>";
    echo "<h3>Selesai. Sistem penilaian kinerja siap digunakan.</h3>";
    echo "<p>Silakan buka menu <b>Penilaian Kinerja</b> (Kepala Sekolah) atau <b>Pengaturan Gaji</b> (Yayasan) untuk melihat hasilnya.</p>";

} catch (Exception $e) {
    die("Error: " . $e->getMessage());
}
?>