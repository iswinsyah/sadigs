<?php
// API: Submit Pocket Money Deposit
header('Content-Type: application/json');
require_once 'db_connect.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id'])) {
    sendJSONResponse(['success' => false, 'message' => 'Unauthorized'], 401);
    exit;
}

$walisantri_id = $_SESSION['user_id'];
$santri_id = $_POST['santri_id'] ?? null;
$amount = $_POST['amount'] ?? null;
$notes = $_POST['notes'] ?? null;

if (!$santri_id || !$amount || !isset($_FILES['proof_file'])) {
    sendJSONResponse(['success' => false, 'message' => 'Data tidak lengkap.'], 400);
    exit;
}

// --- File Upload Logic ---
$uploadDir = 'uploads/proofs/';
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0777, true);
}
$fileName = uniqid() . '-' . basename($_FILES['proof_file']['name']);
$uploadFile = $uploadDir . $fileName;

if (!move_uploaded_file($_FILES['proof_file']['tmp_name'], $uploadFile)) {
    sendJSONResponse(['success' => false, 'message' => 'Gagal mengupload file bukti.'], 500);
    exit;
}

$pdo = getDBConnection();

try {
    // 1. Dapatkan musyrif_id dari santri yang dipilih
    $stmt_musyrif = $pdo->prepare("SELECT musyrif_id FROM mentoring_groups WHERE student_id = ?");
    $stmt_musyrif->execute([$santri_id]);
    $musyrif_id = $stmt_musyrif->fetchColumn();

    // 2. Masukkan transaksi ke database
    $stmt = $pdo->prepare(
        "INSERT INTO pocket_money_transactions 
        (walisantri_id, santri_id, musyrif_id, transaction_type, amount, notes, proof_file, status) 
        VALUES (?, ?, ?, 'deposit', ?, ?, ?, 'pending')"
    );
    
    $stmt->execute([
        $walisantri_id,
        $santri_id,
        $musyrif_id, // Bisa null jika santri belum punya musyrif
        $amount,
        $notes,
        $uploadFile
    ]);

    sendJSONResponse(['success' => true, 'message' => 'Permintaan deposit berhasil dikirim dan akan segera divalidasi oleh Bendahara.']);

} catch (Exception $e) {
    // Hapus file yang sudah terupload jika DB insert gagal
    if (file_exists($uploadFile)) {
        unlink($uploadFile);
    }
    sendJSONResponse(['success' => false, 'message' => 'Database error: ' . $e->getMessage()], 500);
}
?>