<?php
// =================================================================
// SADIGS 3.0: SESSION CHECK (CLEAN VERSION)
// =================================================================
ob_start();

// Menggunakan satu sumber koneksi dan fungsi utility
require_once 'db_connect.php';

// --- DARURAT: RESET IZIN VIA SESSION CHECK ---
if (isset($_GET['reset_permissions']) && $_GET['reset_permissions'] == '1') {
    try {
        $pdo = getDBConnection();
        $pdo->exec("TRUNCATE TABLE menu_permissions");
        $defaults = [
            'Ketua Yayasan' => ['navDashboard', 'navProfil', 'navKalender', 'navVerifikasi', 'navQuota', 'navCalendarSettings', 'navMenuManagement', 'navBiodataPegawai', 'navBukuIndukPegawai', 'navValidasiIzin', 'navDaftarIzin', 'navRapat', 'navJadwalRapat', 'navBukuIndukSantri', 'navMentoring', 'navValidasiPembayaran', 'navTabelPembayaran', 'navRekapPembayaran', 'navPocketMoneyValidation', 'navTabelTransaksi'],
            'Bendahara Yayasan' => ['navDashboard', 'navProfil', 'navKalender', 'navValidasiPembayaran', 'navTabelPembayaran', 'navRekapPembayaran', 'navPocketMoneyValidation', 'navTabelTransaksi'],
            'Musyrif' => ['navDashboard', 'navProfil', 'navKalender', 'navAbsensi', 'navIzinPegawai', 'navJadwalRapat', 'navValidasiIbadah', 'navGuardianLeaveValidation', 'navOnLeaveList', 'navInputTahfizh', 'navMentoring', 'navMusyrifPocketMoney', 'navMusyrifWithdrawalValidation'],
            'Musyrifah' => ['navDashboard', 'navProfil', 'navKalender', 'navAbsensi', 'navIzinPegawai', 'navJadwalRapat', 'navValidasiIbadah', 'navGuardianLeaveValidation', 'navOnLeaveList', 'navInputTahfizh', 'navMentoring', 'navMusyrifPocketMoney', 'navMusyrifWithdrawalValidation'],
            'Walisantri' => ['navDashboard', 'navProfil', 'navKalender', 'navRekapIbadahAnak', 'navViewTahfizh', 'navIzinWalisantri', 'navPocketMoneyDeposit'],
            'Santri Rijal' => ['navDashboard', 'navProfil', 'navKalender', 'navBiodataSantri', 'navIbadahHarian', 'navViewTahfizh', 'navSantriPocketMoney'],
            'Santri Nisa\'' => ['navDashboard', 'navProfil', 'navKalender', 'navBiodataSantri', 'navIbadahHarian', 'navViewTahfizh', 'navSantriPocketMoney'],
        ];
        $stmt = $pdo->prepare("INSERT INTO menu_permissions (role_name, menu_id, is_allowed) VALUES (?, ?, 1)");
        foreach ($defaults as $role => $menus) { foreach ($menus as $menu) { $stmt->execute([$role, $menu]); } }
        ob_end_clean(); header('Content-Type: text/html');
        echo "<h1 style='color:green; text-align:center; margin-top:50px;'>✅ RESET BERHASIL!</h1><p style='text-align:center;'>Silakan <a href='../dashboard.html'>kembali ke Dashboard</a>.</p>";
        exit;
    } catch (Exception $e) {
        echo "Error: " . $e->getMessage(); exit;
    }
}
// ---------------------------------------------

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Logika di bawah ini tidak memerlukan koneksi DB, hanya cek sesi yang sudah dibuat saat login
// Namun, dengan require_once 'db_connect.php', kita sudah mendapatkan fungsi sendJSONResponse()
// dan siap jika di masa depan perlu validasi ke database.
if (isset($_SESSION['user_id'], $_SESSION['username'])) {
    
    // Ambil data peran mentah (termasuk status) untuk halaman profil
    $pdo = getDBConnection();
    $stmt = $pdo->prepare("SELECT role_name, status FROM user_roles WHERE user_id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $raw_roles = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // --- AUTO-APPROVE KETUA YAYASAN ---
    // Jika user memiliki peran Ketua Yayasan tapi masih pending, langsung aktifkan.
    foreach ($raw_roles as $r) {
        if ($r['role_name'] === 'Ketua Yayasan' && $r['status'] !== 'approved') {
            $pdo->prepare("UPDATE user_roles SET status = 'approved' WHERE user_id = ? AND role_name = 'Ketua Yayasan'")->execute([$_SESSION['user_id']]);
            // Refresh data roles
            $stmt->execute([$_SESSION['user_id']]); 
            $raw_roles = $stmt->fetchAll(PDO::FETCH_ASSOC);
            break;
        }
    }
    // ----------------------------------

    // Ambil peran yang sudah disetujui untuk otorisasi menu
    $approved_roles = [];
    foreach ($raw_roles as $role) {
        if ($role['status'] === 'approved') {
            $approved_roles[] = $role['role_name'];
        }
    }

    // SINKRONISASI: Perbarui variabel sesi dengan data peran terbaru dari database.
    $_SESSION['roles'] = $approved_roles;

    // Sesi valid, kirimkan data ke dashboard.html
    sendJSONResponse(array(
        'success' => true,
        'user_id' => $_SESSION['user_id'],
        'username' => $_SESSION['username'],
        'roles' => $approved_roles, // Hanya kirim peran yang sudah disetujui
        'raw_roles' => $raw_roles // Kirim data mentah untuk UI profil
    ));
    
} else {
    // Sesi tidak valid atau telah berakhir
    sendJSONResponse(array('success' => false, 'message' => 'Sesi berakhir atau tidak valid.'), 401);
}