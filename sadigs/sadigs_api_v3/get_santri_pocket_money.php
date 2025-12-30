<?php
// API: Get a student's pocket money balance and history
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

try {
    $pdo = getDBConnection();
    
    // 1. Get current balance
    $stmt_balance = $pdo->prepare("SELECT balance FROM pocket_money_balances WHERE santri_id = ?");
    $stmt_balance->execute([$santri_id]);
    $balance = $stmt_balance->fetchColumn();

    // 2. Get transaction history
    $stmt_tx = $pdo->prepare("
        SELECT 
            transaction_type, 
            amount, 
            notes, 
            DATE_FORMAT(created_at, '%d %b %Y, %H:%i') as created_at_formatted
        FROM 
            pocket_money_transactions 
        WHERE 
            santri_id = ? AND status = 'approved'
        ORDER BY 
            created_at DESC
    ");
    $stmt_tx->execute([$santri_id]);
    $transactions = $stmt_tx->fetchAll(PDO::FETCH_ASSOC);

    sendJSONResponse([
        'success' => true, 
        'balance' => $balance ?: 0,
        'transactions' => $transactions
    ]);

} catch (Exception $e) {
    sendJSONResponse(['success' => false, 'message' => 'Database error: ' . $e->getMessage()], 500);
}
?>