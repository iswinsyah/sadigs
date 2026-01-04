<?php
// API: Get RPP Album List
header('Content-Type: application/json');
require_once 'db_connect.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id'])) {
    sendJSONResponse(['success' => false, 'message' => 'Unauthorized'], 401);
    exit;
}

$user_id = $_SESSION['user_id'];

try {
    $pdo = getDBConnection();
    $method = $_SERVER['REQUEST_METHOD'];

    if ($method === 'GET') {
        if (isset($_GET['id'])) {
            // Ambil detail satu RPP
            $id = $_GET['id'];
            $stmt = $pdo->prepare("SELECT topic, rpp_content FROM rpp_album WHERE id = ? AND user_id = ?");
            $stmt->execute([$id, $user_id]);
            $data = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($data) {
                sendJSONResponse(['success' => true, 'data' => $data]);
            } else {
                sendJSONResponse(['success' => false, 'message' => 'Modul Ajar tidak ditemukan.'], 404);
            }
        } else {
            // Ambil daftar RPP milik user yang sedang login
            $stmt = $pdo->prepare("SELECT id, subject, grade, topic, DATE_FORMAT(created_at, '%d %b %Y') as created_at_formatted FROM rpp_album WHERE user_id = ? ORDER BY created_at DESC");
            $stmt->execute([$user_id]);
            $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
            sendJSONResponse(['success' => true, 'data' => $data]);
        }
    } elseif ($method === 'DELETE') {
        $input = json_decode(file_get_contents('php://input'), true);
        $id = $input['id'] ?? null;

        if (!$id) {
            sendJSONResponse(['success' => false, 'message' => 'ID tidak ditemukan.'], 400);
            exit;
        }

        $stmt = $pdo->prepare("DELETE FROM rpp_album WHERE id = ? AND user_id = ?");
        $stmt->execute([$id, $user_id]);
        sendJSONResponse(['success' => true, 'message' => 'Modul Ajar berhasil dihapus.']);
    }

} catch (Exception $e) {
    sendJSONResponse(['success' => false, 'message' => 'Database error: ' . $e->getMessage()], 500);
}
?>