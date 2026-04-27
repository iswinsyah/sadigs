<?php
header('Content-Type: application/json');
require_once 'db_connect.php';

if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

// Cek Role: Hanya Ketua Yayasan yang boleh Validasi & Edit di sini
$allowed = ['Ketua Yayasan'];
$user_roles = $_SESSION['roles'] ?? [];
if (empty(array_intersect($allowed, $user_roles))) {
    echo json_encode(['success' => false, 'message' => 'Akses ditolak. Khusus Ketua Yayasan.']);
    exit;
}

$pdo = getDBConnection();
$action = $_GET['action'] ?? '';

try {
    // 1. AMBIL DATA PENDING
    if ($action === 'get_pending') {
        $stmt = $pdo->query("
            SELECT r.*, u.full_name as creator_name 
            FROM regulations r
            JOIN users u ON r.created_by = u.user_id
            WHERE r.status = 'pending'
            ORDER BY r.created_at DESC
        ");
        echo json_encode(['success' => true, 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
    }
    
    // 2. UPDATE DATA (FITUR EDIT LANGSUNG)
    elseif ($action === 'update') {
        $input = json_decode(file_get_contents('php://input'), true);
        $id = $input['id'];
        $title = $input['title'];
        $content = $input['content'];
        $target_role = $input['target_role'];
        
        $stmt = $pdo->prepare("UPDATE regulations SET title = ?, content = ?, target_role = ? WHERE id = ?");
        $stmt->execute([$title, $content, $target_role, $id]);
        
        echo json_encode(['success' => true, 'message' => 'Redaksi amanah berhasil diperbarui.']);
    }
    
    // 3. VALIDASI (APPROVE / REJECT)
    elseif ($action === 'validate') {
        $input = json_decode(file_get_contents('php://input'), true);
        $id = $input['id'];
        $status = $input['status']; // 'approved' or 'rejected'
        
        $stmt = $pdo->prepare("UPDATE regulations SET status = ? WHERE id = ?");
        $stmt->execute([$status, $id]);
        
        echo json_encode(['success' => true, 'message' => 'Status berhasil diubah menjadi ' . $status]);
    }
    
    else {
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
?>