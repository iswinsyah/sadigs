<?php
header('Content-Type: application/json');
require_once 'db_connect.php';

if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['user_id'])) {
    sendJSONResponse(['success' => false, 'message' => 'Unauthorized'], 401);
}

$musyrif_id = $_SESSION['user_id'];
$input = json_decode(file_get_contents('php://input'), true);

if (empty($input['student_id']) || empty($input['report_date'])) {
    sendJSONResponse(['success' => false, 'message' => 'ID Santri dan Tanggal Laporan wajib diisi.'], 400);
}

$pdo = getDBConnection();

try {
    // Security Check: Pastikan santri ini adalah binaan musyrif yang login
    $stmtCheck = $pdo->prepare("SELECT id FROM mentoring_assignments WHERE student_id = ? AND musyrif_id = ?");
    $stmtCheck->execute([$input['student_id'], $musyrif_id]);
    if ($stmtCheck->rowCount() == 0) {
        sendJSONResponse(['success' => false, 'message' => 'Akses ditolak. Anda bukan pembimbing santri ini.'], 403);
    }

    $sql = "INSERT INTO tahfizh_reports (student_id, musyrif_id, report_date, last_surah_name, last_ayah_number, last_juz_number, last_page_number, fluency_grade, tajwid_grade, adab_grade, murajaah_notes, musyrif_notes) 
            VALUES (:student_id, :musyrif_id, :report_date, :last_surah_name, :last_ayah_number, :last_juz_number, :last_page_number, :fluency_grade, :tajwid_grade, :adab_grade, :murajaah_notes, :musyrif_notes)
            ON DUPLICATE KEY UPDATE
            last_surah_name = VALUES(last_surah_name), last_ayah_number = VALUES(last_ayah_number), last_juz_number = VALUES(last_juz_number), last_page_number = VALUES(last_page_number),
            fluency_grade = VALUES(fluency_grade), tajwid_grade = VALUES(tajwid_grade), adab_grade = VALUES(adab_grade), murajaah_notes = VALUES(murajaah_notes), musyrif_notes = VALUES(musyrif_notes)";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        'student_id' => $input['student_id'],
        'musyrif_id' => $musyrif_id,
        'report_date' => $input['report_date'],
        'last_surah_name' => $input['last_surah_name'] ?? null,
        'last_ayah_number' => !empty($input['last_ayah_number']) ? (int)$input['last_ayah_number'] : null,
        'last_juz_number' => !empty($input['last_juz_number']) ? (int)$input['last_juz_number'] : null,
        'last_page_number' => !empty($input['last_page_number']) ? (int)$input['last_page_number'] : null,
        'fluency_grade' => $input['fluency_grade'] ?? null,
        'tajwid_grade' => $input['tajwid_grade'] ?? null,
        'adab_grade' => $input['adab_grade'] ?? null,
        'murajaah_notes' => $input['murajaah_notes'] ?? null,
        'musyrif_notes' => $input['musyrif_notes'] ?? null,
    ]);

    sendJSONResponse(['success' => true, 'message' => 'Laporan tahfizh berhasil disimpan.']);
} catch (Exception $e) {
    sendJSONResponse(['success' => false, 'message' => 'Gagal menyimpan: ' . $e->getMessage()], 500);
}
?>