<?php
header('Content-Type: application/json');
require_once 'db_connect.php';

if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['user_id'])) {
    sendJSONResponse(['success' => false, 'message' => 'Unauthorized'], 401);
}

if (!in_array('Walisantri', $_SESSION['roles'] ?? [])) {
    sendJSONResponse(['success' => false, 'message' => 'Hanya Walisantri yang dapat mengakses fitur ini.'], 403);
}

$walisantri_user_id = $_SESSION['user_id'];
$student_username = trim($_POST['student_username'] ?? '');
$payment_date = $_POST['payment_date'] ?? null;
$details_json = $_POST['details'] ?? '[]';
$notes = $_POST['notes'] ?? null;
$proof_file = $_FILES['proof_file'] ?? null;

if (!$student_username || !$payment_date || !$proof_file || $details_json === '[]') {
    sendJSONResponse(['success' => false, 'message' => 'Semua kolom wajib diisi, termasuk rincian dan bukti transfer.'], 400);
}

$details = json_decode($details_json, true);
if (json_last_error() !== JSON_ERROR_NONE) {
    sendJSONResponse(['success' => false, 'message' => 'Format rincian pembayaran tidak valid.'], 400);
}

$total_amount = 0;
foreach ($details as $item) {
    $total_amount += (float)($item['amount'] ?? 0);
}

$upload_dir = 'uploads/proofs/';
if (!is_dir($upload_dir)) {
    mkdir($upload_dir, 0777, true);
}
$file_extension = pathinfo($proof_file['name'], PATHINFO_EXTENSION);
$file_name = uniqid('proof_', true) . '.' . $file_extension;
$target_file = $upload_dir . $file_name;

$allowed_types = ['jpg', 'jpeg', 'png', 'pdf'];
if (!in_array(strtolower($file_extension), $allowed_types)) {
    sendJSONResponse(['success' => false, 'message' => 'Format file bukti transfer harus JPG, PNG, atau PDF.'], 400);
}

if ($proof_file['size'] > 2 * 1024 * 1024) { // 2MB
    sendJSONResponse(['success' => false, 'message' => 'Ukuran file bukti transfer tidak boleh lebih dari 2MB.'], 400);
}

if (!move_uploaded_file($proof_file['tmp_name'], $target_file)) {
    sendJSONResponse(['success' => false, 'message' => 'Gagal mengupload file bukti transfer.'], 500);
}

try {
    $pdo = getDBConnection();

    // Cari student_user_id berdasarkan username (case-sensitive)
    // Menggunakan BINARY untuk perbandingan case-sensitive
    $stmt_user = $pdo->prepare("SELECT user_id FROM users WHERE username = BINARY ?");
    $stmt_user->execute([$student_username]);
    $student_user_id = $stmt_user->fetchColumn();

    if (!$student_user_id) {
        sendJSONResponse(['success' => false, 'message' => "Username santri '$student_username' tidak ditemukan. Pastikan penulisan sudah benar (termasuk besar kecilnya huruf)."], 404);
    }

    $sql = "INSERT INTO payments (walisantri_user_id, student_user_id, payment_date, details, total_amount, proof_file, notes) 
            VALUES (?, ?, ?, ?, ?, ?, ?)";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        $walisantri_user_id,
        $student_user_id,
        $payment_date,
        json_encode($details),
        $total_amount,
        $target_file,
        $notes
    ]);

    sendJSONResponse(['success' => true, 'message' => 'Konfirmasi pembayaran berhasil dikirim dan sedang menunggu validasi.']);

} catch (Exception $e) {
    if (file_exists($target_file)) {
        unlink($target_file);
    }
    sendJSONResponse(['success' => false, 'message' => 'Database error: ' . $e->getMessage()], 500);
}
?>