<?php
// API: Memproses validasi (setujui/tolak) izin walisantri
header('Content-Type: application/json');
require_once 'db_connect.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id'], $_SESSION['username'])) {
    sendJSONResponse(['success' => false, 'message' => 'Unauthorized'], 401);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$request_id = $input['request_id'] ?? null;
$action = $input['action'] ?? null; // 'approved' or 'rejected'

if (!$request_id || !in_array($action, ['approved', 'rejected'])) {
    sendJSONResponse(['success' => false, 'message' => 'Data tidak valid.'], 400);
    exit;
}

$validator_id = $_SESSION['user_id'];
$validator_username = $_SESSION['username'];

try {
    $pdo = getDBConnection();

    // Verifikasi bahwa user yang memvalidasi adalah penerima yang benar
    $stmt = $pdo->prepare("SELECT musyrif_username FROM guardian_leave_requests WHERE id = ?");
    $stmt->execute([$request_id]);
    $recipient = $stmt->fetchColumn();

    if ($recipient !== $validator_username) {
        sendJSONResponse(['success' => false, 'message' => 'Anda tidak berhak memvalidasi izin ini.'], 403);
        exit;
    }

    // Update status izin
    $sql = "UPDATE guardian_leave_requests SET status = ?, validated_by = ?, validated_at = NOW() WHERE id = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$action, $validator_id, $request_id]);

    sendJSONResponse(['success' => true, 'message' => 'Izin berhasil di' . ($action === 'approved' ? 'setujui' : 'tolak') . '.']);

} catch (Exception $e) {
    sendJSONResponse(['success' => false, 'message' => 'Database error: ' . $e->getMessage()], 500);
}
?>