<?php
// API: Manage Menu Permissions (Get Matrix & Update)
// Matikan error display agar tidak merusak JSON output
ini_set('display_errors', 0);
error_reporting(0);
ob_start(); // Mulai buffer output
header('Content-Type: application/json');
require_once 'db_connect.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id'])) {
    sendJSONResponse(['success' => false, 'message' => 'Unauthorized'], 401);
    exit;
}

try {
$pdo = getDBConnection();

// --- AUTO-FIX: STANDARISASI KOLOM DATABASE ---
// Mengatasi error "Unknown column 'is_allowed'" dengan mengubah 'can_view' menjadi 'is_allowed'
try {
    $check = $pdo->query("SHOW COLUMNS FROM menu_permissions LIKE 'can_view'");
    if ($check->rowCount() > 0) {
        $pdo->exec("ALTER TABLE menu_permissions CHANGE COLUMN can_view is_allowed TINYINT(1) DEFAULT 0");
    }
} catch (Exception $e) {
    // Abaikan error (misal tabel belum ada)
}

// --- AUTO-FIX: TAMBAH KOLOM UNTUK STRUKTUR DINAMIS ---
// Menambahkan kolom category_id, sort_order, dan icon ke tabel menus
try {
    $pdo->exec("ALTER TABLE menus ADD COLUMN category_id VARCHAR(50) DEFAULT 'Umum', ADD COLUMN sort_order INT DEFAULT 0, ADD COLUMN icon VARCHAR(50) DEFAULT 'circle'");
} catch (Exception $e) { /* Kolom mungkin sudah ada */ }

// ---------------------------------------------

// --- DARURAT: RESET IZIN VIA URL ---
if (isset($_GET['reset_now'])) {
    ob_end_clean(); header('Content-Type: text/html');
    $pdo->exec("TRUNCATE TABLE menu_permissions");
    $defaults = [
        'Ketua Yayasan' => ['navDashboard', 'navProfil', 'navKalender', 'navVerifikasi', 'navQuota', 'navCalendarSettings', 'navMenuManagement', 'navAbsensi', 'navBiodataPegawai', 'navBukuIndukPegawai', 'navIzinPegawai', 'navValidasiIzin', 'navDaftarIzin', 'navRapat', 'navJadwalRapat', 'navRppGenerator', 'navRppAlbum', 'navBiodataSantri', 'navBukuIndukSantri', 'navIbadahHarian', 'navValidasiIbadah', 'navGuardianLeaveValidation', 'navOnLeaveList', 'navRekapIbadahAnak', 'navInputTahfizh', 'navViewTahfizh', 'navMentoring', 'navIzinWalisantri', 'navMusyrifPocketMoney', 'navSantriPocketMoney', 'navMusyrifWithdrawalValidation', 'navFormulirPembayaran', 'navValidasiPembayaran', 'navTabelPembayaran', 'navRekapPembayaran', 'navPocketMoneyDeposit', 'navPocketMoneyValidation', 'navFormulirTransaksi', 'navTabelTransaksi'],
        'Bendahara Yayasan' => ['navDashboard', 'navProfil', 'navKalender', 'navValidasiPembayaran', 'navTabelPembayaran', 'navRekapPembayaran', 'navPocketMoneyValidation', 'navTabelTransaksi'],
        'Musyrif' => ['navDashboard', 'navProfil', 'navKalender', 'navAbsensi', 'navIzinPegawai', 'navJadwalRapat', 'navValidasiIbadah', 'navGuardianLeaveValidation', 'navOnLeaveList', 'navInputTahfizh', 'navMentoring', 'navMusyrifPocketMoney', 'navMusyrifWithdrawalValidation'],
        'Musyrifah' => ['navDashboard', 'navProfil', 'navKalender', 'navAbsensi', 'navIzinPegawai', 'navJadwalRapat', 'navValidasiIbadah', 'navGuardianLeaveValidation', 'navOnLeaveList', 'navInputTahfizh', 'navMentoring', 'navMusyrifPocketMoney', 'navMusyrifWithdrawalValidation'],
        'Walisantri' => ['navDashboard', 'navProfil', 'navKalender', 'navRekapIbadahAnak', 'navViewTahfizh', 'navIzinWalisantri', 'navPocketMoneyDeposit', 'navMonitoringAnak'],
        'Santri Rijal' => ['navDashboard', 'navProfil', 'navKalender', 'navBiodataSantri', 'navIbadahHarian', 'navViewTahfizh', 'navSantriPocketMoney'],
        'Santri Nisa\'' => ['navDashboard', 'navProfil', 'navKalender', 'navBiodataSantri', 'navIbadahHarian', 'navViewTahfizh', 'navSantriPocketMoney'],
    ];
    $stmt = $pdo->prepare("INSERT INTO menu_permissions (role_name, menu_id, is_allowed) VALUES (?, ?, 1)");
    foreach ($defaults as $role => $menus) { foreach ($menus as $menu) { $stmt->execute([$role, $menu]); } }
    echo "<h1 style='color:green'>✅ RESET BERHASIL!</h1><p>Menu telah dipulihkan. Silakan kembali ke Dashboard.</p>";
    exit;
}
// -----------------------------------

// 1. Pastikan tabel menu_permissions ada
$pdo->exec("CREATE TABLE IF NOT EXISTS menu_permissions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    role_name VARCHAR(50) NOT NULL,
    menu_id VARCHAR(100) NOT NULL,
    is_allowed TINYINT(1) DEFAULT 0,
    UNIQUE KEY unique_perm (role_name, menu_id)
)");

// 1.b Pastikan tabel categories ada
$pdo->exec("CREATE TABLE IF NOT EXISTS menu_categories (
    category_id VARCHAR(50) PRIMARY KEY,
    label VARCHAR(100) NOT NULL,
    sort_order INT DEFAULT 0
)");


// --- KUNCI PENGAMAN (FAILSAFE) ---
// Pastikan Ketua Yayasan SELALU bisa mengakses halaman Manajemen Akses.
$stmt_failsafe = $pdo->prepare("INSERT INTO menu_permissions (role_name, menu_id, is_allowed) VALUES ('Ketua Yayasan', 'navMenuManagement', 1) ON DUPLICATE KEY UPDATE is_allowed = 1");
$stmt_failsafe->execute();
// --- AKHIR KUNCI PENGAMAN ---


// --- DEFINISI STRUKTUR MASTER (SUMBER KEBENARAN) ---
// Struktur ini akan selalu disinkronkan ke database setiap kali halaman dibuka.
// Pastikan variabel ini bernama $master_structure dan berada di luar blok IF manapun.
$master_structure = [
        'Umum' => [
            'label' => '1. UMUM',
            'items' => [
                ['id' => 'navDashboard', 'label' => 'Dashboard', 'icon' => 'layout-dashboard'],
                ['id' => 'navProfil', 'label' => 'Profil Saya', 'icon' => 'user'],
                ['id' => 'navKalender', 'label' => 'Kalender Pendidikan', 'icon' => 'calendar'],
                ['id' => 'navJadwalPelajaran', 'label' => 'Jadwal Pelajaran', 'icon' => 'calendar-days']
            ]
        ],
        'ManajemenYayasan' => [
            'label' => '2. MANAJEMEN YAYASAN',
            'items' => [
                ['id' => 'navYayasanWidget', 'label' => 'Ringkasan Manajemen', 'icon' => 'activity'] // Widget khusus
            ]
        ],
        'KetuaYayasan' => [
            'label' => '3. KETUA YAYASAN',
            'items' => [
                ['id' => 'navMenuManagement', 'label' => 'Formulir Manajemen Akses', 'icon' => 'lock'],
                ['id' => 'navUserCredentials', 'label' => 'Data Akun Pengguna', 'icon' => 'key']
            ]
        ],
        'SekretarisYayasan' => [
            'label' => '4. SEKRETARIS YAYASAN',
            'items' => [
                ['id' => 'navUserCredentials', 'label' => 'Manajemen Pengguna', 'icon' => 'users'],
                ['id' => 'navQuota', 'label' => 'Formulir Atur Kuota Peran', 'icon' => 'users'],
                ['id' => 'navCalendarSettings', 'label' => 'Formulir Atur Kalender', 'icon' => 'calendar-cog']
            ]
        ],
        'BendaharaYayasan' => ['label' => '5. BENDAHARA YAYASAN', 'items' => []],
        'ManajemenSekolah' => [
            'label' => '6. MANAJEMEN SEKOLAH',
            'items' => [
                ['id' => 'navBukuIndukSantri', 'label' => 'Buku Induk Santri', 'icon' => 'book'],
                ['id' => 'navManajemenKelas', 'label' => 'Manajemen Kelas', 'icon' => 'users'],
                ['id' => 'navAturKurikulum', 'label' => 'Formulir Atur Kurikulum', 'icon' => 'book-open-check']
            ]
        ],
        'KepalaSekolah' => [
            'label' => '7. KEPALA SEKOLAH',
            'items' => [
                ['id' => 'navPenilaianKinerja', 'label' => 'Penilaian Kinerja', 'icon' => 'award'],
                ['id' => 'navMonitoringAkademik', 'label' => 'Monitoring Akademik', 'icon' => 'monitor-check'],
                ['id' => 'navValidasiIzin', 'label' => 'Validasi Izin', 'icon' => 'check-square'],
                ['id' => 'navDaftarIzin', 'label' => 'Daftar Izin', 'icon' => 'list-checks'],
                ['id' => 'navValidasiPeraturan', 'label' => 'Validasi Peraturan', 'icon' => 'gavel'],
                ['id' => 'navBuatPeraturan', 'label' => 'Formulir Terbitkan Peraturan', 'icon' => 'megaphone']
            ]
        ],
        'KepalaAsrama' => [
            'label' => '8. KEPALA ASRAMA',
            'items' => [['id' => 'navOnLeaveList', 'label' => 'Santri Sedang Pulang', 'icon' => 'user-minus']]
        ],
        'AdminSekolah' => ['label' => '9. ADMIN SEKOLAH', 'items' => []],
        'SekretarisSekolah' => ['label' => '10. SEKRETARIS SEKOLAH', 'items' => []],
        'BendaharaSekolah' => [
            'label' => '11. BENDAHARA SEKOLAH',
            'items' => [
                ['id' => 'navValidasiPembayaran', 'label' => 'Validasi Pembayaran', 'icon' => 'check-circle-2'],
                ['id' => 'navTabelPembayaran', 'label' => 'Data Pembayaran', 'icon' => 'banknote'],
                ['id' => 'navRekapPembayaran', 'label' => 'Rekap Keuangan', 'icon' => 'pie-chart'],
                ['id' => 'navPocketMoneyValidation', 'label' => 'Validasi Uang Saku', 'icon' => 'check-circle-2'],
                ['id' => 'navFormulirTransaksi', 'label' => 'Formulir Transaksi Harian', 'icon' => 'pen-tool'],
                ['id' => 'navTabelTransaksi', 'label' => 'Buku Transaksi Harian', 'icon' => 'book']
            ]
        ],
        'Kepegawaian' => [
            'label' => '12. ADMINISTRASI PEGAWAI',
            'items' => [
                ['id' => 'navAbsensi', 'label' => 'Absensi Pegawai', 'icon' => 'map-pin'],
                ['id' => 'navBiodataPegawai', 'label' => 'Biodata Pegawai', 'icon' => 'file-badge'],
                ['id' => 'navIzinPegawai', 'label' => 'Formulir Izin', 'icon' => 'file-edit'],
                ['id' => 'navBukuIndukPegawai', 'label' => 'Buku Induk Pegawai', 'icon' => 'book-open'],
                ['id' => 'navRapat', 'label' => 'Formulir Undang Rapat', 'icon' => 'mail'],
                ['id' => 'navJadwalRapat', 'label' => 'Jadwal Rapat', 'icon' => 'calendar-clock']
            ]
        ],
        'Ustadz' => [
            'label' => '13. USTADZ',
            'items' => [
                ['id' => 'navRppGenerator', 'label' => 'Formulir Generator Modul Ajar', 'icon' => 'brain-circuit'],
                ['id' => 'navRppAlbum', 'label' => 'Album Perangkat Ajar', 'icon' => 'folder-open'],
                ['id' => 'navPerencanaanAkademik', 'label' => 'Buku Kerja Ustadz', 'icon' => 'book-check'],
                ['id' => 'navKetersediaanMengajar', 'label' => 'Formulir Kesediaan Mengajar', 'icon' => 'clock'],
                ['id' => 'navInputNilai', 'label' => 'Formulir Input Nilai Rapot', 'icon' => 'graduation-cap']
            ]
        ],
        'Musyrif' => [
            'label' => '14. MUSYRIF',
            'items' => [
                ['id' => 'navMusyrifWidget', 'label' => 'Ringkasan Musyrif', 'icon' => 'activity'], // Widget Dashboard
                ['id' => 'navMentoring', 'label' => 'Kelompok Mentoring', 'icon' => 'users-round'],
                ['id' => 'navInputTahfizh', 'label' => 'Formulir Input Tahfizh', 'icon' => 'book-marked'],
                ['id' => 'navValidasiIbadah', 'label' => 'Validasi Ibadah', 'icon' => 'check-square'],
                ['id' => 'navGuardianLeaveValidation', 'label' => 'Validasi Izin Walisantri', 'icon' => 'mail-check'],
                ['id' => 'navMusyrifPocketMoney', 'label' => 'Riwayat Deposit Santri', 'icon' => 'archive'],
                ['id' => 'navMusyrifWithdrawalValidation', 'label' => 'Validasi Penarikan', 'icon' => 'check-square']
            ]
        ],
        'Santri' => [
            'label' => '15. SANTRI',
            'items' => [
                ['id' => 'navBiodataSantri', 'label' => 'Biodata Santri', 'icon' => 'book-user'],
                ['id' => 'navIbadahHarian', 'label' => 'Formulir Laporan Ibadah Harian', 'icon' => 'notebook-pen'],
                ['id' => 'navSantriPocketMoney', 'label' => 'Uang Saku Saya', 'icon' => 'landmark']
            ]
        ],
        'Walisantri' => [
            'label' => '16. WALISANTRI',
            'items' => [
                ['id' => 'navRekapIbadahAnak', 'label' => 'Rekap Ibadah Anak', 'icon' => 'clipboard-check'],
                ['id' => 'navViewTahfizh', 'label' => 'Laporan Tahfizh', 'icon' => 'book-open-check'],
                ['id' => 'navIzinWalisantri', 'label' => 'Formulir Izin Walisantri', 'icon' => 'mail-question'],
                ['id' => 'navPocketMoneyDeposit', 'label' => 'Formulir Deposit Uang Saku', 'icon' => 'wallet'],
                ['id' => 'navMonitoringAnak', 'label' => 'Monitoring Perkembangan', 'icon' => 'activity'],
                ['id' => 'navFormulirPembayaran', 'label' => 'Formulir Pembayaran', 'icon' => 'credit-card']
            ]
        ]
    ];

// --- SYNC DATABASE (AUTO-HEALING) ---
// Memaksa database mengikuti struktur kodingan di atas
    $pdo->beginTransaction();
    try {
        $stmtCat = $pdo->prepare("INSERT INTO menu_categories (category_id, label, sort_order) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE label=VALUES(label), sort_order=VALUES(sort_order)");
        $stmtMenu = $pdo->prepare("INSERT INTO menus (menu_id, menu_name, category_id, sort_order, icon) VALUES (?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE menu_name=VALUES(menu_name), icon=VALUES(icon)");

        $sort_cat = 1;
        foreach ($master_structure as $cat_id => $cat_data) {
            $stmtCat->execute([$cat_id, $cat_data['label'], $sort_cat++]);
            
            $sort_menu = 1;
            foreach ($cat_data['items'] as $item) {
                $stmtMenu->execute([$item['id'], $item['label'], $cat_id, $sort_menu++, $item['icon']]);
            }
        }
        $pdo->commit();
    } catch (Exception $e) {
        $pdo->rollBack();
        // Lanjut saja meski error sync, agar tidak memblokir tampilan
    }

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
        // Ambil daftar peran secara dinamis dari database
        $stmt_roles = $pdo->query("SELECT role_name FROM quota_settings UNION SELECT role_name FROM user_roles");
        $db_roles = $stmt_roles->fetchAll(PDO::FETCH_COLUMN);
        $allRoles = array_unique($db_roles);
        
        // Definisikan urutan custom untuk kolom peran
        $custom_order = [
            'Ketua Yayasan', 'Sekretaris Yayasan', 'Bendahara Yayasan',
            'Kepala Sekolah', 'Admin Sekolah', 'Sekretaris Sekolah', 'Bendahara Sekolah',
            'Kepala Asrama Putra', 'Kepala Asrama Putri',
            'Musyrif', 'Musyrifah', 'Ustadz', 'Ustadzah',
            'Santri Rijal', 'Santri Nisa\'', 'Walisantri'
        ];

        // Buat map untuk pencarian cepat posisi
        $order_map = array_flip($custom_order);

        // Lakukan sorting custom
        usort($allRoles, function($a, $b) use ($order_map) {
            $pos_a = isset($order_map[$a]) ? $order_map[$a] : PHP_INT_MAX;
            $pos_b = isset($order_map[$b]) ? $order_map[$b] : PHP_INT_MAX;

            if ($pos_a == $pos_b) {
                return strcmp($a, $b); // Jika sama (atau keduanya tidak ada di map), sort alfabetis
            }
            return $pos_a - $pos_b;
        });

        // --- AMBIL STRUKTUR DARI DATABASE ---
        $categories = [];
        $stmtCats = $pdo->query("SELECT * FROM menu_categories ORDER BY sort_order ASC");
        while ($cat = $stmtCats->fetch(PDO::FETCH_ASSOC)) {
            $categories[$cat['category_id']] = [
                'label' => $cat['label'],
                'menus' => [] // Akan diisi di bawah
            ];
        }

        // Ambil menu dan masukkan ke kategori yang sesuai
        $stmtMenus = $pdo->query("SELECT menu_id, category_id, icon FROM menus ORDER BY sort_order ASC");
        while ($menu = $stmtMenus->fetch(PDO::FETCH_ASSOC)) {
            if (isset($categories[$menu['category_id']])) {
                $categories[$menu['category_id']]['menus'][] = $menu['menu_id'];
            }
        }

        // Ambil data permission yang ada
        $stmt = $pdo->query("SELECT role_name, menu_id, is_allowed FROM menu_permissions");
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $matrix = [];
        foreach ($rows as $row) {
            if (!isset($matrix[$row['menu_id']])) {
                $matrix[$row['menu_id']] = [];
            }
            $matrix[$row['menu_id']][$row['role_name']] = (int)$row['is_allowed'];
        }

        // Ambil nama menu yang benar dari database
        $stmt_names = $pdo->query("SELECT menu_id, menu_name, icon FROM menus");
        $menu_details = $stmt_names->fetchAll(PDO::FETCH_ASSOC);
        $menu_names = [];
        $menu_icons = [];
        foreach($menu_details as $md) {
            $menu_names[$md['menu_id']] = $md['menu_name'];
            $menu_icons[$md['menu_id']] = $md['icon'];
        }


        ob_clean(); // Bersihkan buffer sebelum kirim JSON
        sendJSONResponse([
            'success' => true, 'roles' => $allRoles, 'categories' => $categories, 
            'matrix' => $matrix, 'menu_names' => $menu_names, 'menu_icons' => $menu_icons
        ]);

} elseif ($method === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    $updates = $input['updates'] ?? [];

        $stmt = $pdo->prepare("INSERT INTO menu_permissions (role_name, menu_id, is_allowed) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE is_allowed = VALUES(is_allowed)");
        foreach ($updates as $update) {
            $stmt->execute([$update['role'], $update['menu'], $update['state']]);
        }

        // HANDLE STRUKTUR UPDATE (DRAG & DROP)
        if (isset($input['structure_updates'])) {
            $pdo->beginTransaction();
            try {
                $stmtUpdateMenu = $pdo->prepare("UPDATE menus SET category_id = ?, sort_order = ? WHERE menu_id = ?");
                foreach ($input['structure_updates'] as $item) {
                    $stmtUpdateMenu->execute([$item['category_id'], $item['sort_order'], $item['menu_id']]);
                }
                $pdo->commit();
            } catch (Exception $e) {
                $pdo->rollBack();
                ob_clean();
                sendJSONResponse(['success' => false, 'message' => 'Gagal menyimpan struktur: ' . $e->getMessage()], 500);
            }
        }

        ob_clean();
        sendJSONResponse(['success' => true]);
}

} catch (Exception $e) {
    ob_clean();
    sendJSONResponse(['success' => false, 'message' => 'Server Error: ' . $e->getMessage()], 500);
}
?>