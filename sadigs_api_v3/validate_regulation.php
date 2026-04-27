<?php
header('Content-Type: application/json');
require_once 'db_connect.php';

if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['user_id'])) sendJSONResponse(['success' => false, 'message' => 'Unauthorized'], 401);

// Hanya Ketua Yayasan
if (!in_array('Ketua Yayasan', $_SESSION['roles'] ?? [])) {
    sendJSONResponse(['success' => false, 'message' => 'Akses ditolak.'], 403);
}

$input = json_decode(file_get_contents('php://input'), true);
if (empty($input['id']) || empty($input['action'])) {
    sendJSONResponse(['success' => false, 'message' => 'Data tidak lengkap.'], 400);
}

$status = ($input['action'] === 'approve') ? 'approved' : 'rejected';

try {
    $pdo = getDBConnection();
    $stmt = $pdo->prepare("UPDATE regulations SET status = ? WHERE id = ?");
    $stmt->execute([$status, $input['id']]);
    
    sendJSONResponse(['success' => true, 'message' => 'Status peraturan berhasil diperbarui.']);
} catch (Exception $e) { sendJSONResponse(['success' => false, 'message' => $e->getMessage()], 500); }
?>