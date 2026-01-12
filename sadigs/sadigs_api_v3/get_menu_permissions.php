<?php
header('Content-Type: application/json');
require_once 'db_connect.php';

// Matikan error reporting yang merusak JSON
error_reporting(0);
ini_set('display_errors', 0);

if (session_status() === PHP_SESSION_NONE) session_start();

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$user_id = $_SESSION['user_id'];
$roles = $_SESSION['roles'] ?? [];

// --- KONFIGURASI MENU CERDAS (CONTEXTUAL) ---
// Menu-menu ini akan otomatis pindah ke kategori peran user yang login
$contextual_menus = ['navRapat', 'navJadwalRapat', 'navIzinPegawai', 'navKetersediaanMengajar', 'navRekapAbsensi', 'navGrafikAbsensi'];

// Mapping Role ke Category ID
$role_to_cat_map = [
    'Ketua Yayasan' => 'KetuaYayasan',
    'Sekretaris Yayasan' => 'SekretarisYayasan',
    'Bendahara Yayasan' => 'BendaharaYayasan',
    'Kepala Sekolah' => 'KepalaSekolah',
    'Admin Sekolah' => 'AdminSekolah',
    'Sekretaris Sekolah' => 'SekretarisSekolah',
    'Bendahara Sekolah' => 'BendaharaSekolah',
    'Kepala Asrama Putra' => 'KepalaAsrama',
    'Kepala Asrama Putri' => 'KepalaAsrama',
    'Musyrif' => 'Musyrif',
    'Musyrifah' => 'Musyrif',
    'Ustadz' => 'Ustadz',
    'Ustadzah' => 'Ustadz',
    'Walisantri' => 'Walisantri',
    'Santri' => 'Santri',
    'Santri Rijal' => 'Santri',
    "Santri Nisa'" => 'Santri'
];

