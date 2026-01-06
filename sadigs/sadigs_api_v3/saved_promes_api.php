<?php
header('Content-Type: application/json');
require_once 'db_connect.php';

if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['user_id'])) {
    sendJSONResponse(['success' => false, 'message' => 'Unauthorized'], 401);
}

$pdo = getDBConnection();
$user_id = $_SESSION['user_id'];
$method = $_SERVER['REQUEST_METHOD'];
$academic_year = '2024/2025'; // Hardcoded for now

// Jaring Pengaman: Pastikan tabel ada sebelum melakukan operasi apapun
$pdo->exec("CREATE TABLE IF NOT EXISTS `saved_promes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `subject` varchar(255) NOT NULL,
  `fase` varchar(5) NOT NULL,
  `grade` varchar(10) NOT NULL,
  `cp` text DEFAULT NULL,
  `academic_year` varchar(10) NOT NULL,
  `promes_data` longtext NOT NULL,
  `status` enum('draft','submitted') NOT NULL DEFAULT 'draft',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `user_subject_grade_year` (`user_id`,`subject`,`fase`,`grade`,`academic_year`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

try {
    if ($method === 'GET') {
        $subject = isset($_GET['subject']) ? trim($_GET['subject']) : null;
        $fase = isset($_GET['fase']) ? trim($_GET['fase']) : null;
        $grade = isset($_GET['grade']) ? trim($_GET['grade']) : null;

        if (!$subject || !$grade || !$fase) {
            sendJSONResponse(['success' => false, 'message' => 'Mata Pelajaran, Fase, dan Kelas wajib diisi.'], 400);
        }

        $stmt = $pdo->prepare("SELECT promes_data, status, cp FROM saved_promes WHERE user_id = ? AND subject = ? AND fase = ? AND grade = ? AND academic_year = ?");
        $stmt->execute([$user_id, $subject, $fase, $grade, $academic_year]);
        $data = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($data) {
            sendJSONResponse(['success' => true, 'data' => json_decode($data['promes_data'], true), 'status' => $data['status'], 'cp' => $data['cp']]);
        } else {
            sendJSONResponse(['success' => false, 'message' => 'Data Promes belum tersimpan.'], 404);
        }

    } elseif ($method === 'POST') {
        $input = json_decode(file_get_contents('php://input'), true);
        $subject = isset($input['subject']) ? trim($input['subject']) : null;
        $fase = isset($input['fase']) ? trim($input['fase']) : null;
        $grade = isset($input['grade']) ? trim($input['grade']) : null;
        $promes_data = $input['promes_data'] ?? null;
        $cp = $input['cp'] ?? null;
        $action = $input['action'] ?? 'save'; // 'save', 'submit', 'unlock'

        if (!$subject || !$grade || !$fase) {
            sendJSONResponse(['success' => false, 'message' => 'Data tidak lengkap.'], 400);
        }

        if ($action === 'unlock') {
            $stmt = $pdo->prepare("UPDATE saved_promes SET status = 'draft' WHERE user_id = ? AND subject = ? AND fase = ? AND grade = ? AND academic_year = ?");
            $stmt->execute([$user_id, $subject, $fase, $grade, $academic_year]);
            sendJSONResponse(['success' => true, 'message' => 'Buku Kerja telah dibuka.']);
            exit;
        }

        if (!$promes_data) sendJSONResponse(['success' => false, 'message' => 'Data Promes tidak boleh kosong.'], 400);

        $status = ($action === 'submit') ? 'submitted' : 'draft';
        $promes_data_json = json_encode($promes_data);

        $stmt = $pdo->prepare(
            "INSERT INTO saved_promes (user_id, subject, fase, grade, cp, academic_year, promes_data, status) 
             VALUES (?, ?, ?, ?, ?, ?, ?, ?) 
             ON DUPLICATE KEY UPDATE promes_data = VALUES(promes_data), status = VALUES(status), cp = VALUES(cp)"
        );
        $stmt->execute([$user_id, $subject, $fase, $grade, $cp, $academic_year, $promes_data_json, $status]);

        sendJSONResponse(['success' => true, 'message' => 'Program Semester berhasil disimpan sebagai ' . $status . '.']);

    } elseif ($method === 'DELETE') {
        $input = json_decode(file_get_contents('php://input'), true);
        $subject = isset($input['subject']) ? trim($input['subject']) : null;
        $fase = isset($input['fase']) ? trim($input['fase']) : null;
        $grade = isset($input['grade']) ? trim($input['grade']) : null;

        if (!$subject || !$grade || !$fase) {
            sendJSONResponse(['success' => false, 'message' => 'Data tidak lengkap untuk menghapus.'], 400);
        }

        $stmt = $pdo->prepare("DELETE FROM saved_promes WHERE user_id = ? AND subject = ? AND fase = ? AND grade = ? AND academic_year = ?");
        $stmt->execute([$user_id, $subject, $fase, $grade, $academic_year]);

        if ($stmt->rowCount() > 0) {
            sendJSONResponse(['success' => true, 'message' => 'Data Buku Kerja berhasil dihapus.']);
        } else {
            sendJSONResponse(['success' => true, 'message' => 'Tidak ada data yang cocok untuk dihapus.']);
        }
    }
} catch (Exception $e) {
    sendJSONResponse(['success' => false, 'message' => $e->getMessage()], 500);
}
?>