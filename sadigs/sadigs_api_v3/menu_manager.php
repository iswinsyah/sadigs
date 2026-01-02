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
        // Daftar Peran (Sesuai dengan quota.php agar konsisten)
        $allRoles = [
            'Ketua Yayasan', 'Sekretaris Yayasan', 'Bendahara Yayasan',
            'Kepala Sekolah', 'Sekretaris Sekolah', 'Bendahara Sekolah',
            'Kepala Ma\'had', 'Kepala Asrama Putra', 'Kepala Asrama Putri',
            'Musyrif', 'Musyrifah', 'Ustadz', 'Ustadzah',
            'Santri Rijal', 'Santri Nisa\'', 'Walisantri'
        ];

        // Daftar Menu (Single Source of Truth)
        $categories = [
            'Form' => [
                'label' => 'Formulir',
                'menus' => ['navAbsensi', 'navBiodataPegawai', 'navBiodataSantri', 'navCalendarSettings', 'navIbadahHarian', 'navInputTahfizh', 'navInputNilai', 'navBuatPeraturan', 'navProfil', 'navQuota', 'navRapat', 'navIzinPegawai', 'navFormulirPembayaran', 'navFormulirTransaksi', 'navIzinWalisantri', 'navRppGenerator', 'navPocketMoneyDeposit', 'navMenuManagement'],
                'icon' => 'file-pen-line'
            ],
            'Val' => [
                'label' => 'Validasi',
                'menus' => ['navValidasiIbadah', 'navVerifikasi', 'navValidasiPembayaran', 'navValidasiIzin', 'navGuardianLeaveValidation', 'navPocketMoneyValidation', 'navMusyrifWithdrawalValidation', 'navValidasiPeraturan'],
                'icon' => 'check-square'
            ],
            'Tab' => [
                'label' => 'Tabel Data',
                'menus' => ['navDashboard', 'navKalender', 'navJadwalRapat', 'navDaftarIzin', 'navBukuIndukPegawai', 'navBukuIndukSantri', 'navMentoring', 'navTabelPembayaran', 'navTabelTransaksi', 'navRekapIbadahAnak', 'navViewTahfizh', 'navOnLeaveList', 'navRppAlbum', 'navMusyrifPocketMoney', 'navSantriPocketMoney', 'navMonitoringAnak'],
                'icon' => 'table'
            ],
            'Grf' => [
                'label' => 'Grafik',
                'menus' => ['navRekapPembayaran'],
                'icon' => 'bar-chart-2'
            ],
            'Wgt' => [
                'label' => 'Widget Dashboard',
                'menus' => ['navYayasanWidget', 'navMusyrifWidget'],
                'icon' => 'layout-grid'
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

        ob_clean(); // Bersihkan buffer sebelum kirim JSON
        sendJSONResponse(['success' => true, 'roles' => $allRoles, 'categories' => $categories, 'matrix' => $matrix]);

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