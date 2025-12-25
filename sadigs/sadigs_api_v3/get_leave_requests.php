<?php
header('Content-Type: application/json');
require_once 'db_connect.php';

if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['user_id'])) {
    sendJSONResponse(['success' => false, 'message' => 'Unauthorized'], 401);
}

$pdo = getDBConnection();
$user_roles = $_SESSION['roles'] ?? [];
$context = $_GET['context'] ?? 'list';

try {
    $sql = "SELECT lr.*, u.username, u.full_name 
            FROM leave_requests lr 
            JOIN users u ON lr.user_id = u.user_id";
    
    $params = [];

    if ($context === 'validation') {
        // Ambil request yang ditujukan ke salah satu peran user yang login DAN statusnya masih 'pending'
        $conditions = [];
        foreach ($user_roles as $role) {
            // Sanitasi dasar untuk nama peran sebelum dimasukkan ke query
            $sanitized_role = preg_replace('/[^a-zA-Z0-9\s_]/', '', $role);
            if (!empty($sanitized_role)) {
                // Mencari key dengan status 'pending'
                $conditions[] = "JSON_UNQUOTE(JSON_EXTRACT(lr.approvals, '$.\"" . $sanitized_role . "\"')) = 'pending'";
            }
        }

        if (!empty($conditions)) {
            $sql .= " WHERE " . implode(' OR ', $conditions);
        } else {
            // Jika user tidak punya peran validasi, kembalikan array kosong
            sendJSONResponse(['success' => true, 'data' => []]);
            exit;
        }
    }

    $sql .= " ORDER BY lr.created_at DESC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    sendJSONResponse(['success' => true, 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);

} catch (Exception $e) {
    sendJSONResponse(['success' => false, 'message' => 'Database error: ' . $e->getMessage()], 500);
}
?>