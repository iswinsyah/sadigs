<?php
header('Content-Type: application/json');
require_once 'db_connect.php';

if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['user_id'])) {
    sendJSONResponse(['success' => false, 'message' => 'Unauthorized'], 401);
}

$user_id = $_SESSION['user_id'];
$role_sender = $_POST['role_sender'] ?? '';
$category = $_POST['category'] ?? ''; // Ambil dari form
$period_type = $_POST['period_type'] ?? '';
$period_name = $_POST['period_name'] ?? '';
$year = $_POST['year'] ?? '';
$details_json = $_POST['details'] ?? '[]';

if (!$role_sender || !$category || !$period_type || !$year) {
    sendJSONResponse(['success' => false, 'message' => 'Data tidak lengkap.'], 400);
}

$details = json_decode($details_json, true);
$total_amount = 0;
foreach ($details as $item) {
    $total_amount += (float)($item['amount'] ?? 0);
}

try {
    $pdo = getDBConnection();
    
    // Simpan data
    $sql = "INSERT INTO operational_budgets (user_id, role_sender, category, period_type, period_name, year, details, total_amount, final_status) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'proposed')";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$user_id, $role_sender, $category, $period_type, $period_name, $year, json_encode($details), $total_amount]);

    sendJSONResponse(['success' => true, 'message' => 'Rencana anggaran berhasil diajukan.']);

} catch (Exception $e) {
    sendJSONResponse(['success' => false, 'message' => 'Database error: ' . $e->getMessage()], 500);
}
?>