<?php
header('Content-Type: application/json');
require_once 'db_connect.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id'])) {
    sendJSONResponse(['success' => false, 'message' => 'Unauthorized'], 401);
    exit;
}

$user_id = $_SESSION['user_id'];
$roles = $_SESSION['roles'] ?? [];
$username = $_SESSION['username'];

$summary = [];

try {
    $pdo = getDBConnection();

    // Summary untuk semua staf akademik (Kepsek, Musyrif, Guru, dll)
    $academicRoles = ['Kepala Sekolah', 'Sekretaris Sekolah', 'Bendahara Sekolah', 'Musyrif', 'Musyrifah', 'Kepala Asrama Putra', 'Kepala Asrama Putri', 'Ustadz', 'Ustadzah'];
    if (!empty(array_intersect($roles, $academicRoles))) {
        // Hitung santri yang sedang pulang (izin disetujui dan dalam rentang waktu)
        $stmt = $pdo->query("SELECT COUNT(id) FROM guardian_leave_requests WHERE status = 'approved' AND NOW() BETWEEN start_datetime AND end_datetime");
        $summary['on_leave_count'] = $stmt->fetchColumn();
    }

    // Summary untuk Yayasan / Bendahara / Kepala Sekolah
    $financeRoles = ['Ketua Yayasan', 'Sekretaris Yayasan', 'Bendahara Yayasan', 'Kepala Sekolah', 'Bendahara Sekolah'];
    if (!empty(array_intersect($roles, $financeRoles))) {
        // Hitung user yang menunggu verifikasi
        $stmt = $pdo->query("SELECT COUNT(DISTINCT user_id) FROM user_roles WHERE status = 'pending'");
        $summary['pending_users_count'] = $stmt->fetchColumn();

        // Hitung pembayaran yang menunggu validasi
        $stmt = $pdo->query("SELECT COUNT(id) FROM payments WHERE status = 'pending'");
        $summary['pending_payments_count'] = $stmt->fetchColumn();

        // Hitung deposit uang saku yang menunggu validasi
        $stmt = $pdo->query("SELECT COUNT(id) FROM pocket_money_transactions WHERE status = 'pending' AND transaction_type = 'deposit'");
        $summary['pending_pocket_money_count'] = $stmt->fetchColumn();
    }

    // Summary untuk Musyrif
    $musyrifRoles = ['Musyrif', 'Musyrifah', 'Kepala Asrama Putra', 'Kepala Asrama Putri'];
     if (!empty(array_intersect($roles, $musyrifRoles))) {
        // Hitung laporan ibadah yang perlu divalidasi
        // FIX: Query diperbaiki untuk menggunakan musyrif_id dan join yang benar
        $stmt = $pdo->prepare("
            SELECT COUNT(r.id) 
            FROM daily_worship_reports r
            JOIN mentoring_groups mg ON r.user_id = mg.student_id
            WHERE mg.musyrif_id = ? AND r.validation_status = 'pending'
        ");
        $stmt->execute([$user_id]);
        $summary['pending_worship_validations'] = $stmt->fetchColumn();

        // Hitung izin walisantri yang perlu divalidasi
        // NOTE: Query ini mengasumsikan tabel 'guardian_leave_requests' memiliki kolom 'musyrif_username'
        $stmt = $pdo->prepare("SELECT COUNT(id) FROM guardian_leave_requests WHERE musyrif_username = ? AND status = 'pending'");
        $stmt->execute([$username]);
        $summary['pending_guardian_leaves'] = $stmt->fetchColumn();

        // Hitung penarikan uang saku yang perlu divalidasi
        $stmt = $pdo->prepare("SELECT COUNT(id) FROM pocket_money_transactions WHERE musyrif_id = ? AND status = 'pending' AND transaction_type = 'withdrawal'");
        $stmt->execute([$user_id]);
        $summary['pending_withdrawal_count'] = $stmt->fetchColumn();
    }

    sendJSONResponse(['success' => true, 'summary' => $summary]);

} catch (Exception $e) {
    // Jangan kirim detail error ke user, tapi bisa di-log
    error_log('Dashboard Summary Error: ' . $e->getMessage());
    sendJSONResponse(['success' => false, 'message' => 'Gagal mengambil data ringkasan.'], 500);
}
?>