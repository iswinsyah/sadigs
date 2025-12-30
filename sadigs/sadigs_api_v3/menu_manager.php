<?php
// API: Manage Menu Permissions (Get Matrix & Update)
header('Content-Type: application/json');
require_once 'db_connect.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id'])) {
    sendJSONResponse(['success' => false, 'message' => 'Unauthorized'], 401);
    exit;
}

$pdo = getDBConnection();

// 1. Pastikan tabel menu_permissions ada
$pdo->exec("CREATE TABLE IF NOT EXISTS menu_permissions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    role_name VARCHAR(50) NOT NULL,
    menu_id VARCHAR(100) NOT NULL,
    is_allowed TINYINT(1) DEFAULT 0,
    UNIQUE KEY unique_perm (role_name, menu_id)
)");

// 2. Seed Default Permission jika tabel kosong (Agar Ketua Yayasan tidak terkunci)
$stmtCount = $pdo->query("SELECT COUNT(*) FROM menu_permissions");
if ($stmtCount->fetchColumn() == 0) {
    // Berikan akses vital ke Ketua Yayasan
    $defaults = [
        ['Ketua Yayasan', 'navMenuManagement'],
        ['Ketua Yayasan', 'navDashboard'],
        ['Ketua Yayasan', 'navVerifikasi'],
        ['Ketua Yayasan', 'navQuota']
    ];
    $stmtInsert = $pdo->prepare("INSERT INTO menu_permissions (role_name, menu_id, is_allowed) VALUES (?, ?, 1)");
    foreach ($defaults as $d) {
        $stmtInsert->execute($d);
    }
}

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    try {
        // Daftar Peran (Sesuai dengan quota.php agar konsisten)
        $allRoles = [
            'Ketua Yayasan', 'Sekretaris Yayasan', 'Bendahara Yayasan',
            'Kepala Sekolah', 'Sekretaris Sekolah', 'Bendahara Sekolah',
            'Kepala Ma\'had', 'Kepala Asrama Putra', 'Kepala Asrama Putri',
            'Musyrif', 'Musyrifah', 'Ustadz', 'Ustadzah',
            'Santri Rijal', 'Santri Nisa\'', 'Walisantri'
        ];

        // Ambil data permission yang ada
        $stmt = $pdo->query("SELECT role_name, menu_id, is_allowed FROM menu_permissions");
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $matrix = [];
        foreach ($rows as $row) {
            if (!isset($matrix[$row['menu_id']])) {
                $matrix[$row['menu_id']] = [];
            }
            $matrix[$row['menu_id']][$row['role_name']] = (int)$row['is_allowed'];
        }

        sendJSONResponse(['success' => true, 'roles' => $allRoles, 'matrix' => $matrix]);

    } catch (Exception $e) {
        sendJSONResponse(['success' => false, 'message' => $e->getMessage()], 500);
    }
} elseif ($method === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    $updates = $input['updates'] ?? [];

    try {
        $stmt = $pdo->prepare("INSERT INTO menu_permissions (role_name, menu_id, is_allowed) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE is_allowed = VALUES(is_allowed)");
        foreach ($updates as $update) {
            $stmt->execute([$update['role'], $update['menu'], $update['state']]);
        }
        sendJSONResponse(['success' => true]);
    } catch (Exception $e) {
        sendJSONResponse(['success' => false, 'message' => $e->getMessage()], 500);
    }
}
?>