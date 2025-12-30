<?php
// API: Get pocket money transactions for a Musyrif's students
header('Content-Type: application/json');
require_once 'db_connect.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id'])) {
    sendJSONResponse(['success' => false, 'message' => 'Unauthorized'], 401);
    exit;
}

$musyrif_id = $_SESSION['user_id'];

try {
    $pdo = getDBConnection();
    
    $sql = "SELECT 
                t.amount,
                DATE_FORMAT(t.validated_at, '%d %b %Y, %H:%i') as validated_at_formatted,
                w.full_name as walisantri_name,
                s.full_name as santri_name
            FROM 
                pocket_money_transactions t
            JOIN 
                users w ON t.walisantri_id = w.user_id
            JOIN 
                users s ON t.santri_id = s.user_id
            WHERE
                t.musyrif_id = ? AND
                t.status = 'approved' AND
                t.transaction_type = 'deposit'
            ORDER BY
                t.validated_at DESC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([$musyrif_id]);

    $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
    sendJSONResponse(['success' => true, 'data' => $data]);

} catch (Exception $e) {
    sendJSONResponse(['success' => false, 'message' => 'Database error: ' . $e->getMessage()], 500);
}
?>