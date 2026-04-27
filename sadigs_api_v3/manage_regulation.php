<?php
header('Content-Type: application/json');
require_once 'db_connect.php';

if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$user_roles = $_SESSION['roles'] ?? [];
$allowed_roles = ['Ketua Yayasan', 'Sekretaris Yayasan'];
$can_manage = !empty(array_intersect($allowed_roles, $user_roles));

if (!$can_manage) {
    echo json_encode(['success' => false, 'message' => 'Akses ditolak. Hanya Ketua & Sekretaris Yayasan.']);
    exit;
}

$pdo = getDBConnection();
$input = json_decode(file_get_contents('php://input'), true);
$action = $input['action'] ?? '';

try {
    if ($action === 'delete') {
        $id = $input['id'];
        $stmt = $pdo->prepare("DELETE FROM regulations WHERE id = ?");
        $stmt->execute([$id]);
        echo json_encode(['success' => true, 'message' => 'Amanah berhasil dihapus.']);
    } 
    elseif ($action === 'edit') {
        $id = $input['id'];
        $title = $input['title'];
        $content = $input['content'];
        // Target role dikirim sebagai string dipisah koma
        $target_role = $input['target_role']; 
        
        $stmt = $pdo->prepare("UPDATE regulations SET title = ?, content = ?, target_role = ? WHERE id = ?");
        $stmt->execute([$title, $content, $target_role, $id]);
        echo json_encode(['success' => true, 'message' => 'Amanah berhasil diperbarui.']);
    }
    else {
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>