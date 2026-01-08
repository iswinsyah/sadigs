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

    // Helper function untuk cek tabel ada atau tidak agar tidak error 500
    function tableExists($pdo, $table) {
        try {
            $result = $pdo->query("SELECT 1 FROM $table LIMIT 1");
            return $result !== false;
        } catch (Exception $e) {
            return false;
        }
    }

    // Summary untuk semua staf akademik (Kepsek, Musyrif, Guru, dll)
    $academicRoles = ['Kepala Sekolah', 'Sekretaris Sekolah', 'Bendahara Sekolah', 'Musyrif', 'Musyrifah', 'Kepala Asrama Putra', 'Kepala Asrama Putri', 'Ustadz', 'Ustadzah'];
    if (!empty(array_intersect($roles, $academicRoles)) && tableExists($pdo, 'guardian_leave_requests')) {
        // Hitung santri yang sedang pulang (izin disetujui dan dalam rentang waktu)
        $stmt = $pdo->query("SELECT COUNT(id) FROM guardian_leave_requests WHERE status = 'approved' AND NOW() BETWEEN start_datetime AND end_datetime");
        $summary['on_leave_count'] = $stmt->fetchColumn();
    }

    // Summary untuk Yayasan / Bendahara / Kepala Sekolah
    $financeRoles = ['Ketua Yayasan', 'Sekretaris Yayasan', 'Bendahara Yayasan', 'Kepala Sekolah', 'Bendahara Sekolah'];
    if (!empty(array_intersect($roles, $financeRoles))) {
        // Hitung user yang menunggu verifikasi
        if (tableExists($pdo, 'user_roles')) {
            $stmt = $pdo->query("SELECT COUNT(DISTINCT user_id) FROM user_roles WHERE status = 'pending'");
            $summary['pending_users_count'] = $stmt->fetchColumn();
        }

        // Hitung pembayaran yang menunggu validasi
        if (tableExists($pdo, 'payments')) {
            $stmt = $pdo->query("SELECT COUNT(id) FROM payments WHERE status = 'pending'");
            $summary['pending_payments_count'] = $stmt->fetchColumn();
        }

        // Hitung deposit uang saku yang menunggu validasi
        if (tableExists($pdo, 'pocket_money_transactions')) {
            $stmt = $pdo->query("SELECT COUNT(id) FROM pocket_money_transactions WHERE status = 'pending' AND transaction_type = 'deposit'");
            $summary['pending_pocket_money_count'] = $stmt->fetchColumn();
        }
    }

    // Summary untuk Musyrif
    $musyrifRoles = ['Musyrif', 'Musyrifah', 'Kepala Asrama Putra', 'Kepala Asrama Putri'];
     if (!empty(array_intersect($roles, $musyrifRoles))) {
        // Hitung laporan ibadah yang perlu divalidasi (Cek tabel dulu)
        if (tableExists($pdo, 'ibadah_harian') && tableExists($pdo, 'mentoring_groups')) {
            $stmt = $pdo->prepare("
                SELECT COUNT(r.id) 
                FROM ibadah_harian r
                JOIN mentoring_groups mg ON r.user_id = mg.student_id
                WHERE mg.musyrif_id = ? AND r.validation_status = 'pending'
            ");
            $stmt->execute([$user_id]);
            $summary['pending_worship_validations'] = $stmt->fetchColumn();
        }

        // Hitung izin walisantri yang perlu divalidasi
        if (tableExists($pdo, 'guardian_leave_requests')) {
            $stmt = $pdo->prepare("SELECT COUNT(id) FROM guardian_leave_requests WHERE musyrif_username = ? AND status = 'pending'");
            $stmt->execute([$username]);
            $summary['pending_guardian_leaves'] = $stmt->fetchColumn();
        }

        // Hitung penarikan uang saku yang perlu divalidasi
        if (tableExists($pdo, 'pocket_money_transactions')) {
            $stmt = $pdo->prepare("SELECT COUNT(id) FROM pocket_money_transactions WHERE musyrif_id = ? AND status = 'pending' AND transaction_type = 'withdrawal'");
            $stmt->execute([$user_id]);
            $summary['pending_withdrawal_count'] = $stmt->fetchColumn();
        }
    }

    sendJSONResponse(['success' => true, 'summary' => $summary]);

} catch (Exception $e) {
    // Jangan kirim detail error ke user, tapi bisa di-log
    error_log('Dashboard Summary Error: ' . $e->getMessage());
    sendJSONResponse(['success' => false, 'message' => 'Gagal mengambil data ringkasan.'], 500);
}
?>