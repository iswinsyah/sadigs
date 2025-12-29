<?php
// API: Mengambil daftar santri yang sedang izin pulang
header('Content-Type: application/json');
require_once 'db_connect.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id'])) {
    sendJSONResponse(['success' => false, 'message' => 'Unauthorized'], 401);
    exit;
}

try {
    $pdo = getDBConnection();
    
    // Ambil request yang disetujui dan sedang dalam rentang waktu izin
    $sql = "SELECT glr.student_name, glr.leave_type, glr.end_datetime, u.full_name as walisantri_name 
            FROM guardian_leave_requests glr
            JOIN users u ON glr.user_id = u.user_id
            WHERE glr.status = 'approved' AND NOW() BETWEEN glr.start_datetime AND glr.end_datetime
            ORDER BY glr.end_datetime ASC";
            
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    $requests = $stmt->fetchAll(PDO::FETCH_ASSOC);

    sendJSONResponse(['success' => true, 'data' => $requests]);

} catch (Exception $e) {
    sendJSONResponse(['success' => false, 'message' => 'Database error: ' . $e->getMessage()], 500);
}
?>