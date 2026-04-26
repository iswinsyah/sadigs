<?php
// File: sadigs_api_v3/setup_menus.php
header('Content-Type: text/html; charset=utf-8');
require_once 'db_connect.php';

try {
    $pdo = getDBConnection();
} catch (Exception $e) {
    die("Gagal koneksi database: " . $e->getMessage());
}

// --- AUTO-MIGRATE SCHEMA (Jaring Pengaman Hosting) ---
try {
    $check = $pdo->query("SHOW COLUMNS FROM menu_permissions LIKE 'can_view'");
    if ($check->rowCount() > 0) {
        $pdo->exec("ALTER TABLE menu_permissions CHANGE COLUMN can_view is_allowed TINYINT(1) DEFAULT 0");
        echo "<p style='color:blue'>ℹ️ Auto-fix: Kolom 'can_view' diperbarui menjadi 'is_allowed'.</p>";
    }
} catch (Exception $e) {
    // Abaikan jika tabel belum ada
}

// --- AUTO-CREATE TABLES (Jaring Pengaman) ---
$pdo->exec("CREATE TABLE IF NOT EXISTS menus (
    menu_id VARCHAR(50) PRIMARY KEY,
    menu_name VARCHAR(100) NOT NULL,
    category_id VARCHAR(50),
    icon VARCHAR(50),
    link VARCHAR(255)
)");

$pdo->exec("CREATE TABLE IF NOT EXISTS menu_permissions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    role_name VARCHAR(50) NOT NULL,
    menu_id VARCHAR(100) NOT NULL,
    is_allowed TINYINT(1) DEFAULT 0,
    UNIQUE KEY unique_perm (role_name, menu_id)
)");

// Daftar Menu Baru yang ingin didaftarkan
$menuDetails = [
    'navFormulirPembayaran' => ['name' => 'Formulir Pembayaran', 'link' => 'payment_form.html', 'icon' => 'credit-card'],
    'navValidasiPembayaran' => ['name' => 'Validasi Pembayaran', 'link' => 'payment_validation.html', 'icon' => 'check-circle-2'],
    'navTabelPembayaran' => ['name' => 'Data Pembayaran', 'link' => 'payment_list.html', 'icon' => 'banknote'],
    'navRekapPembayaran' => ['name' => 'Rekap Keuangan', 'link' => 'payment_recap.html', 'icon' => 'pie-chart'],
    // Menu Tahfidz Baru
    'navInputTahfizh' => ['name' => 'Input Tahfidz', 'link' => 'tahfizh_report_form.html', 'icon' => 'book-open'],
    'navViewTahfizh' => ['name' => 'Riwayat Tahfidz', 'link' => 'tahfizh_history.html', 'icon' => 'history'],
    'navViewTahfizhSantri' => ['name' => 'Riwayat Tahfidz', 'link' => 'tahfizh_history.html', 'icon' => 'history'],
    'navRekapTahfizh' => ['name' => 'Rekap Grafik Tahfidz', 'link' => 'tahfizh_recap.html', 'icon' => 'bar-chart-2'],
    // Menu Notifikasi Baru
    'navKirimNotifikasi' => ['name' => 'Kirim Notifikasi', 'link' => 'admin_notifications.html', 'icon' => 'send'],
    'navLihatNotifikasi' => ['name' => 'Notifikasi Saya', 'link' => 'my_notifications.html', 'icon' => 'bell'],
    // Menu Akademik & Santri (Tambahan)
    'navRiwayatIbadah' => ['name' => 'Rekap Ibadah (Khusus Santri)', 'link' => 'student_worship_view.html', 'icon' => 'history'],
    'navMonitoringIbadah' => ['name' => 'Rekap Ibadah (Global Admin)', 'link' => 'global_worship_monitoring.html', 'icon' => 'bar-chart-2'],
    'navGrafikIbadah' => ['name' => 'Grafik Rekap Ibadah', 'link' => 'worship_recap_chart.html', 'icon' => 'pie-chart'],
    'navBiodataSantri' => ['name' => 'Biodata Santri', 'link' => 'student_data.html', 'icon' => 'book-user'],
    'navBukuIndukSantri' => ['name' => 'Buku Induk Santri', 'link' => 'student_master_book.html', 'icon' => 'book'],
    'navManajemenKelas' => ['name' => 'Manajemen Kelas', 'link' => 'class_management.html', 'icon' => 'users'],
    'navRekapIbadahAnak' => ['name' => 'Rekap Ibadah (Khusus Wali)', 'link' => 'guardian_worship_view.html', 'icon' => 'clipboard-check'],
    'navMonitoringAkademik' => ['name' => 'Monitoring Akademik', 'link' => 'academic_monitoring.html', 'icon' => 'monitor-check'],
    // Menu Akademik Baru (Leger & Mapel)
    'navManajemenMapel' => ['name' => 'Manajemen Mapel', 'link' => 'subjects_management.html', 'icon' => 'library'],
    'navInputNilaiAkademik' => ['name' => 'Input Nilai Mapel', 'link' => 'grade_input.html', 'icon' => 'edit-3'],
    'navLegerNilai' => ['name' => 'Leger Nilai', 'link' => 'academic_ledger.html', 'icon' => 'book-open'],
    // Menu Penggajian
    'navAturGaji' => ['name' => 'Pengaturan Gaji', 'link' => 'payroll_settings.html', 'icon' => 'settings'],
    // Menu Slip Gaji Real-time (Untuk Pegawai)
    'navSlipGaji' => ['name' => 'Estimasi Gaji Saya', 'link' => 'my_salary_slip.html', 'icon' => 'wallet'],
    // Menu Rapot Santri
    'navNilaiRapotSantri' => ['name' => 'Nilai Rapot Santri', 'link' => 'student_grades_view.html', 'icon' => 'graduation-cap'],
    // Menu Rapot Anak (Khusus Walisantri)
    'navNilaiRapotAnak' => ['name' => 'Nilai Rapot Anak', 'link' => 'student_grades_view.html', 'icon' => 'graduation-cap']
];

