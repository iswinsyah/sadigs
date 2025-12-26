<?php
header('Content-Type: application/json');
require_once 'db_connect.php';

if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['user_id'])) {
    sendJSONResponse(['success' => false, 'message' => 'Unauthorized'], 401);
}

$student_id = $_SESSION['user_id'];
$pdo = getDBConnection();

try {
    // 1. Ambil semua riwayat laporan untuk tabel
    $stmt_history = $pdo->prepare("
        SELECT * 
        FROM tahfizh_reports 
        WHERE student_id = ? 
        ORDER BY report_date DESC
    ");
    $stmt_history->execute([$student_id]);
    $history_data = $stmt_history->fetchAll(PDO::FETCH_ASSOC);

    // 2. Ambil data capaian terakhir untuk progress bar
    $stmt_summary = $pdo->prepare("
        SELECT last_juz_number 
        FROM tahfizh_reports 
        WHERE student_id = ? AND last_juz_number IS NOT NULL
        ORDER BY report_date DESC, id DESC 
        LIMIT 1
    ");
    $stmt_summary->execute([$student_id]);
    $summary_data = $stmt_summary->fetch(PDO::FETCH_ASSOC);

    sendJSONResponse(['success' => true, 'data' => $history_data, 'summary' => $summary_data]);

} catch (Exception $e) {
    sendJSONResponse(['success' => false, 'message' => 'Database error: ' . $e->getMessage()], 500);
}
?>