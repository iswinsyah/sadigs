<?php
header('Content-Type: application/json');
require_once 'db_connect.php';

if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['user_id'])) {
    sendJSONResponse(['success' => false, 'message' => 'Unauthorized'], 401);
}

$pdo = getDBConnection();

try {
    // 1. Ringkasan pemasukan harian (30 hari terakhir) untuk grafik
    $stmt_daily = $pdo->prepare("
        SELECT 
            DATE(payment_date) as date, 
            SUM(total_amount) as total 
        FROM payments 
        WHERE status = 'approved' AND payment_date >= CURDATE() - INTERVAL 30 DAY
        GROUP BY DATE(payment_date)
        ORDER BY date ASC
    ");
    $stmt_daily->execute();
    $daily_summary = $stmt_daily->fetchAll(PDO::FETCH_ASSOC);

    // 2. Total pemasukan yang disetujui bulan ini
    $stmt_monthly = $pdo->prepare("
        SELECT SUM(total_amount) as total 
        FROM payments 
        WHERE status = 'approved' AND MONTH(payment_date) = MONTH(CURDATE()) AND YEAR(payment_date) = YEAR(CURDATE())
    ");
    $stmt_monthly->execute();
    $monthly_total = $stmt_monthly->fetchColumn() ?: 0;

    // 3. Total pembayaran yang masih menunggu validasi
    $stmt_pending = $pdo->prepare("SELECT SUM(total_amount) as total FROM payments WHERE status = 'pending'");
    $stmt_pending->execute();
    $total_pending = $stmt_pending->fetchColumn() ?: 0;
    
    // 4. Total pemasukan yang disetujui sepanjang waktu
    $stmt_approved_all = $pdo->prepare("SELECT SUM(total_amount) as total FROM payments WHERE status = 'approved'");
    $stmt_approved_all->execute();
    $total_approved_all_time = $stmt_approved_all->fetchColumn() ?: 0;

    sendJSONResponse(['success' => true, 'summary' => [
        'daily_summary' => $daily_summary,
        'monthly_total' => (float)$monthly_total,
        'total_pending' => (float)$total_pending,
        'total_approved_all_time' => (float)$total_approved_all_time
    ]]);

} catch (Exception $e) {
    sendJSONResponse(['success' => false, 'message' => 'Database error: ' . $e->getMessage()], 500);
}
?>