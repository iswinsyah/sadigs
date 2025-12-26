<?php
header('Content-Type: application/json');
require_once 'db_connect.php';

if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['user_id'])) {
    sendJSONResponse(['success' => false, 'message' => 'Unauthorized'], 401);
}

$pdo = getDBConnection();
$context = $_GET['context'] ?? 'unit'; // 'unit' (Kepala) atau 'foundation' (Yayasan)

try {
    $sql = "SELECT b.*, u.full_name as sender_name 
            FROM operational_budgets b
            JOIN users u ON b.user_id = u.user_id ";
    
    $conditions = [];
    
    if ($context === 'foundation') {
        // Yayasan hanya melihat yang sudah disetujui Unit (Kepala)
        $conditions[] = "b.status_unit = 'approved'";
    }

    if (!empty($conditions)) {
        $sql .= " WHERE " . implode(' AND ', $conditions);
    }
    
    $sql .= " ORDER BY b.created_at DESC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

    sendJSONResponse(['success' => true, 'data' => $data]);

} catch (Exception $e) {
    sendJSONResponse(['success' => false, 'message' => $e->getMessage()], 500);
}
?>