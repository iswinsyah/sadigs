<?php
header('Content-Type: application/json');
require_once 'db_connect.php';

if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['user_id'])) sendJSONResponse(['success' => false, 'message' => 'Unauthorized'], 401);

$action = $_GET['action'] ?? 'list_active';
$pdo = getDBConnection();

try {
    if ($action === 'list_pending') {
        // Untuk Ketua Yayasan: Lihat yang pending
        if (!in_array('Ketua Yayasan', $_SESSION['roles'] ?? [])) {
            sendJSONResponse(['success' => false, 'message' => 'Forbidden'], 403);
        }
        $stmt = $pdo->query("SELECT r.*, u.full_name as creator_name FROM regulations r JOIN users u ON r.created_by = u.user_id WHERE r.status = 'pending' ORDER BY r.created_at DESC");
        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
        sendJSONResponse(['success' => true, 'data' => $data]);

    } else {
        // Untuk Widget Dashboard: Lihat yang approved & sesuai role
        $user_roles = $_SESSION['roles'] ?? [];
        
        // Bangun query dinamis
        // Tampilkan jika target_role = 'Semua' ATAU target_role ada di daftar role user
        $placeholders = implode(',', array_fill(0, count($user_roles), '?'));
        
        $sql = "SELECT * FROM regulations 
                WHERE status = 'approved' 
                AND (target_role = 'Semua' OR target_role IN ($placeholders)) 
                ORDER BY created_at DESC LIMIT 5";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($user_roles);
        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Format tanggal agar cantik
        foreach ($data as &$row) {
            $row['formatted_date'] = date('d M Y', strtotime($row['created_at']));
        }
        
        sendJSONResponse(['success' => true, 'data' => $data]);
    }
} catch (Exception $e) {
    sendJSONResponse(['success' => false, 'message' => $e->getMessage()], 500);
}
?>