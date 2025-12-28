<?php
header('Content-Type: application/json');
require_once 'db_connect.php';

if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['user_id'])) {
    sendJSONResponse(['success' => false, 'message' => 'Unauthorized'], 401);
}

$pdo = getDBConnection();
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
    try {
        $stmt = $pdo->query("SELECT role_name, menu_id, can_view FROM menu_permissions");
        $permissions = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $matrix = [];
        foreach ($permissions as $p) {
            $matrix[$p['menu_id']][$p['role_name']] = (int)$p['can_view'];
        }

        sendJSONResponse(['success' => true, 'roles' => $allRoles, 'matrix' => $matrix]);
    } catch (Exception $e) {
        // Jika tabel belum ada, kembalikan matrix kosong tapi sukses agar UI tidak macet
        sendJSONResponse(['success' => true, 'roles' => $allRoles, 'matrix' => []]);
    }
} 
elseif ($method === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    $updates = $input['updates'] ?? [];

    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare("INSERT INTO menu_permissions (role_name, menu_id, can_view) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE can_view = VALUES(can_view)");
        
        foreach ($updates as $u) {
            $stmt->execute([$u['role'], $u['menu'], $u['state']]);
        }
        
        $pdo->commit();
        sendJSONResponse(['success' => true]);
    } catch (Exception $e) {
        $pdo->rollBack();
        sendJSONResponse(['success' => false, 'message' => $e->getMessage()], 500);
    }
}
?>