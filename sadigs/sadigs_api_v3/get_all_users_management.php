<?php
header('Content-Type: application/json');
// Matikan display errors agar tidak merusak output JSON
ini_set('display_errors', 0);
error_reporting(0);

require_once 'db_connect.php';

if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
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
            GROUP BY u.user_id";

    $stmt = $pdo->query($sql);
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Sorting di PHP (Lebih aman dan kompatibel)
    usort($users, function($a, $b) {
        // Prioritas 1: Yang butuh aksi (Pending > 0 atau Non-Aktif)
        $a_active = isset($a['is_active']) ? (int)$a['is_active'] : 0;
        $b_active = isset($b['is_active']) ? (int)$b['is_active'] : 0;
        
        $a_needs_action = ($a['pending_count'] > 0 || $a_active === 0);
        $b_needs_action = ($b['pending_count'] > 0 || $b_active === 0);

        if ($a_needs_action && !$b_needs_action) return -1;
        if (!$a_needs_action && $b_needs_action) return 1;

        // Prioritas 2: User terbaru (ID besar) di atas
        return $b['user_id'] - $a['user_id'];
    });

    echo json_encode(['success' => true, 'data' => $users]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server Error: ' . $e->getMessage()]);
}
?>