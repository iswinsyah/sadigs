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

// 'recipient' sekarang adalah array, ubah menjadi string JSON untuk disimpan di DB
$recipient_json = json_encode($data['recipient']);
if ($recipient_json === false) {
    sendJSONResponse(['success' => false, 'message' => 'Format data penerima tidak valid.'], 400);
}

$leave_type = $data['leave_type'];
$description = isset($data['description']) ? $data['description'] : null;
$start_date = $data['start_date'];
$end_date = $data['end_date'];

try {
    $pdo = getDBConnection();
    $sql = "INSERT INTO leave_requests (user_id, recipient, leave_type, description, start_date, end_date) 
            VALUES (?, ?, ?, ?, ?, ?)";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$user_id, $recipient_json, $leave_type, $description, $start_date, $end_date]);

    sendJSONResponse(['success' => true, 'message' => 'Permohonan izin berhasil diajukan.']);

} catch (Exception $e) {
    sendJSONResponse(['success' => false, 'message' => 'Database error: ' . $e->getMessage()], 500);
}
?>