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
        // Bersihkan semua buffer output sebelum kirim JSON
        while (ob_get_level()) { ob_end_clean(); }
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

    $rawInput = file_get_contents('php://input');
    $input = json_decode($rawInput, true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception('Invalid JSON input', 400);
    }

    $roles = $input['roles'] ?? [];
    $user_id = $_SESSION['user_id'];

    if (empty($roles)) {
        throw new Exception('Tidak ada peran yang dipilih.', 400);
    }

    $pdo = getDBConnection();

    $pdo->beginTransaction();

    // Tentukan status
    $status = isset($_SESSION['impersonator_user_id']) ? 'approved' : 'pending';
    
    // Gunakan pendekatan SELECT lalu INSERT/UPDATE (Lebih aman & kompatibel semua MySQL)
    $stmtCheck = $pdo->prepare("SELECT id FROM user_roles WHERE user_id = ? AND role_name = ?");
    $stmtInsert = $pdo->prepare("INSERT INTO user_roles (user_id, role_name, status) VALUES (?, ?, ?)");
    $stmtUpdate = $pdo->prepare("UPDATE user_roles SET status = ? WHERE id = ?");

    foreach ($roles as $role) {
        $stmtCheck->execute([$user_id, $role]);
        $existing = $stmtCheck->fetch(PDO::FETCH_ASSOC);

        if ($existing) {
            $stmtUpdate->execute([$status, $existing['id']]);
        } else {
            $stmtInsert->execute([$user_id, $role, $status]);
        }
    }

    $pdo->commit();
    
    ob_clean();
    sendJSONResponse(['success' => true, 'message' => ($status === 'approved' ? 'Peran berhasil ditambahkan (Auto-Approved).' : 'Peran berhasil diajukan. Mohon tunggu validasi admin.')]);

} catch (Throwable $e) {
    if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
    sendJSONResponse(['success' => false, 'message' => 'Server Error: ' . $e->getMessage()], 500);
}
?>