// --- DATA MASTER (JURUS PAMUNGKAS: HARDCODED FALLBACK) ---
// Data ini digunakan jika database kosong, error, atau belum disetting.
$master_structure = [
    'Umum' => [
        'label' => '1. UMUM',
        'items' => [
            ['id' => 'navDashboard', 'label' => 'Dashboard', 'icon' => 'layout-dashboard', 'link' => 'dashboard.html'],
            ['id' => 'navProfil', 'label' => 'Profil Saya', 'icon' => 'user', 'link' => 'profile.html'],
            ['id' => 'navKalender', 'label' => 'Kalender Pendidikan', 'icon' => 'calendar', 'link' => 'calendar_view.html'],
            ['id' => 'navJadwalPelajaran', 'label' => 'Jadwal Pelajaran', 'icon' => 'calendar-days', 'link' => 'view_schedule.html']
        ]
    ],
    'ManajemenYayasan' => [
        'label' => '2. MANAJEMEN YAYASAN',
        'items' => [
            ['id' => 'navRekapJurnal', 'label' => 'Rekap Jurnal Mengajar', 'icon' => 'book-open-check', 'link' => 'teaching_recap.html'],
            ['id' => 'navYayasanWidget', 'label' => 'Ringkasan Manajemen', 'icon' => 'activity', 'link' => '#']
        ]
    ],
    'KetuaYayasan' => [
        'label' => '3. KETUA YAYASAN',
        'items' => [
            ['id' => 'navMenuManagement', 'label' => 'Formulir Manajemen Akses', 'icon' => 'lock', 'link' => 'menu_management.html'],
            ['id' => 'navUserCredentials', 'label' => 'Data Akun Pengguna', 'icon' => 'key', 'link' => 'user_credentials.html']
        ]
    ],
    'SekretarisYayasan' => [
        'label' => '4. SEKRETARIS YAYASAN',
        'items' => [
            ['id' => 'navUserCredentials', 'label' => 'Manajemen Pengguna', 'icon' => 'users', 'link' => 'user_credentials.html'],
            ['id' => 'navQuota', 'label' => 'Formulir Atur Kuota Peran', 'icon' => 'users', 'link' => 'quota_management.html'],
            ['id' => 'navCalendarSettings', 'label' => 'Formulir Atur Kalender', 'icon' => 'calendar-cog', 'link' => 'calendar_settings.html']
        ]
    ],
    'BendaharaYayasan' => ['label' => '5. BENDAHARA YAYASAN', 'items' => []],
    'ManajemenSekolah' => [
        'label' => '6. MANAJEMEN SEKOLAH',
        'items' => [
            ['id' => 'navBukuIndukSantri', 'label' => 'Buku Induk Santri', 'icon' => 'book', 'link' => 'student_master_book.html'],
            ['id' => 'navManajemenKelas', 'label' => 'Manajemen Kelas', 'icon' => 'users', 'link' => 'class_management.html'],
            ['id' => 'navAturKurikulum', 'label' => 'Formulir Atur Kurikulum', 'icon' => 'book-open-check', 'link' => 'curriculum_settings.html']
        ]
    ],
    'KepalaSekolah' => [
        'label' => '7. KEPALA SEKOLAH',
        'items' => [
            ['id' => 'navPenilaianKinerja', 'label' => 'Penilaian Kinerja', 'icon' => 'award', 'link' => 'performance_assessment.html'],
            ['id' => 'navMonitoringAkademik', 'label' => 'Monitoring Akademik', 'icon' => 'monitor-check', 'link' => 'academic_monitoring.html'],
            ['id' => 'navValidasiIzin', 'label' => 'Validasi Izin', 'icon' => 'check-square', 'link' => 'leave_validation.html'],
            ['id' => 'navDaftarIzin', 'label' => 'Daftar Izin', 'icon' => 'list-checks', 'link' => 'leave_list.html'],
            ['id' => 'navValidasiPeraturan', 'label' => 'Validasi Peraturan', 'icon' => 'gavel', 'link' => 'validate_regulation.html'],
            ['id' => 'navBuatPeraturan', 'label' => 'Formulir Terbitkan Peraturan', 'icon' => 'megaphone', 'link' => 'create_regulation.html']
        ]
    ],
    'KepalaAsrama' => [
        'label' => '8. KEPALA ASRAMA',
        'items' => [['id' => 'navOnLeaveList', 'label' => 'Santri Sedang Pulang', 'icon' => 'user-minus', 'link' => 'on_leave_list.html']]
    ],
    'AdminSekolah' => ['label' => '9. ADMIN SEKOLAH', 'items' => []],
    'SekretarisSekolah' => ['label' => '10. SEKRETARIS SEKOLAH', 'items' => []],
    'BendaharaSekolah' => [
        'label' => '11. BENDAHARA SEKOLAH',
        'items' => [
            ['id' => 'navValidasiPembayaran', 'label' => 'Validasi Pembayaran', 'icon' => 'check-circle-2', 'link' => 'payment_validation.html'],
            ['id' => 'navTabelPembayaran', 'label' => 'Data Pembayaran', 'icon' => 'banknote', 'link' => 'payment_list.html'],
            ['id' => 'navRekapPembayaran', 'label' => 'Rekap Keuangan', 'icon' => 'pie-chart', 'link' => 'payment_recap.html'],
            ['id' => 'navPocketMoneyValidation', 'label' => 'Validasi Uang Saku', 'icon' => 'check-circle-2', 'link' => 'pocket_money_validation.html'],
            ['id' => 'navFormulirTransaksi', 'label' => 'Formulir Transaksi Harian', 'icon' => 'pen-tool', 'link' => 'daily_transaction_form.html'],
            ['id' => 'navTabelTransaksi', 'label' => 'Buku Transaksi Harian', 'icon' => 'book', 'link' => 'daily_transaction_list.html']
        ]
    ],
    'Kepegawaian' => [
        'label' => '12. ADMINISTRASI PEGAWAI',
        'items' => [
            ['id' => 'navAbsensi', 'label' => 'Absensi Pegawai', 'icon' => 'map-pin', 'link' => 'attendance.html'],
            ['id' => 'navAbsensiPribadi', 'label' => 'Riwayat Absensi Saya', 'icon' => 'history', 'link' => 'personal_attendance.html'],
            ['id' => 'navRekapAbsensi', 'label' => 'Tabel Rekap Absensi', 'icon' => 'clipboard-list', 'link' => 'attendance_recap.html'],
            ['id' => 'navGrafikAbsensi', 'label' => 'Grafik Kehadiran', 'icon' => 'bar-chart-3', 'link' => 'attendance_graphs.html'],
            ['id' => 'navBiodataPegawai', 'label' => 'Biodata Pegawai', 'icon' => 'file-badge', 'link' => 'employee_data.html'],
            ['id' => 'navIzinPegawai', 'label' => 'Formulir Izin', 'icon' => 'file-edit', 'link' => 'leave_request.html'],
            ['id' => 'navBukuIndukPegawai', 'label' => 'Buku Induk Pegawai', 'icon' => 'book-open', 'link' => 'employee_master_book.html'],
            ['id' => 'navRapat', 'label' => 'Formulir Undang Rapat', 'icon' => 'mail', 'link' => 'meeting_invitation.html'],
            ['id' => 'navJadwalRapat', 'label' => 'Jadwal Rapat', 'icon' => 'calendar-clock', 'link' => 'meeting_schedule.html']
        ]
    ],
    'Ustadz' => [
        'label' => '13. USTADZ',
        'items' => [
            ['id' => 'navJurnalMengajar', 'label' => 'Formulir Jurnal Mengajar', 'icon' => 'pen-tool', 'link' => 'teaching_journal_form.html'],
            ['id' => 'navRiwayatMengajar', 'label' => 'Riwayat Mengajar', 'icon' => 'history', 'link' => 'teaching_history.html'],
            ['id' => 'navRppGenerator', 'label' => 'Formulir Generator Modul Ajar', 'icon' => 'brain-circuit', 'link' => 'rpp_generator.html'],
            ['id' => 'navRppAlbum', 'label' => 'Album Perangkat Ajar', 'icon' => 'folder-open', 'link' => 'rpp_album.html'],
            ['id' => 'navPerencanaanAkademik', 'label' => 'Buku Kerja Ustadz', 'icon' => 'book-check', 'link' => 'academic_planning.html'],
            ['id' => 'navKetersediaanMengajar', 'label' => 'Formulir Kesediaan Mengajar', 'icon' => 'clock', 'link' => 'teacher_availability.html'],
            ['id' => 'navInputNilai', 'label' => 'Formulir Input Nilai Rapot', 'icon' => 'graduation-cap', 'link' => 'input_grades.html']
        ]
    ],
    'Musyrif' => [
        'label' => '14. MUSYRIF',
        'items' => [
            ['id' => 'navMusyrifWidget', 'label' => 'Ringkasan Musyrif', 'icon' => 'activity', 'link' => '#'],
            ['id' => 'navMentoring', 'label' => 'Kelompok Mentoring', 'icon' => 'users-round', 'link' => 'mentoring_groups.html'],
            ['id' => 'navInputTahfizh', 'label' => 'Formulir Input Tahfizh', 'icon' => 'book-marked', 'link' => 'tahfizh_report_form.html'],
            ['id' => 'navValidasiIbadah', 'label' => 'Validasi Ibadah', 'icon' => 'check-square', 'link' => 'daily_worship_validation.html'],
            ['id' => 'navGuardianLeaveValidation', 'label' => 'Validasi Izin Walisantri', 'icon' => 'mail-check', 'link' => 'guardian_leave_validation.html'],
            ['id' => 'navMusyrifPocketMoney', 'label' => 'Riwayat Deposit Santri', 'icon' => 'archive', 'link' => 'musyrif_pocket_money_view.html'],
            ['id' => 'navMusyrifWithdrawalValidation', 'label' => 'Validasi Penarikan', 'icon' => 'check-square', 'link' => 'musyrif_withdrawal_validation.html']
        ]
    ],
    'Santri' => [
        'label' => '15. SANTRI',
        'items' => [
            ['id' => 'navBiodataSantri', 'label' => 'Biodata Santri', 'icon' => 'book-user', 'link' => 'student_data.html'],
            ['id' => 'navIbadahHarian', 'label' => 'Formulir Laporan Ibadah Harian', 'icon' => 'notebook-pen', 'link' => 'daily_worship_form.html'],
            ['id' => 'navSantriPocketMoney', 'label' => 'Uang Saku Saya', 'icon' => 'landmark', 'link' => 'santri_pocket_money.html']
        ]
    ],
    'Walisantri' => [
        'label' => '16. WALISANTRI',
        'items' => [
            ['id' => 'navRekapIbadahAnak', 'label' => 'Rekap Ibadah Anak', 'icon' => 'clipboard-check', 'link' => 'select_student.html'],
            ['id' => 'navViewTahfizh', 'label' => 'Laporan Tahfizh', 'icon' => 'book-open-check', 'link' => 'tahfizh_student_view.html'],
            ['id' => 'navIzinWalisantri', 'label' => 'Formulir Izin Walisantri', 'icon' => 'mail-question', 'link' => 'guardian_leave_request.html'],
            ['id' => 'navPocketMoneyDeposit', 'label' => 'Formulir Deposit Uang Saku', 'icon' => 'wallet', 'link' => 'pocket_money_deposit.html'],
            ['id' => 'navMonitoringAnak', 'label' => 'Monitoring Perkembangan', 'icon' => 'activity', 'link' => 'guardian_monitoring.html'],
            ['id' => 'navFormulirPembayaran', 'label' => 'Formulir Pembayaran', 'icon' => 'credit-card', 'link' => 'payment_form.html']
        ]
    ]
];

