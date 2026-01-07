<?php
// API: Manage Menu Permissions (Get Matrix & Update)
// Matikan error display agar tidak merusak JSON
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

// --- KUNCI PENGAMAN (FAILSAFE) ---
// Pastikan Ketua Yayasan SELALU bisa mengakses halaman Manajemen Akses.
$stmt_failsafe = $pdo->prepare("INSERT INTO menu_permissions (role_name, menu_id, is_allowed) VALUES ('Ketua Yayasan', 'navMenuManagement', 1) ON DUPLICATE KEY UPDATE is_allowed = 1");
$stmt_failsafe->execute();
// --- AKHIR KUNCI PENGAMAN ---


// 2. Seed Default Permission jika tabel kosong (Agar Ketua Yayasan tidak terkunci)
$stmtCount = $pdo->query("SELECT COUNT(*) FROM menu_permissions");
if ($stmtCount->fetchColumn() == 0) {
    // Berikan akses vital ke Ketua Yayasan
    $defaults = [
        ['Ketua Yayasan', 'navMenuManagement'],
        ['Ketua Yayasan', 'navDashboard'],
        ['Ketua Yayasan', 'navVerifikasi'],
        ['Ketua Yayasan', 'navQuota']
    ];
    $stmtInsert = $pdo->prepare("INSERT INTO menu_permissions (role_name, menu_id, is_allowed) VALUES (?, ?, 1)");
    foreach ($defaults as $d) {
        $stmtInsert->execute($d);
    }
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
            'Kepala Sekolah', 'Sekretaris Sekolah', 'Bendahara Sekolah',
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

        // Daftar Menu (Single Source of Truth)
        $categories = [
            'Umum' => [
                'label' => '1. UMUM',
                'menus' => ['navDashboard', 'navProfil', 'navKalender', 'navJadwalPelajaran']
            ],
            'KetuaYayasan' => [
                'label' => '2. KETUA YAYASAN',
                'menus' => ['navMenuManagement', 'navUserCredentials']
            ],
            'SekretarisYayasan' => [
                'label' => '3. SEKRETARIS YAYASAN',
                'menus' => ['navVerifikasi', 'navQuota', 'navCalendarSettings']
            ],
            'BendaharaYayasan' => [
                'label' => '4. BENDAHARA YAYASAN',
                'menus' => []
            ],
            'Kepegawaian' => [
                'label' => '5. KEPEGAWAIAN',
                'menus' => ['navAbsensi', 'navBiodataPegawai', 'navIzinPegawai', 'navBukuIndukPegawai', 'navRapat', 'navJadwalRapat']
            ],
            'KepalaSekolah' => [
                'label' => '6. KEPALA SEKOLAH',
                'menus' => ['navPenilaianKinerja', 'navMonitoringAkademik', 'navValidasiIzin', 'navDaftarIzin', 'navValidasiPeraturan', 'navBuatPeraturan', 'navAturKurikulum']
            ],
            'SekretarisSekolah' => [
                'label' => '7. SEKRETARIS SEKOLAH',
                'menus' => []
            ],
            'BendaharaSekolah' => [
                'label' => '8. BENDAHARA SEKOLAH',
                'menus' => ['navValidasiPembayaran', 'navTabelPembayaran', 'navRekapPembayaran', 'navPocketMoneyValidation', 'navFormulirTransaksi', 'navTabelTransaksi']
            ],
            'AdminSekolah' => [
                'label' => '9. ADMIN SEKOLAH',
                'menus' => ['navBukuIndukSantri']
            ],
            'KepalaAsrama' => [
                'label' => '10. KEPALA ASRAMA',
                'menus' => ['navOnLeaveList']
            ],
            'Musyrif' => [
                'label' => '11. MUSYRIF',
                'menus' => ['navMentoring', 'navInputTahfizh', 'navValidasiIbadah', 'navGuardianLeaveValidation', 'navMusyrifPocketMoney', 'navMusyrifWithdrawalValidation']
            ],
            'Ustadz' => [
                'label' => '12. USTADZ',
                'menus' => ['navRppGenerator', 'navRppAlbum', 'navPerencanaanAkademik', 'navKetersediaanMengajar', 'navInputNilai']
            ],
            'Kesantrian' => [
                'label' => '13. KESANTRIAN',
                'menus' => ['navBiodataSantri', 'navIbadahHarian', 'navSantriPocketMoney']
            ],
            'Walisantri' => [
                'label' => '14. WALISANTRI',
                'menus' => ['navRekapIbadahAnak', 'navViewTahfizh', 'navIzinWalisantri', 'navPocketMoneyDeposit', 'navMonitoringAnak', 'navFormulirPembayaran']
            ]
        ];

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
        $stmt_names = $pdo->query("SELECT menu_id, menu_name FROM menus");
        $menu_names = $stmt_names->fetchAll(PDO::FETCH_KEY_PAIR);


        ob_clean(); // Bersihkan buffer sebelum kirim JSON
        sendJSONResponse(['success' => true, 'roles' => $allRoles, 'categories' => $categories, 'matrix' => $matrix, 'menu_names' => $menu_names]);

} elseif ($method === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    $updates = $input['updates'] ?? [];

        $stmt = $pdo->prepare("INSERT INTO menu_permissions (role_name, menu_id, is_allowed) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE is_allowed = VALUES(is_allowed)");
        foreach ($updates as $update) {
            $stmt->execute([$update['role'], $update['menu'], $update['state']]);
        }
        ob_clean();
        sendJSONResponse(['success' => true]);
}

} catch (Exception $e) {
    ob_clean();
    sendJSONResponse(['success' => false, 'message' => 'Server Error: ' . $e->getMessage()], 500);
}
?>