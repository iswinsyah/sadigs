<?php
// API: Validate Pocket Money Deposit
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

    // 1. Update status transaksi
    $stmt_update = $pdo->prepare(
        "UPDATE pocket_money_transactions SET status = ?, validated_by = ?, validated_at = NOW() WHERE id = ? AND status = 'pending'"
    );
    $stmt_update->execute([$action, $validator_id, $transaction_id]);

    // 2. Jika disetujui, update saldo santri
    if ($action === 'approved') {
        // Ambil info santri dan jumlah dari transaksi
        $stmt_info = $pdo->prepare("SELECT santri_id, amount FROM pocket_money_transactions WHERE id = ?");
        $stmt_info->execute([$transaction_id]);
        $tx_info = $stmt_info->fetch(PDO::FETCH_ASSOC);

        if ($tx_info) {
            // Gunakan ON DUPLICATE KEY UPDATE untuk membuat atau memperbarui saldo
            $stmt_balance = $pdo->prepare(
                "INSERT INTO pocket_money_balances (santri_id, balance) VALUES (?, ?) ON DUPLICATE KEY UPDATE balance = balance + VALUES(balance)"
            );
            $stmt_balance->execute([$tx_info['santri_id'], $tx_info['amount']]);
        }
    }

    $pdo->commit();
    sendJSONResponse(['success' => true, 'message' => 'Validasi berhasil.']);

} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    sendJSONResponse(['success' => false, 'message' => 'Database error: ' . $e->getMessage()], 500);
}
?>