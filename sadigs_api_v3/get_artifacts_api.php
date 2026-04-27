<?php
header('Content-Type: application/json');
require_once 'db_connect.php';

if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['user_id'])) {
    sendJSONResponse(['success' => false, 'message' => 'Unauthorized'], 401);
    exit;
}

$user_id = $_SESSION['user_id'];
$subject = $_GET['subject'] ?? '';
$grade = $_GET['grade'] ?? '';
$type = $_GET['type'] ?? '';

// Validasi parameter
if (empty($subject) || empty($grade) || empty($type)) {
    sendJSONResponse(['success' => false, 'message' => 'Parameter subject, grade, dan type wajib diisi.'], 400);
    exit;
}

try {
    $pdo = getDBConnection();
    
    // Jaring pengaman: Cek apakah tabel sudah ada untuk menghindari error
    $check = $pdo->query("SHOW TABLES LIKE 'teaching_artifacts'");
    if ($check->rowCount() == 0) {
        // Jika tabel belum ada, kirim array data kosong.
        sendJSONResponse(['success' => true, 'data' => []]);
        exit;
    }

    // Query untuk mengambil data artefak
    $stmt = $pdo->prepare(
        "SELECT id, topic, tp, content, status, created_at 
         FROM teaching_artifacts 
         WHERE user_id = ? AND subject = ? AND grade = ? AND type = ? 
         ORDER BY created_at DESC"
    );
    $stmt->execute([$user_id, $subject, $grade, $type]);
    $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

    sendJSONResponse(['success' => true, 'data' => $data]);

} catch (Exception $e) {
    // Tangkap semua error dan kirim sebagai JSON
    http_response_code(500);
    sendJSONResponse(['success' => false, 'message' => 'Error Server: ' . $e->getMessage()]);
}
?>