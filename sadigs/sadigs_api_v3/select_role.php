<?php
// Matikan tampilan error PHP agar tidak merusak format JSON
ini_set('display_errors', 0);
error_reporting(E_ALL);

// Mulai buffer output
ob_start();

header('Content-Type: application/json');

// Fungsi cadangan
if (!function_exists('sendJSONResponse')) {
    function sendJSONResponse($data, $code = 200) {
        http_response_code($code);
        echo json_encode($data);
        exit;
    }
}

try {
    require_once 'db_connect.php';

    if (session_status() === PHP_SESSION_NONE) session_start();
    if (!isset($_SESSION['user_id'])) {
        throw new Exception('Unauthorized', 401);
    }

    $input = json_decode(file_get_contents('php://input'), true);
    $roles = $input['roles'] ?? [];
    $user_id = $_SESSION['user_id'];

    if (empty($roles)) {
        throw new Exception('Tidak ada peran yang dipilih.', 400);
    }

    $pdo = getDBConnection();
    $pdo->beginTransaction();

    // Siapkan statement insert
    // Status default 'pending' agar menunggu validasi admin/yayasan
    $stmt = $pdo->prepare("INSERT INTO user_roles (user_id, role_name, status) VALUES (?, ?, 'pending') ON DUPLICATE KEY UPDATE status = status");

    foreach ($roles as $role) {
        // Validasi nama role sederhana (opsional)
        $stmt->execute([$user_id, $role]);
    }

    $pdo->commit();
    
    ob_clean();
    sendJSONResponse(['success' => true, 'message' => 'Peran berhasil diajukan. Mohon tunggu validasi admin.']);

} catch (Throwable $e) {
    if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
    ob_clean();
    sendJSONResponse(['success' => false, 'message' => 'Database error: ' . $e->getMessage()], 500);
}
?>