$master_permissions = [
    'Ketua Yayasan' => ['navDashboard', 'navProfil', 'navKalender', 'navVerifikasi', 'navQuota', 'navCalendarSettings', 'navMenuManagement', 'navUserCredentials', 'navAbsensi', 'navAbsensiPribadi', 'navRekapAbsensi', 'navGrafikAbsensi', 'navBiodataPegawai', 'navBukuIndukPegawai', 'navIzinPegawai', 'navValidasiIzin', 'navDaftarIzin', 'navRapat', 'navJadwalRapat', 'navRppGenerator', 'navRppAlbum', 'navBiodataSantri', 'navBukuIndukSantri', 'navIbadahHarian', 'navValidasiIbadah', 'navGuardianLeaveValidation', 'navOnLeaveList', 'navRekapIbadahAnak', 'navInputTahfizh', 'navViewTahfizh', 'navMentoring', 'navIzinWalisantri', 'navMusyrifPocketMoney', 'navSantriPocketMoney', 'navMusyrifWithdrawalValidation', 'navFormulirPembayaran', 'navValidasiPembayaran', 'navTabelPembayaran', 'navRekapPembayaran', 'navPocketMoneyDeposit', 'navPocketMoneyValidation', 'navFormulirTransaksi', 'navTabelTransaksi', 'navValidasiPeraturan', 'navBuatPeraturan', 'navYayasanWidget', 'navMusyrifWidget', 'navAturKurikulum', 'navJadwalPelajaran', 'navPenilaianKinerja', 'navMonitoringAkademik', 'navRekapJurnal'],
    'Sekretaris Yayasan' => ['navDashboard', 'navProfil', 'navKalender', 'navVerifikasi', 'navQuota', 'navCalendarSettings', 'navBiodataPegawai', 'navBukuIndukPegawai', 'navRapat', 'navJadwalRapat', 'navBuatPeraturan', 'navYayasanWidget', 'navRekapAbsensi', 'navGrafikAbsensi', 'navRekapJurnal'],
    'Bendahara Yayasan' => ['navDashboard', 'navProfil', 'navKalender', 'navValidasiPembayaran', 'navTabelPembayaran', 'navRekapPembayaran', 'navPocketMoneyValidation', 'navTabelTransaksi', 'navYayasanWidget', 'navRekapAbsensi', 'navGrafikAbsensi', 'navRekapJurnal'],
    'Kepala Sekolah' => ['navDashboard', 'navProfil', 'navKalender', 'navAbsensi', 'navAbsensiPribadi', 'navRekapAbsensi', 'navGrafikAbsensi', 'navBiodataPegawai', 'navBukuIndukPegawai', 'navValidasiIzin', 'navDaftarIzin', 'navRapat', 'navJadwalRapat', 'navRppGenerator', 'navRppAlbum', 'navBiodataSantri', 'navBukuIndukSantri', 'navManajemenKelas', 'navValidasiIbadah', 'navMentoring', 'navInputNilai', 'navYayasanWidget', 'navPerencanaanAkademik', 'navKetersediaanMengajar', 'navAturKurikulum', 'navJadwalPelajaran', 'navPenilaianKinerja', 'navMonitoringAkademik', 'navValidasiPeraturan', 'navBuatPeraturan'],
    'Bendahara Sekolah' => ['navDashboard', 'navProfil', 'navKalender', 'navValidasiPembayaran', 'navTabelPembayaran', 'navRekapPembayaran', 'navPocketMoneyValidation', 'navFormulirTransaksi', 'navTabelTransaksi'],
    'Musyrif' => ['navDashboard', 'navProfil', 'navKalender', 'navAbsensi', 'navAbsensiPribadi', 'navIzinPegawai', 'navJadwalRapat', 'navValidasiIbadah', 'navGuardianLeaveValidation', 'navOnLeaveList', 'navInputTahfizh', 'navInputNilai', 'navMentoring', 'navMusyrifPocketMoney', 'navMusyrifWithdrawalValidation', 'navMusyrifWidget', 'navPerencanaanAkademik', 'navKetersediaanMengajar'],
    'Ustadz' => ['navDashboard', 'navProfil', 'navKalender', 'navAbsensi', 'navAbsensiPribadi', 'navIzinPegawai', 'navJadwalRapat', 'navRppGenerator', 'navRppAlbum', 'navMentoring', 'navInputNilai', 'navMusyrifWidget', 'navPerencanaanAkademik', 'navKetersediaanMengajar', 'navJurnalMengajar', 'navRiwayatMengajar'],
    'Santri' => ['navDashboard', 'navProfil', 'navKalender', 'navBiodataSantri', 'navIbadahHarian', 'navViewTahfizh', 'navSantriPocketMoney'],
    'Walisantri' => ['navDashboard', 'navProfil', 'navKalender', 'navRekapIbadahAnak', 'navViewTahfizh', 'navIzinWalisantri', 'navPocketMoneyDeposit', 'navMonitoringAnak', 'navFormulirPembayaran']
];

