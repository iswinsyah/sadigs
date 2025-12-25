<?php
header('Content-Type: application/json');
require_once 'db_connect.php';

if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['user_id'])) {
    sendJSONResponse(['success' => false, 'message' => 'Unauthorized'], 401);
}

$user_roles = $_SESSION['roles'] ?? [];
$input = json_decode(file_get_contents('php://input'), true);

$request_id = $input['request_id'] ?? null;
$action = $input['action'] ?? null; // 'approved' or 'rejected'

if (!$request_id || !in_array($action, ['approved', 'rejected'])) {
    sendJSONResponse(['success' => false, 'message' => 'Data tidak valid.'], 400);
}

try {
    $pdo = getDBConnection();
    $pdo->beginTransaction();

    // 1. Ambil data izin saat ini
    $stmt = $pdo->prepare("SELECT approvals FROM leave_requests WHERE id = ?");
    $stmt->execute([$request_id]);
    $current_approvals_json = $stmt->fetchColumn();

    if (!$current_approvals_json) {
        throw new Exception("Data perizinan tidak ditemukan.");
    }

    $approvals = json_decode($current_approvals_json, true);
    $validated = false;

    // 2. Update status berdasarkan peran user yang login
    foreach ($user_roles as $role) {
        if (array_key_exists($role, $approvals) && $approvals[$role] === 'pending') {
            $approvals[$role] = $action;
            $validated = true;
        }
    }

    if (!$validated) throw new Exception("Anda tidak memiliki hak untuk memvalidasi izin ini atau izin sudah divalidasi.");

    // 3. Simpan kembali JSON yang sudah diupdate
    $stmt = $pdo->prepare("UPDATE leave_requests SET approvals = ? WHERE id = ?");
    $stmt->execute([json_encode($approvals), $request_id]);

    $pdo->commit();
    sendJSONResponse(['success' => true, 'message' => 'Status izin berhasil diperbarui.']);

} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    sendJSONResponse(['success' => false, 'message' => $e->getMessage()], 500);
}
?>