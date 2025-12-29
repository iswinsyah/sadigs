<?php
// API: Menyimpan RPP ke Album
// Matikan tampilan error PHP agar tidak merusak format JSON
ini_set('display_errors', 0);
error_reporting(E_ALL);

// Mulai buffer output untuk menangkap error tak terduga
ob_start();

header('Content-Type: application/json');

// Fungsi cadangan jika db_connect.php gagal dimuat
if (!function_exists('sendJSONResponse')) {
    function sendJSONResponse($data, $code = 200) {
        http_response_code($code);
        echo json_encode($data);
        exit;
    }
}

try {
    require_once 'db_connect.php';

    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    if (!isset($_SESSION['user_id'])) {
        throw new Exception('Unauthorized', 401);
    }

    $input = json_decode(file_get_contents('php://input'), true);

    if (empty($input['subject']) || empty($input['topic']) || empty($input['rpp_content'])) {
        throw new Exception('Data RPP tidak lengkap.', 400);
    }

    $user_id = $_SESSION['user_id'];
    $subject = $input['subject'];
    $grade = $input['grade'] ?? null;
    $topic = $input['topic'];
    $rpp_content = $input['rpp_content'];

    $pdo = getDBConnection();

    // --- AUTO MIGRATION: Buat tabel jika belum ada ---
    $pdo->exec("CREATE TABLE IF NOT EXISTS rpp_album (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        subject VARCHAR(255) NOT NULL,
        grade VARCHAR(100),
        topic VARCHAR(255) NOT NULL,
        rpp_content TEXT NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )");
    $stmt = $pdo->prepare(
        "INSERT INTO rpp_album (user_id, subject, grade, topic, rpp_content) VALUES (?, ?, ?, ?, ?)"
    );
    $stmt->execute([$user_id, $subject, $grade, $topic, $rpp_content]);

    ob_clean();
    sendJSONResponse(['success' => true, 'message' => 'RPP berhasil disimpan ke album.']);

} catch (Throwable $e) {
    ob_clean();
    sendJSONResponse(['success' => false, 'message' => 'Server Error: ' . $e->getMessage()], 500);
}
?>