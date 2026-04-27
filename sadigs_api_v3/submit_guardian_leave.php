<?php
header('Content-Type: application/json');
require_once 'db_connect.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized: Anda harus login.']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);

// Validasi sederhana
if (empty($input['student_name']) || empty($input['musyrif']) || empty($input['leave_type'])) {
    echo json_encode(['success' => false, 'message' => 'Data tidak lengkap.']);
    exit;
}

$user_id = $_SESSION['user_id'];
$student_name = $input['student_name'];
$musyrif_username = $input['musyrif'];
$leave_type = $input['leave_type'];
$reason = $input['reason'];
$start_datetime = $input['start_date'] . ' ' . $input['start_time'];
$end_datetime = $input['end_date'] . ' ' . $input['end_time'];

try {
    $pdo = getDBConnection();
    $stmt = $pdo->prepare("INSERT INTO guardian_leave_requests 
        (user_id, student_name, musyrif_username, leave_type, reason, start_datetime, end_datetime, status) 
        VALUES (?, ?, ?, ?, ?, ?, ?, 'pending')");
    
    $stmt->execute([
        $user_id, 
        $student_name, 
        $musyrif_username, 
        $leave_type, 
        $reason, 
        $start_datetime, 
        $end_datetime
    ]);

    echo json_encode(['success' => true, 'message' => 'Pengajuan berhasil disimpan.']);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>