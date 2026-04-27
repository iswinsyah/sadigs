<?php
// API: Get pending withdrawal requests for a Musyrif
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
                t.id,
                t.amount,
                t.notes,
                DATE_FORMAT(t.created_at, '%d %b %Y, %H:%i') as created_at_formatted,
                s.full_name as santri_name
            FROM 
                pocket_money_transactions t
            JOIN 
                users s ON t.santri_id = s.user_id
            WHERE
                t.musyrif_id = ? AND
                t.status = 'pending' AND
                t.transaction_type = 'withdrawal'
            ORDER BY
                t.created_at ASC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([$musyrif_id]);

    $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
    sendJSONResponse(['success' => true, 'data' => $data]);

} catch (Exception $e) {
    sendJSONResponse(['success' => false, 'message' => 'Database error: ' . $e->getMessage()], 500);
}
?>