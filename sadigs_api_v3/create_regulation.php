<?php
header('Content-Type: application/json');
require_once 'db_connect.php';

if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['user_id'])) sendJSONResponse(['success' => false, 'message' => 'Unauthorized'], 401);

// Hanya Sekretaris Yayasan (atau Ketua) yang boleh buat
$allowed = ['Sekretaris Yayasan', 'Ketua Yayasan'];
$user_roles = $_SESSION['roles'] ?? [];
if (empty(array_intersect($allowed, $user_roles))) {
    sendJSONResponse(['success' => false, 'message' => 'Akses ditolak.'], 403);
}

$input = json_decode(file_get_contents('php://input'), true);
if (empty($input['title']) || empty($input['content']) || empty($input['target_role'])) {
    sendJSONResponse(['success' => false, 'message' => 'Semua kolom wajib diisi.'], 400);
}

try {
    $pdo = getDBConnection();
    $stmt = $pdo->prepare("INSERT INTO regulations (created_by, title, content, target_role, status) VALUES (?, ?, ?, ?, 'pending')");
    $stmt->execute([
        $_SESSION['user_id'],
        $input['title'],
        $input['content'],
        $input['target_role']
    ]);
    sendJSONResponse(['success' => true, 'message' => 'Peraturan berhasil dibuat dan menunggu validasi Ketua Yayasan.']);
} catch (Exception $e) { sendJSONResponse(['success' => false, 'message' => $e->getMessage()], 500); }
?>