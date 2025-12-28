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

    // Summary untuk Yayasan / Bendahara / Kepala Sekolah
    $financeRoles = ['Ketua Yayasan', 'Sekretaris Yayasan', 'Bendahara Yayasan', 'Kepala Sekolah', 'Bendahara Sekolah'];
    if (!empty(array_intersect($roles, $financeRoles))) {
        // Hitung user yang menunggu verifikasi
        $stmt = $pdo->query("SELECT COUNT(DISTINCT user_id) FROM user_roles WHERE status = 'pending'");
        $summary['pending_users_count'] = $stmt->fetchColumn();

        // Hitung pembayaran yang menunggu validasi
        $stmt = $pdo->query("SELECT COUNT(id) FROM payments WHERE status = 'pending'");
        $summary['pending_payments_count'] = $stmt->fetchColumn();
    }

    // Summary untuk Musyrif
    $musyrifRoles = ['Musyrif', 'Musyrifah', 'Kepala Asrama Putra', 'Kepala Asrama Putri'];
     if (!empty(array_intersect($roles, $musyrifRoles))) {
        // Hitung laporan ibadah yang perlu divalidasi
        $stmt = $pdo->prepare("
            SELECT COUNT(r.id) 
            FROM daily_worship_reports r
            JOIN users u ON r.user_id = u.id
            JOIN mentoring_groups mg ON u.id = mg.student_id
            WHERE mg.musyrif_username = ? AND r.validation_status = 'pending'
        ");
        $stmt->execute([$username]);
        $summary['pending_worship_validations'] = $stmt->fetchColumn();

        // Hitung izin walisantri yang perlu divalidasi
        $stmt = $pdo->prepare("SELECT COUNT(id) FROM guardian_leave_requests WHERE musyrif_username = ? AND status = 'pending'");
        $stmt->execute([$username]);
        $summary['pending_guardian_leaves'] = $stmt->fetchColumn();
    }

    sendJSONResponse(['success' => true, 'summary' => $summary]);

} catch (Exception $e) {
    // Jangan kirim detail error ke user, tapi bisa di-log
    error_log('Dashboard Summary Error: ' . $e->getMessage());
    sendJSONResponse(['success' => false, 'message' => 'Gagal mengambil data ringkasan.'], 500);
}
?>