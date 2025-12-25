<?php
header('Content-Type: application/json');
require_once 'db_connect.php';

if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['user_id'])) {
    sendJSONResponse(['success' => false, 'message' => 'Unauthorized'], 401);
}

// Hanya Bendahara Sekolah yang boleh input (sesuai request)
if (!in_array('Bendahara Sekolah', $_SESSION['roles'] ?? [])) {
    sendJSONResponse(['success' => false, 'message' => 'Akses ditolak. Hanya Bendahara Sekolah yang dapat menginput transaksi harian.'], 403);
}

$user_id = $_SESSION['user_id'];
$transaction_date = $_POST['transaction_date'] ?? null;
$type = $_POST['type'] ?? null;
$category = $_POST['category'] ?? null;
$description = $_POST['description'] ?? null;
$amount = $_POST['amount'] ?? 0;
$proof_file = $_FILES['proof_file'] ?? null;

if (!$transaction_date || !$type || !$category || !$amount) {
    sendJSONResponse(['success' => false, 'message' => 'Tanggal, Jenis, Kategori, dan Nominal wajib diisi.'], 400);
}

$proof_path = null;
if ($proof_file && $proof_file['error'] === UPLOAD_ERR_OK) {
    $upload_dir = 'uploads/transactions/';
    if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);
    
    $ext = pathinfo($proof_file['name'], PATHINFO_EXTENSION);
    $filename = uniqid('trans_', true) . '.' . $ext;
    $target = $upload_dir . $filename;
    
    if (move_uploaded_file($proof_file['tmp_name'], $target)) {
        $proof_path = $target;
    }
}

try {
    $pdo = getDBConnection();
    $sql = "INSERT INTO daily_transactions (transaction_date, type, category, description, amount, proof_file, created_by) 
            VALUES (?, ?, ?, ?, ?, ?, ?)";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        $transaction_date,
        $type,
        $category,
        $description,
        $amount,
        $proof_path,
        $user_id
    ]);

    sendJSONResponse(['success' => true, 'message' => 'Transaksi berhasil dicatat.']);

} catch (Exception $e) {
    if ($proof_path && file_exists($proof_path)) unlink($proof_path);
    sendJSONResponse(['success' => false, 'message' => 'Database error: ' . $e->getMessage()], 500);
}
?>