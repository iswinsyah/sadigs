<?php
header('Content-Type: application/json');
require_once 'db_connect.php';

if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['user_id']) || !in_array('Musyrif', $_SESSION['roles'] ?? [])) {
    sendJSONResponse(['success' => false, 'message' => 'Akses ditolak. Hanya untuk Musyrif.'], 403);
}

$pdo = getDBConnection();
$musyrif_id = $_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $student_id = $_GET['student_id'] ?? null;
    $report_date = $_GET['date'] ?? null;

    if (!$student_id || !$report_date) {
        sendJSONResponse(['success' => false, 'message' => 'ID Santri dan tanggal diperlukan.'], 400);
    }

    try {
        $stmt = $pdo->prepare("SELECT * FROM tahfizh_reports WHERE student_id = ? AND report_date = ?");
        $stmt->execute([$student_id, $report_date]);
        $data = $stmt->fetch(PDO::FETCH_ASSOC);
        sendJSONResponse(['success' => true, 'data' => $data ?: null]);
    } catch (Exception $e) {
        sendJSONResponse(['success' => false, 'message' => $e->getMessage()], 500);
    }
}

elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);

    // --- Security Check: Pastikan Musyrif hanya mengisi untuk santri binaannya ---
    $stmtCheck = $pdo->prepare("SELECT COUNT(*) FROM mentoring_assignments WHERE student_id = ? AND musyrif_id = ?");
    $stmtCheck->execute([$input['student_id'], $musyrif_id]);
    if ($stmtCheck->fetchColumn() == 0) {
        sendJSONResponse(['success' => false, 'message' => 'Akses ditolak. Anda bukan pembimbing santri ini.'], 403);
    }

    try {
        $sql = "INSERT INTO tahfizh_reports (
                    student_id, musyrif_id, report_date, 
                    last_surah_name, last_ayah_number, last_juz_number, last_page_number,
                    fluency_grade, tajwid_grade, adab_grade,
                    murajaah_notes, musyrif_notes
                ) VALUES (
                    :sid, :mid, :rdate,
                    :surah, :ayah, :juz, :page,
                    :fluency, :tajwid, :adab,
                    :murajaah, :notes
                )
                ON DUPLICATE KEY UPDATE
                    last_surah_name = VALUES(last_surah_name), last_ayah_number = VALUES(last_ayah_number),
                    last_juz_number = VALUES(last_juz_number), last_page_number = VALUES(last_page_number),
                    fluency_grade = VALUES(fluency_grade), tajwid_grade = VALUES(tajwid_grade),
                    adab_grade = VALUES(adab_grade), murajaah_notes = VALUES(murajaah_notes),
                    musyrif_notes = VALUES(musyrif_notes)";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            'sid' => $input['student_id'],
            'mid' => $musyrif_id,
            'rdate' => $input['report_date'],
            'surah' => $input['last_surah_name'] ?: null,
            'ayah' => $input['last_ayah_number'] ?: null,
            'juz' => $input['last_juz_number'] ?: null,
            'page' => $input['last_page_number'] ?: null,
            'fluency' => $input['fluency_grade'] ?: null,
            'tajwid' => $input['tajwid_grade'] ?: null,
            'adab' => $input['adab_grade'] ?: null,
            'murajaah' => $input['murajaah_notes'] ?: null,
            'notes' => $input['musyrif_notes'] ?: null,
        ]);

        sendJSONResponse(['success' => true, 'message' => 'Laporan Tahfizh berhasil disimpan.']);
    } catch (Exception $e) {
        sendJSONResponse(['success' => false, 'message' => 'Gagal menyimpan: ' . $e->getMessage()], 500);
    }
}
?>