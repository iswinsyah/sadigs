<?php
header('Content-Type: application/json');
require_once 'db_connect.php';

if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['user_id'])) {
    sendJSONResponse(['success' => false, 'message' => 'Unauthorized'], 401);
}

$pdo = getDBConnection();
$context = $_GET['context'] ?? 'unit'; // 'unit' (Kepala) atau 'foundation' (Yayasan)
$user_roles = $_SESSION['roles'] ?? []; // Get user roles from session

try {
    $sql = "SELECT b.*, u.full_name as sender_name 
            FROM operational_budgets b
            JOIN users u ON b.user_id = u.user_id ";
    
    $conditions = [];
    $params = [];
    
    if ($context === 'unit') {
        $allowed_categories = [];
        if (in_array('Kepala Sekolah', $user_roles)) {
            $allowed_categories[] = 'Sekolah';
        }
        if (in_array('Kepala Asrama Putra', $user_roles)) {
            $allowed_categories[] = 'Asrama Putra';
        }
        if (in_array('Kepala Asrama Putri', $user_roles)) {
            $allowed_categories[] = 'Asrama Putri';
        }

        if (!empty($allowed_categories)) {
            $placeholders = implode(',', array_fill(0, count($allowed_categories), '?'));
            $conditions[] = "b.category IN ($placeholders)";
            $params = array_merge($params, $allowed_categories);
        } else {
            // If user has no validation roles, they see nothing in unit context
            $conditions[] = "1=0"; // Always false condition
        }
    } elseif ($context === 'foundation') {
        // Yayasan hanya melihat yang sudah disetujui Unit (Kepala)
        $conditions[] = "b.status_unit = 'approved'";
    }

    if (!empty($conditions)) {
        $sql .= " WHERE " . implode(' AND ', $conditions);
    }
    
    $sql .= " ORDER BY b.created_at DESC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

    sendJSONResponse(['success' => true, 'data' => $data]);

} catch (Exception $e) {
    sendJSONResponse(['success' => false, 'message' => 'Database error: ' . $e->getMessage()], 500);
}
?>