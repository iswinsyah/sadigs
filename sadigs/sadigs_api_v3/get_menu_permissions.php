<?php
// API: Get Allowed Menus for Current User
// Matikan error display agar JSON valid (PENTING!)
ini_set('display_errors', 0);
error_reporting(0);
header('Content-Type: application/json');
require_once 'db_connect.php';

if (session_status() === PHP_SESSION_NONE) session_start();

if (!isset($_SESSION['user_id'])) {
    sendJSONResponse(['success' => false, 'message' => 'Unauthorized'], 401);
    exit;
}

$roles = $_SESSION['roles'] ?? [];

// Jika roles kosong di sesi, coba ambil ulang dari database (Double Check)
// Dan pastikan minimal ada role 'Guest' jika kosong, agar tidak error
if (empty($roles)) {
    $pdo = getDBConnection();
    $stmt = $pdo->prepare("SELECT role_name FROM user_roles WHERE user_id = ? AND status = 'approved'");
    $stmt->execute([$_SESSION['user_id']]);
    $roles = $stmt->fetchAll(PDO::FETCH_COLUMN);
    $_SESSION['roles'] = $roles; // Update sesi
}

if (empty($roles)) {
    sendJSONResponse(['success' => true, 'permissions' => []]);
    exit;
}

// --- DEFINISI IZIN STANDAR (HARDCODED FALLBACK) ---
// Ini adalah jaring pengaman. Jika database gagal/kosong, sistem akan pakai ini.
$fallback_perms = [
    'Ketua Yayasan' => ['navDashboard', 'navProfil', 'navKalender', 'navVerifikasi', 'navQuota', 'navCalendarSettings', 'navMenuManagement', 'navAbsensi', 'navBiodataPegawai', 'navBukuIndukPegawai', 'navIzinPegawai', 'navValidasiIzin', 'navDaftarIzin', 'navRapat', 'navJadwalRapat', 'navRppGenerator', 'navRppAlbum', 'navBiodataSantri', 'navBukuIndukSantri', 'navIbadahHarian', 'navValidasiIbadah', 'navGuardianLeaveValidation', 'navOnLeaveList', 'navRekapIbadahAnak', 'navInputTahfizh', 'navViewTahfizh', 'navMentoring', 'navIzinWalisantri', 'navMusyrifPocketMoney', 'navSantriPocketMoney', 'navMusyrifWithdrawalValidation', 'navFormulirPembayaran', 'navValidasiPembayaran', 'navTabelPembayaran', 'navRekapPembayaran', 'navPocketMoneyDeposit', 'navPocketMoneyValidation', 'navFormulirTransaksi', 'navTabelTransaksi'],
    'Bendahara Yayasan' => ['navDashboard', 'navProfil', 'navKalender', 'navValidasiPembayaran', 'navTabelPembayaran', 'navRekapPembayaran', 'navPocketMoneyValidation', 'navTabelTransaksi'],
    'Sekretaris Yayasan' => ['navDashboard', 'navProfil', 'navKalender', 'navVerifikasi', 'navQuota', 'navCalendarSettings', 'navBiodataPegawai', 'navBukuIndukPegawai', 'navRapat', 'navJadwalRapat'],
    'Kepala Sekolah' => ['navDashboard', 'navProfil', 'navKalender', 'navAbsensi', 'navBiodataPegawai', 'navBukuIndukPegawai', 'navValidasiIzin', 'navDaftarIzin', 'navRapat', 'navJadwalRapat', 'navRppGenerator', 'navRppAlbum', 'navBiodataSantri', 'navBukuIndukSantri', 'navValidasiIbadah', 'navMentoring', 'navInputNilai'],
    'Musyrif' => ['navDashboard', 'navProfil', 'navKalender', 'navAbsensi', 'navIzinPegawai', 'navJadwalRapat', 'navValidasiIbadah', 'navGuardianLeaveValidation', 'navOnLeaveList', 'navInputTahfizh', 'navInputNilai', 'navMentoring', 'navMusyrifPocketMoney', 'navMusyrifWithdrawalValidation'],
    'Musyrifah' => ['navDashboard', 'navProfil', 'navKalender', 'navAbsensi', 'navIzinPegawai', 'navJadwalRapat', 'navValidasiIbadah', 'navGuardianLeaveValidation', 'navOnLeaveList', 'navInputTahfizh', 'navInputNilai', 'navMentoring', 'navMusyrifPocketMoney', 'navMusyrifWithdrawalValidation'],
    'Walisantri' => ['navDashboard', 'navProfil', 'navKalender', 'navRekapIbadahAnak', 'navViewTahfizh', 'navIzinWalisantri', 'navPocketMoneyDeposit', 'navMonitoringAnak'],
    'Santri Rijal' => ['navDashboard', 'navProfil', 'navKalender', 'navBiodataSantri', 'navIbadahHarian', 'navViewTahfizh', 'navSantriPocketMoney'],
    'Santri Nisa\'' => ['navDashboard', 'navProfil', 'navKalender', 'navBiodataSantri', 'navIbadahHarian', 'navViewTahfizh', 'navSantriPocketMoney'],
    'Ustadz' => ['navDashboard', 'navProfil', 'navKalender', 'navAbsensi', 'navIzinPegawai', 'navJadwalRapat', 'navRppGenerator', 'navRppAlbum', 'navMentoring', 'navInputNilai'],
    'Ustadzah' => ['navDashboard', 'navProfil', 'navKalender', 'navAbsensi', 'navIzinPegawai', 'navJadwalRapat', 'navRppGenerator', 'navRppAlbum', 'navMentoring', 'navInputNilai'],
];

$permissions = [];

try {
    $pdo = getDBConnection();
    
    // PRIORITAS 1: AMBIL DARI DATABASE (UTAMA)
    // Gunakan try-catch khusus query ini agar jika tabel hilang, script tidak mati
    $placeholders = implode(',', array_fill(0, count($roles), '?'));
    $sql = "SELECT DISTINCT menu_id FROM menu_permissions WHERE role_name IN ($placeholders) AND is_allowed = 1";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($roles);
    $db_permissions = $stmt->fetchAll(PDO::FETCH_COLUMN);

    if (!empty($db_permissions)) {
        $permissions = $db_permissions;
    }
} catch (Exception $e) {
    // Database error? Abaikan, kita akan pakai fallback di bawah.
}

// --- LOGIKA PENYELAMAT (RESCUE LOGIC) ---
// PRIORITAS 2: GUNAKAN HARDCODED FALLBACK (CADANGAN)
// Jika permissions kosong (karena DB kosong/error/belum disetting),
// ATAU jika user adalah Ketua Yayasan (kita paksa lengkap),
// maka gabungkan dengan data hardcoded.
if (empty($permissions) || in_array('Ketua Yayasan', $roles)) {
    foreach ($roles as $role) {
        if (isset($fallback_perms[$role])) {
            // Gabungkan array (merge)
            $permissions = array_merge($permissions, $fallback_perms[$role]);
        }
    }
}

// Hapus duplikat
$permissions = array_unique($permissions);
// Pastikan array indexnya rapi (re-index)
$permissions = array_values($permissions);

sendJSONResponse(['success' => true, 'permissions' => $permissions]);
?>