// Ambil semua role yang ada di sistem
$stmt = $pdo->query("SELECT DISTINCT role_name FROM user_roles");
$roles = $stmt->fetchAll(PDO::FETCH_COLUMN);

// Tambahkan role default standar untuk memastikan semua tercover
$defaultRoles = ['Ketua Yayasan', 'Kepala Sekolah', 'Bendahara Sekolah', 'Bendahara Yayasan', 'Walisantri', 'Musyrif', 'Santri', 'Sekretaris Yayasan'];
$allRoles = array_unique(array_merge($roles, $defaultRoles));

echo "<h3>Setup Menu Baru SADIGS</h3>";
echo "<p>Memproses pendaftaran menu ke database...</p>";
echo "<ul>";

// --- HAPUS MENU LAMA (Agar tidak membingungkan) ---
$pdo->exec("DELETE FROM menus WHERE menu_id = 'navInputNilai'");
$pdo->exec("DELETE FROM menu_permissions WHERE menu_id = 'navInputNilai'");

// 1. Pastikan Menu Terdaftar di Tabel 'menus'
foreach ($menuDetails as $id => $detail) {
    // Hapus menu_name = VALUES(menu_name) agar nama yang sudah diedit manual oleh admin (ikon pensil) tidak tertimpa/direset
    $stmt = $pdo->prepare("INSERT INTO menus (menu_id, menu_name, link, icon) VALUES (?, ?, ?, ?) ON DUPLICATE KEY UPDATE link = VALUES(link), icon = VALUES(icon)");
    $stmt->execute([$id, $detail['name'], $detail['link'], $detail['icon']]);
}

    // --- KOREKSI KATEGORI MENU (DATABASE) ---
    // Memaksa menu-menu baru masuk ke deretan yang benar di Dashboard & Manajemen Akses
    $pdo->exec("INSERT INTO menu_categories (category_id, label, sort_order) VALUES ('ManajemenYayasan', '2. MANAJEMEN YAYASAN', 2), ('Umum', '1. UMUM', 1), ('Walisantri', '16. WALISANTRI', 16), ('Santri', '15. SANTRI', 15), ('BendaharaSekolah', '11. BENDAHARA SEKOLAH', 11) ON DUPLICATE KEY UPDATE label=VALUES(label)");
    $pdo->exec("UPDATE menus SET category_id = 'Umum' WHERE menu_id IN ('navDashboard', 'navProfil', 'navLihatNotifikasi', 'navKalender', 'navJadwalPelajaran')");
    $pdo->exec("UPDATE menus SET category_id = 'ManajemenYayasan' WHERE menu_id = 'navGrafikIbadah'");
    $pdo->exec("UPDATE menus SET category_id = 'Walisantri' WHERE menu_id IN ('navNilaiRapotAnak', 'navFormulirPembayaran', 'navRekapIbadahAnak', 'navViewTahfizh', 'navIzinWalisantri', 'navPocketMoneyDeposit', 'navMonitoringAnak')");
    $pdo->exec("UPDATE menus SET category_id = 'Santri' WHERE menu_id IN ('navNilaiRapotSantri', 'navBiodataSantri', 'navIbadahHarian', 'navRiwayatIbadah', 'navSantriPocketMoney', 'navViewTahfizhSantri')");
    $pdo->exec("UPDATE menus SET category_id = 'BendaharaSekolah' WHERE menu_id IN ('navValidasiPembayaran', 'navTabelPembayaran', 'navRekapPembayaran')");