// Alias untuk role gender-specific
$master_permissions['Musyrifah'] = $master_permissions['Musyrif'];
$master_permissions['Ustadzah'] = $master_permissions['Ustadz'];
$master_permissions['Santri Rijal'] = $master_permissions['Santri'];
$master_permissions['Santri Nisa\''] = $master_permissions['Santri'];
$master_permissions['Kepala Asrama Putra'] = $master_permissions['Musyrif']; // Asumsi
$master_permissions['Kepala Asrama Putri'] = $master_permissions['Musyrif']; // Asumsi

// --- LOGIKA UTAMA ---

$categories = [];
$menu_names = [];
$menu_icons = [];
$menu_links = [];
$allowed_menus = [];

try {
    $pdo = getDBConnection();

    // 1. AUTO-REPAIR DATABASE (Jika tabel hilang)
    $pdo->exec("CREATE TABLE IF NOT EXISTS menu_categories (category_id VARCHAR(50) PRIMARY KEY, label VARCHAR(100) NOT NULL, sort_order INT DEFAULT 0)");
    $pdo->exec("CREATE TABLE IF NOT EXISTS menus (menu_id VARCHAR(100) PRIMARY KEY, menu_name VARCHAR(100) NOT NULL, category_id VARCHAR(50) DEFAULT 'Umum', sort_order INT DEFAULT 0, icon VARCHAR(50) DEFAULT 'circle', link VARCHAR(255) DEFAULT '#')");
    
    // Upgrade schema jika kolom link belum ada
    try {
        $pdo->exec("ALTER TABLE menus ADD COLUMN link VARCHAR(255) DEFAULT '#'");
    } catch (Exception $e) {}

    $pdo->exec("CREATE TABLE IF NOT EXISTS menu_permissions (id INT AUTO_INCREMENT PRIMARY KEY, role_name VARCHAR(50) NOT NULL, menu_id VARCHAR(100) NOT NULL, is_allowed TINYINT(1) DEFAULT 0, UNIQUE KEY unique_perm (role_name, menu_id))");

    // 2. CEK & SEEDING (Jika data kosong)
    $stmtCatCount = $pdo->query("SELECT COUNT(*) FROM menu_categories");
    if ($stmtCatCount->fetchColumn() == 0) {
        // Seeding Struktur
        $pdo->beginTransaction();
        $sort_cat = 1;
        foreach ($master_structure as $cat_id => $cat_data) {
            $pdo->prepare("INSERT INTO menu_categories (category_id, label, sort_order) VALUES (?, ?, ?)")->execute([$cat_id, $cat_data['label'], $sort_cat++]);
            $sort_menu = 1;
            foreach ($cat_data['items'] as $item) {
                $pdo->prepare("INSERT INTO menus (menu_id, menu_name, category_id, sort_order, icon, link) VALUES (?, ?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE category_id = VALUES(category_id), sort_order = VALUES(sort_order), icon = VALUES(icon), link = VALUES(link)")->execute([$item['id'], $item['label'], $cat_id, $sort_menu++, $item['icon'], $item['link'] ?? '#']);
            }
        }
        
        // Seeding Permissions (Hanya jika tabel permission juga kosong)
        $stmtPermCount = $pdo->query("SELECT COUNT(*) FROM menu_permissions");
        if ($stmtPermCount->fetchColumn() == 0) {
            $stmtIns = $pdo->prepare("INSERT IGNORE INTO menu_permissions (role_name, menu_id, is_allowed) VALUES (?, ?, 1)");
            foreach ($master_permissions as $role => $menus) {
                foreach ($menus as $menu_id) {
                    $stmtIns->execute([$role, $menu_id]);
                }
            }
        }
        $pdo->commit();
    }
    
    // 2.5. SYNC MENUS (AUTO-HEALING)
    // Memastikan semua menu yang ada di kodingan ($master_structure) masuk ke database
    $stmtSync = $pdo->prepare("INSERT INTO menus (menu_id, menu_name, category_id, icon, link) VALUES (?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE icon=VALUES(icon), link=VALUES(link)"); // Hapus menu_name agar edit manual user tetap ada
    
    foreach ($master_structure as $cat_id => $cat_data) {
        foreach ($cat_data['items'] as $item) {
            $link = $item['link'] ?? '#';
            $icon = $item['icon'] ?? 'circle';
            $stmtSync->execute([$item['id'], $item['label'], $cat_id, $icon, $link]);
        }
    }

    // 2.6. SYNC MISSING PERMISSIONS (AUTO-HEALING FOR NEW FEATURES)
    // Memastikan menu baru mendapatkan hak akses default jika belum ada di database
    $new_menus_check = ['navJurnalMengajar', 'navRiwayatMengajar', 'navRekapJurnal', 'navRekapAbsensi', 'navGrafikAbsensi'];
    $stmtCheckPerm = $pdo->prepare("SELECT COUNT(*) FROM menu_permissions WHERE menu_id = ?");
    $stmtInsertPerm = $pdo->prepare("INSERT IGNORE INTO menu_permissions (role_name, menu_id, is_allowed) VALUES (?, ?, 1)");

    foreach ($new_menus_check as $nm) {
        $stmtCheckPerm->execute([$nm]);
        if ($stmtCheckPerm->fetchColumn() == 0) {
            // Menu ini belum ada permission-nya. Tambahkan default dari $master_permissions.
            foreach ($master_permissions as $role => $menus) {
                if (in_array($nm, $menus)) {
                    $stmtInsertPerm->execute([$role, $nm]);
                }
            }
        }
    }

    // 3. AMBIL DATA DARI DATABASE
    // A. Struktur Menu
    $stmtCats = $pdo->query("SELECT * FROM menu_categories ORDER BY sort_order ASC");
    while ($cat = $stmtCats->fetch(PDO::FETCH_ASSOC)) {
        $categories[$cat['category_id']] = ['label' => $cat['label'], 'menus' => []];
    }
    
    // Tentukan Kategori Target User (Berdasarkan role prioritas tertinggi)
    $user_target_cat = null;
    // Urutan prioritas pengecekan role
    $role_priority = ['Ketua Yayasan', 'Kepala Sekolah', 'Kepala Asrama Putra', 'Kepala Asrama Putri', 'Sekretaris Yayasan', 'Bendahara Yayasan', 'Admin Sekolah', 'Musyrif', 'Ustadz'];
    
    // Cari role user yang paling tinggi prioritasnya
    foreach ($role_priority as $p_role) {
        if (in_array($p_role, $roles) && isset($role_to_cat_map[$p_role])) {
            $user_target_cat = $role_to_cat_map[$p_role];
            break;
        }
    }
    // Jika tidak ada di prioritas, ambil role pertama
    if (!$user_target_cat && !empty($roles) && isset($role_to_cat_map[$roles[0]])) {
        $user_target_cat = $role_to_cat_map[$roles[0]];
    }

    $stmtMenus = $pdo->query("SELECT menu_id, category_id, icon, menu_name, link FROM menus ORDER BY sort_order ASC");
    while ($menu = $stmtMenus->fetch(PDO::FETCH_ASSOC)) {
        $cat_id = $menu['category_id'];

        // LOGIKA MENU CERDAS: Pindahkan menu ke kategori user jika menu tersebut fleksibel
        if ($user_target_cat && in_array($menu['menu_id'], $contextual_menus)) {
            // Cek apakah kategori target valid
            if (isset($categories[$user_target_cat])) {
                $cat_id = $user_target_cat;
            }
        }

        if (isset($categories[$cat_id])) {
            $categories[$cat_id]['menus'][] = $menu['menu_id'];
        }
        $menu_names[$menu['menu_id']] = $menu['menu_name'];
        $menu_icons[$menu['menu_id']] = $menu['icon'];
        $menu_links[$menu['menu_id']] = $menu['link'];
    }

    // B. Izin User
    if (!empty($roles)) {
        $placeholders = str_repeat('?,', count($roles) - 1) . '?';
        $sql = "SELECT DISTINCT menu_id FROM menu_permissions WHERE role_name IN ($placeholders) AND is_allowed = 1";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($roles);
        $allowed_menus = $stmt->fetchAll(PDO::FETCH_COLUMN);
    }

} catch (Exception $e) {
    // SILENT FAIL: Jika DB error, biarkan variabel kosong, nanti diisi fallback
}

