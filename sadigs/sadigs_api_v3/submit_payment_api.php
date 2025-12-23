<?php
header('Content-Type: application/json');
require_once 'db_connect.php';

if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['user_id'])) {
    sendJSONResponse(['success' => false, 'message' => 'Unauthorized'], 401);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendJSONResponse(['success' => false, 'message' => 'Method not allowed'], 405);
}

$pdo = getDBConnection();

try {
    $student_id = $_POST['student_id'];
    $payment_type = $_POST['payment_type'];
    $amount = $_POST['amount'];
    $payment_date = $_POST['payment_date'];
    $notes = $_POST['notes'] ?? '';

    // Handle File Upload
    $proof_path = null;
    if (isset($_FILES['proof_file']) && $_FILES['proof_file']['error'] === UPLOAD_ERR_OK) {
        $uploadDir = '../uploads/payments/';
        if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);

        $ext = pathinfo($_FILES['proof_file']['name'], PATHINFO_EXTENSION);
        $filename = 'pay_' . time() . '_' . $student_id . '.' . $ext;
        $targetPath = $uploadDir . $filename;

        if (move_uploaded_file($_FILES['proof_file']['tmp_name'], $targetPath)) {
            // Simpan path relatif untuk database (agar bisa diakses dari frontend)
            $proof_path = 'uploads/payments/' . $filename;
        } else {
            throw new Exception("Gagal mengupload file bukti.");
        }
    }

    $stmt = $pdo->prepare("INSERT INTO payments (student_id, payment_type, amount, payment_date, proof_file, notes, status) VALUES (?, ?, ?, ?, ?, ?, 'pending')");
    $stmt->execute([$student_id, $payment_type, $amount, $payment_date, $proof_path, $notes]);

    // --- NOTIFIKASI WHATSAPP (OPSIONAL/PLACEHOLDER) ---
    // Di sini Anda bisa menambahkan kode untuk kirim WA ke Bendahara
    // sendWhatsAppToBendahara(...);

    sendJSONResponse(['success' => true, 'message' => 'Pembayaran berhasil dikirim. Menunggu validasi.']);

} catch (Exception $e) {
    sendJSONResponse(['success' => false, 'message' => $e->getMessage()], 500);
}
?>