$count = 0;

foreach ($allRoles as $role) {
    foreach (array_keys($menuDetails) as $menuId) {
        // 1. Cek apakah kombinasi Role + Menu sudah ada
        $check = $pdo->prepare("SELECT COUNT(*) FROM menu_permissions WHERE role_name = ? AND menu_id = ?");
        $check->execute([$role, $menuId]);
        $exists = $check->fetchColumn();
        
        if ($exists == 0) {
            // 2. Jika belum ada, Insert
            // Default: Hanya Ketua Yayasan yang otomatis aktif (1), lainnya non-aktif (0)
            $canView = ($role === 'Ketua Yayasan') ? 1 : 0;

            $insert = $pdo->prepare("INSERT INTO menu_permissions (role_name, menu_id, is_allowed) VALUES (?, ?, ?)");
            $insert->execute([$role, $menuId, $canView]);
            
            echo "<li style='color: green;'>Berhasil menambahkan: <b>$menuId</b> untuk role <b>$role</b> (Akses: $canView)</li>";
            $count++;
        }
    }
}

// --- FORCE UPDATE ADMIN PERMISSIONS ---
// Pastikan Ketua Yayasan selalu punya akses ke menu baru ini (jika sebelumnya 0)
$adminRole = 'Ketua Yayasan';
foreach (array_keys($menuDetails) as $menuId) {
    $pdo->prepare("UPDATE menu_permissions SET is_allowed = 1 WHERE role_name = ? AND menu_id = ?")->execute([$adminRole, $menuId]);
}
echo "<li><b style='color:blue'>Info:</b> Hak akses Ketua Yayasan untuk menu baru telah dipastikan AKTIF.</li>";

// --- FORCE UPDATE PERMISSIONS FOR TAHFIDZ (SANTRI & WALISANTRI) ---
// Agar otomatis aktif tanpa perlu setting manual di Manajemen Akses
$tahfidzPerms = [
    'navViewTahfizh' => ['Walisantri', 'Musyrif', 'Musyrifah'],
    'navViewTahfizhSantri' => ['Santri', 'Santri Rijal', "Santri Nisa'"],
    'navInputTahfizh' => ['Musyrif', 'Musyrifah', 'Ustadz', 'Ustadzah'],
    'navRekapTahfizh' => ['Ketua Yayasan', 'Kepala Sekolah', 'Kepala Asrama Putra', 'Kepala Asrama Putri']
];

foreach ($tahfidzPerms as $mId => $rList) {
    foreach ($rList as $rName) {
        // Gunakan INSERT ... ON DUPLICATE KEY UPDATE untuk memastikan permission aktif (1)
        $pdo->prepare("INSERT INTO menu_permissions (role_name, menu_id, is_allowed) VALUES (?, ?, 1) ON DUPLICATE KEY UPDATE is_allowed = 1")
            ->execute([$rName, $mId]);
    }
}
echo "<li><b style='color:blue'>Info:</b> Hak akses Tahfidz untuk Santri, Walisantri, dan Musyrif telah diaktifkan otomatis.</li>";

