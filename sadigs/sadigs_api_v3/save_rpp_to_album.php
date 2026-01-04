<?php
header('Content-Type: application/json');
// Matikan display error agar tidak merusak format JSON
ini_set('display_errors', 0);
error_reporting(0);

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
    
    // Pastikan tabel rpp_album ada (Auto-create jika belum ada)
    $pdo->exec("CREATE TABLE IF NOT EXISTS `rpp_album` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `user_id` INT NOT NULL,
        `subject` VARCHAR(100) NOT NULL,
        `grade` VARCHAR(50) NOT NULL,
        `topic` VARCHAR(255) NOT NULL,
        `rpp_content` TEXT NOT NULL,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX `idx_user_id` (`user_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");

    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input) {
        sendJSONResponse(['success' => false, 'message' => 'Invalid JSON input'], 400);
        exit;
    }

    $subject = isset($input['subject']) ? trim($input['subject']) : '';
    $grade = isset($input['grade']) ? trim($input['grade']) : '';
    $topic = isset($input['topic']) ? trim($input['topic']) : '';
    $rpp_content = isset($input['rpp_content']) ? trim($input['rpp_content']) : '';

    if (empty($subject) || empty($grade) || empty($topic) || empty($rpp_content)) {
        sendJSONResponse(['success' => false, 'message' => 'Data tidak lengkap. Pastikan semua field terisi.'], 400);
        exit;
    }

    $stmt = $pdo->prepare("INSERT INTO rpp_album (user_id, subject, grade, topic, rpp_content) VALUES (?, ?, ?, ?, ?)");
    $stmt->execute([$user_id, $subject, $grade, $topic, $rpp_content]);

    sendJSONResponse(['success' => true, 'message' => 'Modul Ajar berhasil disimpan ke Album.']);

} catch (Exception $e) {
    sendJSONResponse(['success' => false, 'message' => 'Database error: ' . $e->getMessage()], 500);
}
?>