<?php
header('Content-Type: application/json');
require_once 'db_connect.php';

if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['user_id'])) {
    sendJSONResponse(['success' => false, 'message' => 'Unauthorized'], 401);
}

$data = json_decode(file_get_contents('php://input'), true);
$user_id = $_SESSION['user_id'];

// Validasi input
$required_fields = ['recipient', 'leave_type', 'start_date', 'end_date'];
foreach ($required_fields as $field) {
    // 'recipient' sekarang adalah array, pengecekan empty() sudah cukup
    if (empty($data[$field])) {
        sendJSONResponse(['success' => false, 'message' => "Kolom '$field' wajib diisi."], 400);
    }
}

// 'recipient' sekarang adalah array, ubah menjadi JSON object untuk kolom 'approvals'
$recipients = $data['recipient'];
$approvals = [];
foreach ($recipients as $recipient_role) {
    $approvals[$recipient_role] = 'pending'; // status awal
}
$approvals_json = json_encode($approvals);

$leave_type = $data['leave_type'];
$description = isset($data['description']) ? $data['description'] : null;
$start_date = $data['start_date'];
$end_date = $data['end_date'];

try {
    $pdo = getDBConnection();
    $sql = "INSERT INTO leave_requests (user_id, approvals, leave_type, description, start_date, end_date) 
            VALUES (?, ?, ?, ?, ?, ?)";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$user_id, $approvals_json, $leave_type, $description, $start_date, $end_date]);

    sendJSONResponse(['success' => true, 'message' => 'Permohonan izin berhasil diajukan.']);

} catch (Exception $e) {
    sendJSONResponse(['success' => false, 'message' => 'Database error: ' . $e->getMessage()], 500);
}
?>