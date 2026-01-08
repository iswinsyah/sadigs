<?php
header('Content-Type: application/json');
require_once 'db_connect.php';

if (session_status() === PHP_SESSION_NONE) session_start();

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$user_id = $_SESSION['user_id'];
$roles = $_SESSION['roles'] ?? [];

try {
    $pdo = getDBConnection();

    // ==================================================================================
    // 1. AUTO-FIX & SEEDING (Agar Dashboard tidak kosong jika menu_manager belum dibuka)
    // ==================================================================================
    
    // Pastikan tabel ada
    $pdo->exec("CREATE TABLE IF NOT EXISTS menu_categories (
        category_id VARCHAR(50) PRIMARY KEY,
        label VARCHAR(100) NOT NULL,
        sort_order INT DEFAULT 0
    )");
    
    $pdo->exec("CREATE TABLE IF NOT EXISTS menus (
        menu_id VARCHAR(100) PRIMARY KEY,
        menu_name VARCHAR(100) NOT NULL,
        category_id VARCHAR(50) DEFAULT 'Umum',
        sort_order INT DEFAULT 0,
        icon VARCHAR(50) DEFAULT 'circle'
    )");

    // Cek apakah kosong, jika ya, isi default
    $stmtCatCount = $pdo->query("SELECT COUNT(*) FROM menu_categories");
    if ($stmtCatCount->fetchColumn() == 0) {
        // Struktur Default (Sama seperti di menu_manager.php)
        $initial_structure = [
            'Umum' => ['label' => '1. UMUM', 'items' => [['id' => 'navDashboard', 'label' => 'Dashboard', 'icon' => 'layout-dashboard'], ['id' => 'navProfil', 'label' => 'Profil Saya', 'icon' => 'user'], ['id' => 'navKalender', 'label' => 'Kalender Pendidikan', 'icon' => 'calendar'], ['id' => 'navJadwalPelajaran', 'label' => 'Jadwal Pelajaran', 'icon' => 'calendar-days']]],
            'ManajemenYayasan' => ['label' => '2. MANAJEMEN YAYASAN', 'items' => [['id' => 'navYayasanWidget', 'label' => 'Ringkasan Manajemen', 'icon' => 'activity']]],
            'KetuaYayasan' => ['label' => '3. KETUA YAYASAN', 'items' => [['id' => 'navMenuManagement', 'label' => 'Manajemen Akses', 'icon' => 'lock'], ['id' => 'navUserCredentials', 'label' => 'Data Akun Pengguna', 'icon' => 'key']]],
            'SekretarisYayasan' => ['label' => '4. SEKRETARIS YAYASAN', 'items' => [['id' => 'navVerifikasi', 'label' => 'Verifikasi Pengguna', 'icon' => 'user-check'], ['id' => 'navQuota', 'label' => 'Atur Kuota Peran', 'icon' => 'users'], ['id' => 'navCalendarSettings', 'label' => 'Atur Kalender', 'icon' => 'calendar-cog']]],
            'BendaharaYayasan' => ['label' => '5. BENDAHARA YAYASAN', 'items' => []],
            'ManajemenSekolah' => ['label' => '6. MANAJEMEN SEKOLAH', 'items' => [['id' => 'navBukuIndukSantri', 'label' => 'Buku Induk Santri', 'icon' => 'book'], ['id' => 'navAturKurikulum', 'label' => 'Atur Kurikulum', 'icon' => 'book-open-check']]],
            'KepalaSekolah' => ['label' => '7. KEPALA SEKOLAH', 'items' => [['id' => 'navPenilaianKinerja', 'label' => 'Penilaian Kinerja', 'icon' => 'award'], ['id' => 'navMonitoringAkademik', 'label' => 'Monitoring Akademik', 'icon' => 'monitor-check'], ['id' => 'navValidasiIzin', 'label' => 'Validasi Izin', 'icon' => 'check-square'], ['id' => 'navDaftarIzin', 'label' => 'Daftar Izin', 'icon' => 'list-checks'], ['id' => 'navValidasiPeraturan', 'label' => 'Validasi Peraturan', 'icon' => 'gavel'], ['id' => 'navBuatPeraturan', 'label' => 'Terbitkan Peraturan', 'icon' => 'megaphone']]],
            'KepalaAsrama' => ['label' => '8. KEPALA ASRAMA', 'items' => [['id' => 'navOnLeaveList', 'label' => 'Santri Sedang Pulang', 'icon' => 'user-minus']]],
            'SekretarisSekolah' => ['label' => '9. SEKRETARIS SEKOLAH', 'items' => []],
            'BendaharaSekolah' => ['label' => '10. BENDAHARA SEKOLAH', 'items' => [['id' => 'navValidasiPembayaran', 'label' => 'Validasi Pembayaran', 'icon' => 'check-circle-2'], ['id' => 'navTabelPembayaran', 'label' => 'Data Pembayaran', 'icon' => 'banknote'], ['id' => 'navRekapPembayaran', 'label' => 'Rekap Keuangan', 'icon' => 'pie-chart'], ['id' => 'navPocketMoneyValidation', 'label' => 'Validasi Uang Saku', 'icon' => 'check-circle-2'], ['id' => 'navFormulirTransaksi', 'label' => 'Form Transaksi Harian', 'icon' => 'pen-tool'], ['id' => 'navTabelTransaksi', 'label' => 'Buku Transaksi Harian', 'icon' => 'book']]],
            'Kepegawaian' => ['label' => '11. ADMINISTRASI PEGAWAI', 'items' => [['id' => 'navAbsensi', 'label' => 'Absensi Pegawai', 'icon' => 'map-pin'], ['id' => 'navBiodataPegawai', 'label' => 'Biodata Pegawai', 'icon' => 'file-badge'], ['id' => 'navIzinPegawai', 'label' => 'Formulir Izin', 'icon' => 'file-edit'], ['id' => 'navBukuIndukPegawai', 'label' => 'Buku Induk Pegawai', 'icon' => 'book-open'], ['id' => 'navRapat', 'label' => 'Undang Rapat', 'icon' => 'mail'], ['id' => 'navJadwalRapat', 'label' => 'Jadwal Rapat', 'icon' => 'calendar-clock']]],
            'Ustadz' => ['label' => '12. USTADZ', 'items' => [['id' => 'navRppGenerator', 'label' => 'Generator Modul Ajar', 'icon' => 'brain-circuit'], ['id' => 'navRppAlbum', 'label' => 'Album Perangkat Ajar', 'icon' => 'folder-open'], ['id' => 'navPerencanaanAkademik', 'label' => 'Buku Kerja Ustadz', 'icon' => 'book-check'], ['id' => 'navKetersediaanMengajar', 'label' => 'Kesediaan Mengajar', 'icon' => 'clock'], ['id' => 'navInputNilai', 'label' => 'Input Nilai Rapot', 'icon' => 'graduation-cap']]],
            'Musyrif' => ['label' => '13. MUSYRIF', 'items' => [['id' => 'navMentoring', 'label' => 'Kelompok Mentoring', 'icon' => 'users-round'], ['id' => 'navInputTahfizh', 'label' => 'Input Tahfizh', 'icon' => 'book-marked'], ['id' => 'navValidasiIbadah', 'label' => 'Validasi Ibadah', 'icon' => 'check-square'], ['id' => 'navGuardianLeaveValidation', 'label' => 'Validasi Izin Walisantri', 'icon' => 'mail-check'], ['id' => 'navMusyrifPocketMoney', 'label' => 'Riwayat Deposit Santri', 'icon' => 'archive'], ['id' => 'navMusyrifWithdrawalValidation', 'label' => 'Validasi Penarikan', 'icon' => 'check-square']]],
            'Santri' => ['label' => '14. SANTRI', 'items' => [['id' => 'navBiodataSantri', 'label' => 'Biodata Santri', 'icon' => 'book-user'], ['id' => 'navIbadahHarian', 'label' => 'Laporan Ibadah Harian', 'icon' => 'notebook-pen'], ['id' => 'navSantriPocketMoney', 'label' => 'Uang Saku Saya', 'icon' => 'landmark']]],
            'Walisantri' => ['label' => '15. WALISANTRI', 'items' => [['id' => 'navRekapIbadahAnak', 'label' => 'Rekap Ibadah Anak', 'icon' => 'clipboard-check'], ['id' => 'navViewTahfizh', 'label' => 'Laporan Tahfizh', 'icon' => 'book-open-check'], ['id' => 'navIzinWalisantri', 'label' => 'Izin Walisantri', 'icon' => 'mail-question'], ['id' => 'navPocketMoneyDeposit', 'label' => 'Deposit Uang Saku', 'icon' => 'wallet'], ['id' => 'navMonitoringAnak', 'label' => 'Monitoring Perkembangan', 'icon' => 'activity'], ['id' => 'navFormulirPembayaran', 'label' => 'Formulir Pembayaran', 'icon' => 'credit-card']]]
        ];

        $pdo->beginTransaction();
        try {
            $sort_cat = 1;
            foreach ($initial_structure as $cat_id => $cat_data) {
                $pdo->prepare("INSERT INTO menu_categories (category_id, label, sort_order) VALUES (?, ?, ?)")->execute([$cat_id, $cat_data['label'], $sort_cat++]);
                $sort_menu = 1;
                foreach ($cat_data['items'] as $item) {
                    $pdo->prepare("INSERT INTO menus (menu_id, menu_name, category_id, sort_order, icon) VALUES (?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE category_id = VALUES(category_id), sort_order = VALUES(sort_order), icon = VALUES(icon)")->execute([$item['id'], $item['label'], $cat_id, $sort_menu++, $item['icon']]);
                }
            }
            $pdo->commit();
        } catch (Exception $e) { $pdo->rollBack(); }
    }
    // ==================================================================================

    // 2. Ambil Struktur Menu (Categories & Menus) dari Database
    $categories = [];
    $stmtCats = $pdo->query("SELECT * FROM menu_categories ORDER BY sort_order ASC");
    while ($cat = $stmtCats->fetch(PDO::FETCH_ASSOC)) {
        $categories[$cat['category_id']] = [
            'label' => $cat['label'],
            'menus' => []
        ];
    }

    $stmtMenus = $pdo->query("SELECT menu_id, category_id, icon, menu_name FROM menus ORDER BY sort_order ASC");
    $menu_names = [];
    $menu_icons = [];
    
    while ($menu = $stmtMenus->fetch(PDO::FETCH_ASSOC)) {
        if (isset($categories[$menu['category_id']])) {
            $categories[$menu['category_id']]['menus'][] = $menu['menu_id'];
        }
        $menu_names[$menu['menu_id']] = $menu['menu_name'];
        $menu_icons[$menu['menu_id']] = $menu['icon'];
    }

    // 3. Tentukan Izin User Saat Ini
    $allowed_menus = [];

    // A. Izin berdasarkan Role
    if (!empty($roles)) {
        $placeholders = str_repeat('?,', count($roles) - 1) . '?';
        $sql = "SELECT DISTINCT menu_id FROM menu_permissions WHERE role_name IN ($placeholders) AND is_allowed = 1";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($roles);
        $allowed_menus = $stmt->fetchAll(PDO::FETCH_COLUMN);
    }

    // B. Failsafe untuk Ketua Yayasan (Selalu punya akses ke Manajemen Akses)
    if (in_array('Ketua Yayasan', $roles)) {
        if (!in_array('navMenuManagement', $allowed_menus)) $allowed_menus[] = 'navMenuManagement';
        if (!in_array('navUserCredentials', $allowed_menus)) $allowed_menus[] = 'navUserCredentials';
    }

    echo json_encode([
        'success' => true,
        'permissions' => array_values(array_unique($allowed_menus)),
        'categories' => $categories,
        'menu_names' => $menu_names,
        'menu_icons' => $menu_icons
    ]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>