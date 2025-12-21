<?php
header('Content-Type: application/json');
require_once 'db_connect.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 1. Cek Login & Otorisasi (Hanya Ketua Yayasan)
if (!isset($_SESSION['user_id'])) {
    sendJSONResponse(['success' => false, 'message' => 'Unauthorized'], 401);
}

$allowed_roles = ['Ketua Yayasan'];
$user_roles = $_SESSION['roles'] ?? [];
if (empty(array_intersect($allowed_roles, $user_roles))) {
    sendJSONResponse(['success' => false, 'message' => 'Akses ditolak. Hanya Ketua Yayasan.'], 403);
}

$pdo = getDBConnection();

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    try {
        // Ambil semua data permissions
        $stmt = $pdo->query("SELECT * FROM menu_permissions ORDER BY menu_id, role_name");
        $permissions = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Ambil daftar Role unik dan Menu unik untuk header tabel
        $roles = array_unique(array_column($permissions, 'role_name'));
        $menus = array_unique(array_column($permissions, 'menu_id'));
        sort($roles);
        sort($menus);

        // Format data agar mudah dibaca frontend (Matrix)
        $matrix = [];
        foreach ($permissions as $p) {
            $matrix[$p['menu_id']][$p['role_name']] = (int)$p['can_view'];
        }

        sendJSONResponse([
            'success' => true,
            'roles' => array_values($roles),
            'menus' => array_values($menus),
            'matrix' => $matrix
        ]);
    } catch (Exception $e) {
        sendJSONResponse(['success' => false, 'message' => $e->getMessage()], 500);
    }

} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    $updates = $input['updates'] ?? [];

    if (empty($updates)) {
        sendJSONResponse(['success' => false, 'message' => 'Tidak ada data perubahan.'], 400);
    }

    try {
        $pdo->beginTransaction();
        
        $sql = "INSERT INTO menu_permissions (role_name, menu_id, can_view) VALUES (:role, :menu, :view)
                ON DUPLICATE KEY UPDATE can_view = :view_update";
        $stmt = $pdo->prepare($sql);

        foreach ($updates as $item) {
            $stmt->execute([
                'role' => $item['role'],
                'menu' => $item['menu'],
                'view' => $item['state'],
                'view_update' => $item['state']
            ]);
        }

        $pdo->commit();
        sendJSONResponse(['success' => true, 'message' => 'Izin menu berhasil diperbarui.']);
    } catch (Exception $e) {
        $pdo->rollBack();
        sendJSONResponse(['success' => false, 'message' => 'Gagal menyimpan: ' . $e->getMessage()], 500);
    }
}
?>