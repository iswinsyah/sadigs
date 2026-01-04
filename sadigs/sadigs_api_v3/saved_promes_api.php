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

try {
    if ($method === 'GET') {
        $subject = isset($_GET['subject']) ? trim($_GET['subject']) : null;
        $grade = isset($_GET['grade']) ? trim($_GET['grade']) : null;

        if (!$subject || !$grade) {
            sendJSONResponse(['success' => false, 'message' => 'Mata Pelajaran dan Kelas wajib diisi.'], 400);
        }

        $stmt = $pdo->prepare("SELECT promes_data FROM saved_promes WHERE user_id = ? AND subject = ? AND grade = ? AND academic_year = ?");
        $stmt->execute([$user_id, $subject, $grade, $academic_year]);
        $data = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($data) {
            sendJSONResponse(['success' => true, 'data' => json_decode($data['promes_data'], true)]);
        } else {
            sendJSONResponse(['success' => false, 'message' => 'Data Promes belum tersimpan.'], 404);
        }

    } elseif ($method === 'POST') {
        $input = json_decode(file_get_contents('php://input'), true);
        $subject = isset($input['subject']) ? trim($input['subject']) : null;
        $grade = isset($input['grade']) ? trim($input['grade']) : null;
        $promes_data = $input['promes_data'] ?? null;

        if (!$subject || !$grade || !$promes_data) {
            sendJSONResponse(['success' => false, 'message' => 'Data tidak lengkap.'], 400);
        }

        $promes_data_json = json_encode($promes_data);

        $stmt = $pdo->prepare(
            "INSERT INTO saved_promes (user_id, subject, grade, academic_year, promes_data) 
             VALUES (?, ?, ?, ?, ?) 
             ON DUPLICATE KEY UPDATE promes_data = VALUES(promes_data)"
        );
        $stmt->execute([$user_id, $subject, $grade, $academic_year, $promes_data_json]);

        sendJSONResponse(['success' => true, 'message' => 'Program Semester berhasil disimpan!']);

    } elseif ($method === 'DELETE') {
        $input = json_decode(file_get_contents('php://input'), true);
        $subject = isset($input['subject']) ? trim($input['subject']) : null;
        $grade = isset($input['grade']) ? trim($input['grade']) : null;

        if (!$subject || !$grade) {
            sendJSONResponse(['success' => false, 'message' => 'Data tidak lengkap untuk menghapus.'], 400);
        }

        $stmt = $pdo->prepare("DELETE FROM saved_promes WHERE user_id = ? AND subject = ? AND grade = ? AND academic_year = ?");
        $stmt->execute([$user_id, $subject, $grade, $academic_year]);

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