// --- FORCE UPDATE PERMISSIONS FOR NOTIFICATIONS ---
$notifPerms = [
    'navKirimNotifikasi' => ['Ketua Yayasan', 'Sekretaris Yayasan', 'Bendahara Yayasan', 'Kepala Sekolah', 'Admin Sekolah'],
    'navLihatNotifikasi' => $allRoles // Semua role bisa lihat notifikasi
];
foreach ($notifPerms as $mId => $rList) {
    foreach ($rList as $rName) {
        $pdo->prepare("INSERT INTO menu_permissions (role_name, menu_id, is_allowed) VALUES (?, ?, 1) ON DUPLICATE KEY UPDATE is_allowed = 1")->execute([$rName, $mId]);
    }
}
echo "<li><b style='color:blue'>Info:</b> Hak akses Notifikasi telah diaktifkan otomatis.</li>";

// --- FORCE UPDATE PERMISSIONS FOR ACADEMIC ---
$academicPerms = [
    'navManajemenKelas' => ['Ketua Yayasan', 'Kepala Sekolah', 'Admin Sekolah'],
    'navMonitoringAkademik' => ['Ketua Yayasan', 'Kepala Sekolah', 'Admin Sekolah'],
    'navBukuIndukSantri' => ['Ketua Yayasan', 'Kepala Sekolah', 'Admin Sekolah', 'Bendahara Sekolah']
];
foreach ($academicPerms as $mId => $rList) {
    foreach ($rList as $rName) {
        $pdo->prepare("INSERT INTO menu_permissions (role_name, menu_id, is_allowed) VALUES (?, ?, 1) ON DUPLICATE KEY UPDATE is_allowed = 1")->execute([$rName, $mId]);
    }
}

// --- FORCE UPDATE PERMISSIONS FOR ACADEMIC LEDGER ---
$academicLegerPerms = [
    'navManajemenMapel' => ['Ketua Yayasan', 'Kepala Sekolah', 'Admin Sekolah'],
    'navInputNilaiAkademik' => ['Ketua Yayasan', 'Kepala Sekolah', 'Admin Sekolah', 'Ustadz', 'Ustadzah'],
    'navLegerNilai' => ['Ketua Yayasan', 'Kepala Sekolah', 'Admin Sekolah']
];
foreach ($academicLegerPerms as $mId => $rList) {
    foreach ($rList as $rName) {
        $pdo->prepare("INSERT INTO menu_permissions (role_name, menu_id, is_allowed) VALUES (?, ?, 1) ON DUPLICATE KEY UPDATE is_allowed = 1")->execute([$rName, $mId]);
    }
}

// --- FORCE UPDATE PERMISSIONS FOR WORSHIP HISTORY ---
$worshipPerms = ['Santri', 'Santri Rijal', "Santri Nisa'"];
foreach ($worshipPerms as $rName) {
    $pdo->prepare("INSERT INTO menu_permissions (role_name, menu_id, is_allowed) VALUES (?, 'navRiwayatIbadah', 1) ON DUPLICATE KEY UPDATE is_allowed = 1")->execute([$rName]);
}

// --- FORCE UPDATE PERMISSIONS FOR GLOBAL MONITORING ---
$monitoringPerms = ['Ketua Yayasan', 'Kepala Sekolah', 'Kepala Asrama Putra', 'Kepala Asrama Putri', 'Admin Sekolah'];
foreach ($monitoringPerms as $rName) {
    $pdo->prepare("INSERT INTO menu_permissions (role_name, menu_id, is_allowed) VALUES (?, 'navMonitoringIbadah', 1) ON DUPLICATE KEY UPDATE is_allowed = 1")->execute([$rName]);
}

// --- FORCE UPDATE PERMISSIONS FOR GRAFIK IBADAH (YAYASAN ONLY) ---
$grafikPerms = ['Ketua Yayasan', 'Sekretaris Yayasan', 'Bendahara Yayasan'];
foreach ($grafikPerms as $rName) {
    $pdo->prepare("INSERT INTO menu_permissions (role_name, menu_id, is_allowed) VALUES (?, 'navGrafikIbadah', 1) ON DUPLICATE KEY UPDATE is_allowed = 1")->execute([$rName]);
}

