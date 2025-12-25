<?php
header('Content-Type: application/json');
require_once 'db_connect.php';

if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['user_id'])) {
    sendJSONResponse(['success' => false, 'message' => 'Unauthorized'], 401);
}

$allowed = ['Bendahara Sekolah', 'Bendahara Yayasan', 'Ketua Yayasan'];
if (empty(array_intersect($allowed, $_SESSION['roles'] ?? []))) {
    sendJSONResponse(['success' => false, 'message' => 'Akses ditolak.'], 403);
}

$pdo = getDBConnection();

try {
    // Ambil data transaksi urut dari yang terbaru
    $sql = "SELECT t.*, u.full_name as officer_name 
            FROM daily_transactions t
            JOIN users u ON t.created_by = u.user_id
            ORDER BY t.transaction_date DESC, t.created_at DESC
            LIMIT 500"; // Batasi 500 transaksi terakhir agar ringan
            
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Hitung Saldo Sederhana (Total Masuk - Total Keluar dari data yang ditarik)
    // Catatan: Untuk saldo akurat, sebaiknya query SUM() terpisah tanpa LIMIT.
    // Tapi untuk tampilan tabel, ini cukup.
    
    sendJSONResponse(['success' => true, 'data' => $data]);

} catch (Exception $e) {
    sendJSONResponse(['success' => false, 'message' => $e->getMessage()], 500);
}
?>