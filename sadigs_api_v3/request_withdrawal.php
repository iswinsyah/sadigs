<?php
// API: Request Pocket Money Withdrawal
header('Content-Type: application/json');
require_once 'db_connect.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id'])) {
    sendJSONResponse(['success' => false, 'message' => 'Unauthorized'], 401);
    exit;
}

$santri_id = $_SESSION['user_id'];
$input = json_decode(file_get_contents('php://input'), true);
$amount = $input['amount'] ?? null;
$notes = $input['notes'] ?? null;

if (!$amount || !is_numeric($amount) || $amount <= 0 || !$notes) {
    sendJSONResponse(['success' => false, 'message' => 'Jumlah dan keperluan penarikan wajib diisi.'], 400);
    exit;
}

$pdo = getDBConnection();

try {
    $pdo->beginTransaction();

    // 1. Kunci baris saldo untuk mencegah race condition dan cek saldo
    $stmt_balance = $pdo->prepare("SELECT balance FROM pocket_money_balances WHERE santri_id = ? FOR UPDATE");
    $stmt_balance->execute([$santri_id]);
    $current_balance = $stmt_balance->fetchColumn();

    if ($current_balance === false || $current_balance < $amount) {
        $pdo->rollBack();
        sendJSONResponse(['success' => false, 'message' => 'Saldo tidak mencukupi untuk melakukan penarikan.'], 400);
        exit;
    }

    // 2. Dapatkan musyrif_id dari santri
    $stmt_musyrif = $pdo->prepare("SELECT musyrif_id FROM mentoring_groups WHERE student_id = ?");
    $stmt_musyrif->execute([$santri_id]);
    $musyrif_id = $stmt_musyrif->fetchColumn();

    if (!$musyrif_id) {
        $pdo->rollBack();
        sendJSONResponse(['success' => false, 'message' => 'Anda belum memiliki Musyrif pembimbing. Penarikan tidak dapat diproses.'], 400);
        exit;
    }

    // 3. Masukkan transaksi penarikan dengan status 'pending'
    $stmt_insert = $pdo->prepare(
        "INSERT INTO pocket_money_transactions 
        (santri_id, walisantri_id, musyrif_id, transaction_type, amount, notes, status) 
        VALUES (?, ?, ?, 'withdrawal', ?, ?, 'pending')"
    );
    
    $stmt_insert->execute([$santri_id, $santri_id, $musyrif_id, $amount, $notes]);

    $pdo->commit();
    sendJSONResponse(['success' => true, 'message' => 'Permintaan penarikan berhasil dikirim dan akan divalidasi oleh Musyrif Anda.']);

} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    sendJSONResponse(['success' => false, 'message' => 'Database error: ' . $e->getMessage()], 500);
}
?>