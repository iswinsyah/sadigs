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

try {
    if ($method === 'GET') {
        $subject = $_GET['subject'] ?? null;
        $grade = $_GET['grade'] ?? null;
        $academic_year = $_GET['academic_year'] ?? '2024/2025';

        if (!$subject || !$grade) {
            sendJSONResponse(['success' => true, 'data' => ['ganjil' => [], 'genap' => []]]);
            exit;
        }

        $stmt = $pdo->prepare("SELECT * FROM annual_programs WHERE user_id = ? AND subject = ? AND grade = ? AND academic_year = ? ORDER BY created_at ASC");
        $stmt->execute([$user_id, $subject, $grade, $academic_year]);
        $all_objectives = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $data = ['ganjil' => [], 'genap' => []];
        foreach ($all_objectives as $obj) {
            if ($obj['semester'] === 'Ganjil') $data['ganjil'][] = $obj;
            else $data['genap'][] = $obj;
        }

        sendJSONResponse(['success' => true, 'data' => $data]);

    } elseif ($method === 'POST') {
        $input = json_decode(file_get_contents('php://input'), true);
        if (empty($input['subject']) || empty($input['grade']) || empty($input['semester']) || empty($input['learning_objective'])) {
            throw new Exception("Data tidak lengkap.", 400);
        }

        $stmt = $pdo->prepare("INSERT INTO annual_programs (user_id, subject, grade, academic_year, semester, learning_objective, estimated_hours) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            $user_id,
            $input['subject'],
            $input['grade'],
            $input['academic_year'] ?? '2024/2025',
            $input['semester'],
            $input['learning_objective'],
            $input['estimated_hours'] ?? null
        ]);
        sendJSONResponse(['success' => true, 'message' => 'Tujuan Pembelajaran berhasil ditambahkan.']);

    } elseif ($method === 'DELETE') {
        $id = $_GET['id'] ?? null;
        if (!$id) throw new Exception("ID tidak ditemukan.", 400);

        $stmt = $pdo->prepare("DELETE FROM annual_programs WHERE id = ? AND user_id = ?");
        $stmt->execute([$id, $user_id]);
        sendJSONResponse(['success' => true, 'message' => 'Tujuan Pembelajaran berhasil dihapus.']);
    }

} catch (Exception $e) {
    sendJSONResponse(['success' => false, 'message' => $e->getMessage()], $e->getCode() ?: 500);
}
?>