// --- FORCE UPDATE PERMISSIONS FOR GUARDIAN ---
$pdo->prepare("INSERT INTO menu_permissions (role_name, menu_id, is_allowed) VALUES ('Walisantri', 'navRekapIbadahAnak', 1) ON DUPLICATE KEY UPDATE is_allowed = 1")->execute();

// --- FORCE UPDATE PERMISSIONS FOR PAYROLL SETTINGS ---
$payrollPerms = ['Ketua Yayasan', 'Bendahara Yayasan'];
foreach ($payrollPerms as $rName) {
    $pdo->prepare("INSERT INTO menu_permissions (role_name, menu_id, is_allowed) VALUES (?, 'navAturGaji', 1) ON DUPLICATE KEY UPDATE is_allowed = 1")->execute([$rName]);
}

// --- FORCE UPDATE PERMISSIONS FOR SALARY SLIP ---
// Semua role pegawai (kecuali santri/wali) bisa lihat slip gaji mereka
$employeeRoles = ['Ketua Yayasan', 'Sekretaris Yayasan', 'Bendahara Yayasan', 'Kepala Sekolah', 'Admin Sekolah', 'Sekretaris Sekolah', 'Bendahara Sekolah', 'Kepala Asrama Putra', 'Kepala Asrama Putri', 'Musyrif', 'Musyrifah', 'Ustadz', 'Ustadzah'];
foreach ($employeeRoles as $rName) {
    $pdo->prepare("INSERT INTO menu_permissions (role_name, menu_id, is_allowed) VALUES (?, 'navSlipGaji', 1) ON DUPLICATE KEY UPDATE is_allowed = 1")->execute([$rName]);
}


// --- FORCE UPDATE PERMISSIONS FOR RAPOT ---
$pdo->prepare("INSERT INTO menu_permissions (role_name, menu_id, is_allowed) VALUES ('Walisantri', 'navNilaiRapotAnak', 1) ON DUPLICATE KEY UPDATE is_allowed = 1")->execute();
$santriRoles = ['Santri Rijal', "Santri Nisa'"];
foreach ($santriRoles as $rName) {
    $pdo->prepare("INSERT INTO menu_permissions (role_name, menu_id, is_allowed) VALUES (?, 'navNilaiRapotSantri', 1) ON DUPLICATE KEY UPDATE is_allowed = 1")->execute([$rName]);
}

// --- CLEANUP: Hapus Akses Walisantri dari Menu Santri Lama ---
$pdo->exec("DELETE FROM menu_permissions WHERE role_name = 'Walisantri' AND menu_id = 'navNilaiRapotSantri'");

// --- CLEANUP EKSTRA: Rapikan & Hapus Akses Gaib Menu Santri ---
$pdo->exec("DELETE FROM menu_permissions WHERE role_name IN ('Santri', 'Santri Rijal', 'Santri Nisa\'') AND menu_id NOT IN ('navDashboard', 'navProfil', 'navKalender', 'navJadwalPelajaran', 'navLihatNotifikasi', 'navNilaiRapotSantri', 'navBiodataSantri', 'navIbadahHarian', 'navRiwayatIbadah', 'navSantriPocketMoney', 'navViewTahfizhSantri')");
$pdo->exec("DELETE FROM menu_permissions WHERE role_name IN ('Santri', 'Santri Rijal', 'Santri Nisa\'') AND menu_id = 'navViewTahfizh'");

echo "<li><b style='color:blue'>Info:</b> Hak akses Menu Santri telah dirapikan dan dibersihkan dari menu yang tidak relevan secara otomatis.</li>";

echo "</ul>";

if ($count > 0) {
    echo "<h4>Selesai! $count izin menu berhasil ditambahkan.</h4>";
} else {
    echo "<h4>Semua menu sudah terdaftar sebelumnya. Tidak ada perubahan.</h4>";
}

echo "<p>Silakan kembali ke <a href='../menu_management.html'>Manajemen Akses</a> untuk mengatur siapa yang boleh melihat menu ini.</p>";
?>