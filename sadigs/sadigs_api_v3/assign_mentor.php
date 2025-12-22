<?php
header('Content-Type: application/json');
require_once 'db_connect.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id'])) {
    sendJSONResponse(['success' => false, 'message' => 'Unauthorized'], 401);
}

$input = json_decode(file_get_contents('php://input'), true);
$student_id = $input['student_id'] ?? null;
$musyrif_id = $input['musyrif_id'] ?? null;

if (!$student_id || !$musyrif_id) {
    sendJSONResponse(['success' => false, 'message' => 'Data tidak lengkap'], 400);
}

$pdo = getDBConnection();

try {
    // Insert atau Update jika sudah ada (ON DUPLICATE KEY UPDATE)
    $sql = "INSERT INTO mentoring_assignments (student_id, musyrif_id) VALUES (?, ?) 
            ON DUPLICATE KEY UPDATE musyrif_id = VALUES(musyrif_id)";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$student_id, $musyrif_id]);
    sendJSONResponse(['success' => true, 'message' => 'Pembagian kelompok berhasil disimpan.']);
} catch (Exception $e) {
    sendJSONResponse(['success' => false, 'message' => $e->getMessage()], 500);
}
?>