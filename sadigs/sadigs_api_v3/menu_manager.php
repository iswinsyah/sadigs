<?php
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

    if (session_status() === PHP_SESSION_NONE) session_start();
    if (!isset($_SESSION['user_id'])) {
        throw new Exception('Unauthorized', 401);
    }

    $pdo = getDBConnection();

    // --- AUTO MIGRATION: Buat tabel jika belum ada ---
    $pdo->exec("CREATE TABLE IF NOT EXISTS menu_permissions (
        id INT AUTO_INCREMENT PRIMARY KEY,
        role_name VARCHAR(50) NOT NULL,
        menu_id VARCHAR(100) NOT NULL,
        can_view TINYINT(1) DEFAULT 0,
        UNIQUE KEY unique_permission (role_name, menu_id)
    )");
    // ------------------------------------------------

    $method = $_SERVER['REQUEST_METHOD'];

    if ($method === 'GET') {
        // 1. Daftar Semua Peran (Hardcoded agar urutan sesuai hierarki)
        $allRoles = [
            'Ketua Yayasan', 'Sekretaris Yayasan', 'Bendahara Yayasan',
            'Kepala Sekolah', 'Sekretaris Sekolah', 'Bendahara Sekolah',
            'Kepala Ma\'had', 'Kepala Asrama Putra', 'Kepala Asrama Putri',
            'Musyrif', 'Musyrifah', 'Ustadz', 'Ustadzah',
            'Santri Rijal', 'Santri Nisa\'', 'Walisantri'
        ];

        // 2. Ambil Matrix Izin dari Database
        $stmt = $pdo->query("SELECT role_name, menu_id, can_view FROM menu_permissions");
        $permissions = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $matrix = [];
        foreach ($permissions as $p) {
            $matrix[$p['menu_id']][$p['role_name']] = (int)$p['can_view'];
        }

        // Bersihkan buffer sebelum kirim output
        ob_clean();
        sendJSONResponse(['success' => true, 'roles' => $allRoles, 'matrix' => $matrix]);
    }
    elseif ($method === 'POST') {
        $input = json_decode(file_get_contents('php://input'), true);
        $updates = $input['updates'] ?? [];

        $pdo->beginTransaction();
        $stmt = $pdo->prepare("INSERT INTO menu_permissions (role_name, menu_id, can_view) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE can_view = VALUES(can_view)");
        
        foreach ($updates as $u) {
            $stmt->execute([$u['role'], $u['menu'], $u['state']]);
        }
        
        $pdo->commit();
        
        ob_clean();
        sendJSONResponse(['success' => true]);
    }

} catch (Throwable $e) { // Ubah Exception menjadi Throwable untuk menangkap semua error
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    ob_clean(); // Hapus output error PHP yang mungkin muncul
    sendJSONResponse(['success' => false, 'message' => $e->getMessage()], 500);
}
?>