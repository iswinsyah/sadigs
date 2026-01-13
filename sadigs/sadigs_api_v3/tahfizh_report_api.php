<?php
header('Content-Type: application/json');
require_once 'db_connect.php';

if (session_status() === PHP_SESSION_NONE) session_start();
// Perbaikan Izin: Izinkan Musyrif, Ustadz, Ustadzah, Kepala Asrama
$allowed_roles = ['Musyrif', 'Musyrifah', 'Ustadz', 'Ustadzah', 'Kepala Asrama Putra', 'Kepala Asrama Putri', 'Walisantri', 'Santri Rijal', "Santri Nisa'"];
if (!isset($_SESSION['user_id'])) {
    sendJSONResponse(['success' => false, 'message' => 'Akses ditolak. Anda tidak memiliki izin input tahfidz.'], 403);
}

$pdo = getDBConnection();
$musyrif_id = $_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $action = $_GET['action'] ?? 'get_single';

    if ($action === 'get_history') {
        // LOGIKA RIWAYAT (Untuk Walisantri, Santri, & Musyrif)
        $user_id = $_SESSION['user_id'];
        $roles = $_SESSION['roles'] ?? [];
        $username = $_SESSION['username'];

        try {
            if (in_array('Walisantri', $roles)) {
                // Walisantri: Cari anak berdasarkan parent_username
                $stmtChild = $pdo->prepare("SELECT user_id FROM users WHERE user_id IN (SELECT user_id FROM student_details WHERE parent_username = ?)");
                $stmtChild->execute([$username]);
                $children = $stmtChild->fetchAll(PDO::FETCH_COLUMN);
                
                if (empty($children)) {
                    sendJSONResponse(['success' => true, 'data' => [], 'message' => 'Belum ada santri yang terhubung. Hubungi admin untuk menautkan akun.']);
                }
                
                $placeholders = implode(',', array_fill(0, count($children), '?'));
                $sql = "SELECT r.*, u.full_name as student_name, t.full_name as teacher_name 
                        FROM tahfizh_reports r 
                        JOIN users u ON r.student_id = u.user_id 
                        LEFT JOIN users t ON r.musyrif_id = t.user_id
                        WHERE r.student_id IN ($placeholders) 
                        ORDER BY r.report_date DESC LIMIT 50";
                $stmt = $pdo->prepare($sql);
                $stmt->execute($children);
                sendJSONResponse(['success' => true, 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);

            } else {
                // Santri / Musyrif (Lihat berdasarkan parameter atau diri sendiri)
                $target_id = $_GET['student_id'] ?? $user_id;
                
                $sql = "SELECT r.*, u.full_name as student_name, t.full_name as teacher_name 
                        FROM tahfizh_reports r 
                        JOIN users u ON r.student_id = u.user_id 
                        LEFT JOIN users t ON r.musyrif_id = t.user_id
                        WHERE r.student_id = ? 
                        ORDER BY r.report_date DESC LIMIT 50";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([$target_id]);
                sendJSONResponse(['success' => true, 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
            }
        } catch (Exception $e) {
            sendJSONResponse(['success' => false, 'message' => $e->getMessage()], 500);
        }
    } else {
        // LOGIKA SINGLE REPORT (Existing)
        $student_id = $_GET['student_id'] ?? null;
        $report_date = $_GET['date'] ?? null;
        $stmt = $pdo->prepare("SELECT * FROM tahfizh_reports WHERE student_id = ? AND report_date = ?");
        $stmt->execute([$student_id, $report_date]);
        sendJSONResponse(['success' => true, 'data' => $stmt->fetch(PDO::FETCH_ASSOC) ?: null]);
    } catch (Exception $e) {
        sendJSONResponse(['success' => false, 'message' => $e->getMessage()], 500);
    }
}

elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);

    // Validasi Dasar
    if (empty($input['student_id']) || empty($input['last_surah_name'])) {
        sendJSONResponse(['success' => false, 'message' => 'Nama Santri dan Nama Surat wajib diisi.'], 400);
        exit;
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