<?php
// API: Mengambil daftar izin walisantri yang perlu divalidasi
header('Content-Type: application/json');
require_once 'db_connect.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id'], $_SESSION['username'])) {
    sendJSONResponse(['success' => false, 'message' => 'Unauthorized'], 401);
    exit;
}

$username = $_SESSION['username'];

try {
    $pdo = getDBConnection();
    
    // Ambil request yang ditujukan ke user ini dan masih pending
    $sql = "SELECT glr.*, u.full_name as walisantri_name 
            FROM guardian_leave_requests glr
            JOIN users u ON glr.user_id = u.id
            WHERE glr.musyrif_username = ? AND glr.status = 'pending'
            ORDER BY glr.created_at ASC";
            
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$username]);
    $requests = $stmt->fetchAll(PDO::FETCH_ASSOC);

    sendJSONResponse(['success' => true, 'data' => $requests]);

} catch (Exception $e) {
    sendJSONResponse(['success' => false, 'message' => 'Database error: ' . $e->getMessage()], 500);
}
?>