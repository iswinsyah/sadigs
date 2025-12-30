<?php
// API: Validate Pocket Money Withdrawal by Musyrif
header('Content-Type: application/json');
require_once 'db_connect.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id'])) {
    sendJSONResponse(['success' => false, 'message' => 'Unauthorized'], 401);
    exit;
}

$validator_id = $_SESSION['user_id'];
$input = json_decode(file_get_contents('php://input'), true);
$transaction_id = $input['transaction_id'] ?? null;
$action = $input['action'] ?? null; // 'approved' or 'rejected'

if (!$transaction_id || !in_array($action, ['approved', 'rejected'])) {
    sendJSONResponse(['success' => false, 'message' => 'Data tidak valid.'], 400);
    exit;
}

$pdo = getDBConnection();

try {
    $pdo->beginTransaction();

    // 1. Ambil info transaksi dan pastikan Musyrif berhak memvalidasi
    $stmt_info = $pdo->prepare("SELECT santri_id, amount, status FROM pocket_money_transactions WHERE id = ? AND musyrif_id = ? FOR UPDATE");
    $stmt_info->execute([$transaction_id, $validator_id]);
    $tx_info = $stmt_info->fetch(PDO::FETCH_ASSOC);

    if (!$tx_info) {
        $pdo->rollBack();
        sendJSONResponse(['success' => false, 'message' => 'Transaksi tidak ditemukan atau Anda tidak berhak memvalidasi.'], 404);
        exit;
    }

    if ($tx_info['status'] !== 'pending') {
        $pdo->rollBack();
        sendJSONResponse(['success' => false, 'message' => 'Transaksi ini sudah diproses sebelumnya.'], 400);
        exit;
    }

    // 2. Jika disetujui, cek saldo dan kurangi
    if ($action === 'approved') {
        $stmt_balance = $pdo->prepare("SELECT balance FROM pocket_money_balances WHERE santri_id = ? FOR UPDATE");
        $stmt_balance->execute([$tx_info['santri_id']]);
        $current_balance = $stmt_balance->fetchColumn();

        if ($current_balance === false || $current_balance < $tx_info['amount']) {
            $pdo->rollBack();
            sendJSONResponse(['success' => false, 'message' => 'Gagal, saldo santri tidak mencukupi. Transaksi tidak dapat disetujui.'], 400);
            exit;
        }

        $stmt_deduct = $pdo->prepare("UPDATE pocket_money_balances SET balance = balance - ? WHERE santri_id = ?");
        $stmt_deduct->execute([$tx_info['amount'], $tx_info['santri_id']]);
    }

    // 3. Update status transaksi menjadi 'approved' atau 'rejected'
    $stmt_update = $pdo->prepare("UPDATE pocket_money_transactions SET status = ?, validated_by = ?, validated_at = NOW() WHERE id = ?");
    $stmt_update->execute([$action, $validator_id, $transaction_id]);

    $pdo->commit();
    sendJSONResponse(['success' => true, 'message' => 'Validasi penarikan berhasil.']);

} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    sendJSONResponse(['success' => false, 'message' => 'Database error: ' . $e->getMessage()], 500);
}
?>