<?php
header('Content-Type: application/json');
require_once 'db_connect.php';

if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['user_id'])) {
    sendJSONResponse(['success' => false, 'message' => 'Unauthorized'], 401);
    exit;
}

try {
    $pdo = getDBConnection();

    // Query Gabungan: Ambil User + Role + Status
    // Kita hitung berapa role yang 'pending' untuk menentukan status verifikasi
    $sql = "SELECT u.user_id, u.username, u.full_name, u.email, u.is_active, u.password_hash,
                   GROUP_CONCAT(
                       CASE 
                           WHEN ur.status = 'pending' THEN CONCAT(ur.role_name, ' (Pending)')
                           ELSE ur.role_name 
                       END SEPARATOR ', '
                   ) as roles_display,
                   SUM(CASE WHEN ur.status = 'pending' THEN 1 ELSE 0 END) as pending_count
            FROM users u
            LEFT JOIN user_roles ur ON u.user_id = ur.user_id
            GROUP BY u.user_id
            ORDER BY 
                (pending_count > 0 OR u.is_active = 0) DESC, -- Prioritaskan yang butuh aksi
                u.created_at DESC";

    $stmt = $pdo->query($sql);
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);

    sendJSONResponse(['success' => true, 'data' => $users]);

} catch (Exception $e) {
    sendJSONResponse(['success' => false, 'message' => $e->getMessage()], 500);
}
?>