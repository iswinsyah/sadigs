<?php
header('Content-Type: application/json');
require_once 'db_connect.php';

if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['user_id'])) {
    sendJSONResponse(['success' => false, 'message' => 'Unauthorized'], 401);
}

$data = json_decode(file_get_contents("php://input"), true);
$payment_id = $data['id'];
$status = $data['status']; // 'approved' or 'rejected'
$validator_id = $_SESSION['user_id'];

if (!in_array($status, ['approved', 'rejected'])) {
    sendJSONResponse(['success' => false, 'message' => 'Status tidak valid'], 400);
}

$pdo = getDBConnection();

try {
    $stmt = $pdo->prepare("UPDATE payments SET status = ?, validator_user_id = ?, validated_at = NOW() WHERE id = ?");
    $stmt->execute([$status, $validator_id, $payment_id]);

    if ($stmt->rowCount() > 0) {
        sendJSONResponse(['success' => true, 'message' => 'Status pembayaran diperbarui.']);
    } else {
        sendJSONResponse(['success' => false, 'message' => 'Data tidak ditemukan atau tidak ada perubahan.'], 404);
    }
} catch (Exception $e) {
    sendJSONResponse(['success' => false, 'message' => $e->getMessage()], 500);
}
?>