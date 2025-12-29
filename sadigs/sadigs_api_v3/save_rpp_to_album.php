<?php
// API: Menyimpan RPP ke Album
header('Content-Type: application/json');
require_once 'db_connect.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id'])) {
    sendJSONResponse(['success' => false, 'message' => 'Unauthorized'], 401);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);

if (empty($input['subject']) || empty($input['topic']) || empty($input['rpp_content'])) {
    sendJSONResponse(['success' => false, 'message' => 'Data RPP tidak lengkap.'], 400);
    exit;
}

$user_id = $_SESSION['user_id'];
$subject = $input['subject'];
$grade = $input['grade'] ?? null;
$topic = $input['topic'];
$rpp_content = $input['rpp_content'];

try {
    $pdo = getDBConnection();
    $stmt = $pdo->prepare(
        "INSERT INTO rpp_album (user_id, subject, grade, topic, rpp_content) VALUES (?, ?, ?, ?, ?)"
    );
    $stmt->execute([$user_id, $subject, $grade, $topic, $rpp_content]);

    sendJSONResponse(['success' => true, 'message' => 'RPP berhasil disimpan ke album.']);

} catch (Exception $e) {
    sendJSONResponse(['success' => false, 'message' => 'Database error: ' . $e->getMessage()], 500);
}
?>