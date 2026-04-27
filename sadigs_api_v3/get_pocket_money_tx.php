<?php
// API: Get Pocket Money Transactions
header('Content-Type: application/json');
require_once 'db_connect.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id'])) {
    sendJSONResponse(['success' => false, 'message' => 'Unauthorized'], 401);
    exit;
}

$status = $_GET['status'] ?? null;

try {
    $pdo = getDBConnection();
    
    $sql = "SELECT 
                t.id,
                t.amount,
                t.proof_file,
                DATE_FORMAT(t.created_at, '%d %b %Y, %H:%i') as created_at_formatted,
                w.full_name as walisantri_name,
                s.full_name as santri_name
            FROM 
                pocket_money_transactions t
            JOIN 
                users w ON t.walisantri_id = w.user_id
            JOIN 
                users s ON t.santri_id = s.user_id";

    if ($status) {
        $sql .= " WHERE t.status = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$status]);
    } else {
        $stmt = $pdo->query($sql);
    }

    $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
    sendJSONResponse(['success' => true, 'data' => $data]);

} catch (Exception $e) {
    sendJSONResponse(['success' => false, 'message' => 'Database error: ' . $e->getMessage()], 500);
}
?>