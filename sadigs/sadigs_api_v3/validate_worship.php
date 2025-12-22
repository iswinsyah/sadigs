<?php
header('Content-Type: application/json');
require_once 'db_connect.php';

if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['user_id'])) {
    sendJSONResponse(['success' => false, 'message' => 'Unauthorized'], 401);
}

$input = json_decode(file_get_contents('php://input'), true);
$id = $input['id'] ?? null;
$status = $input['status'] ?? null;

if (!$id || !in_array($status, ['approved', 'rejected'])) {
    sendJSONResponse(['success' => false, 'message' => 'Data tidak valid'], 400);
}

$pdo = getDBConnection();

try {
    $sql = "UPDATE ibadah_harian SET validation_status = ?, validated_at = NOW(), validator_id = ? WHERE id = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$status, $_SESSION['user_id'], $id]);
    
    sendJSONResponse(['success' => true, 'message' => 'Status validasi berhasil diperbarui.']);
} catch (Exception $e) {
    sendJSONResponse(['success' => false, 'message' => $e->getMessage()], 500);
}
?>