// 4. JURUS PAMUNGKAS: FALLBACK JIKA DATA KOSONG
// Jika DB gagal memberikan struktur (misal tabel corrupt atau query error), pakai hardcoded.
if (empty($categories)) {
    foreach ($master_structure as $cat_id => $cat_data) {
        $categories[$cat_id] = ['label' => $cat_data['label'], 'menus' => []];
        foreach ($cat_data['items'] as $item) {
            $categories[$cat_id]['menus'][] = $item['id'];
            $menu_names[$item['id']] = $item['label'];
            $menu_icons[$item['id']] = $item['icon'];
            $menu_links[$item['id']] = $item['link'] ?? '#';
        }
    }
}

// Jika DB gagal memberikan izin (atau user belum disetting), pakai default berdasarkan role.
if (empty($allowed_menus)) {
    foreach ($roles as $role) {
        if (isset($master_permissions[$role])) {
            $allowed_menus = array_merge($allowed_menus, $master_permissions[$role]);
        }
    }
    // Failsafe Ketua Yayasan
    if (in_array('Ketua Yayasan', $roles)) {
        $allowed_menus[] = 'navMenuManagement';
        $allowed_menus[] = 'navUserCredentials';
    }
    
    // JURUS PAMUNGKAS 2: Jika user belum punya role (User Baru), beri akses dasar
    if (empty($allowed_menus)) {
        $allowed_menus = ['navDashboard', 'navProfil'];
    }
    
    $allowed_menus = array_unique($allowed_menus);
}

// 5. KIRIM RESPONSE
echo json_encode([
    'success' => true,
    'permissions' => array_values($allowed_menus),
    'categories' => $categories,
    'menu_names' => $menu_names,
    'menu_icons' => $menu_icons,
    'menu_links' => $menu_links
]);
?>