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

    // --- AUTO MIGRATION: Buat tabel jika belum ada (DILUAR TRANSAKSI) ---
    $pdo->exec("CREATE TABLE IF NOT EXISTS user_roles (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        role_name VARCHAR(50) NOT NULL,
        status VARCHAR(20) DEFAULT 'pending',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY unique_user_role (user_id, role_name)
    )");
    // ------------------------------------------------

    $pdo->beginTransaction();

    // Siapkan statement insert
    // LOGIKA BARU: Jika sedang Impersonate (Admin yang memilihkan), langsung 'approved'
    // Jika user sendiri yang memilih, tetap 'pending'
    $status = isset($_SESSION['impersonator_user_id']) ? 'approved' : 'pending';
    
    $stmt = $pdo->prepare("INSERT INTO user_roles (user_id, role_name, status) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE status = VALUES(status)");

    foreach ($roles as $role) {
        $stmt->execute([$user_id, $role, $status]);
    }

    $pdo->commit();
    
    ob_clean();
    sendJSONResponse(['success' => true, 'message' => ($status === 'approved' ? 'Peran berhasil ditambahkan (Auto-Approved).' : 'Peran berhasil diajukan. Mohon tunggu validasi admin.')]);

} catch (Throwable $e) {
    if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
    ob_clean();
    sendJSONResponse(['success' => false, 'message' => 'Database error: ' . $e->getMessage()], 500);
}
?>