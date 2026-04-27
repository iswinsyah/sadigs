<?php
header('Content-Type: application/json');
require_once 'db_connect.php';

if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['user_id'])) {
    sendJSONResponse(['success' => false, 'message' => 'Unauthorized'], 401);
}

$pdo = getDBConnection();
$action = $_GET['action'] ?? '';

try {
    // --- GET: AMBIL DATA MATRIKS ---
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        // 1. Ambil Daftar Role (Kolom)
        $stmtRoles = $pdo->query("SELECT DISTINCT role_name FROM menu_permissions");
        $roles = $stmtRoles->fetchAll(PDO::FETCH_COLUMN);

        // 2. Ambil Daftar Menu (Baris)
        $stmtMenus = $pdo->query("
            SELECT m.menu_id, m.menu_name, m.category_id, mc.label as category_label, mc.sort_order
            FROM menus m
            LEFT JOIN menu_categories mc ON m.category_id = mc.category_id
            ORDER BY mc.sort_order ASC, m.menu_name ASC
        ");
        $menus = $stmtMenus->fetchAll(PDO::FETCH_ASSOC);

        // 3. Ambil Data Izin (Centang)
        $stmtPerms = $pdo->query("SELECT role_name, menu_id, is_allowed FROM menu_permissions");
        $permsRaw = $stmtPerms->fetchAll(PDO::FETCH_ASSOC);
        
        // Format permissions jadi array asosiatif: $perms[role][menu_id] = 1/0
        $perms = [];
        foreach ($permsRaw as $p) {
            $perms[$p['role_name']][$p['menu_id']] = $p['is_allowed'];
        }

        sendJSONResponse([
            'success' => true,
            'roles' => $roles,
            'menus' => $menus,
            'permissions' => $perms
        ]);
    }

    // --- POST: SIMPAN PERUBAHAN ---
    elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $input = json_decode(file_get_contents('php://input'), true);

        // --- FITUR BARU: RENAME MENU ---
        if (isset($input['action']) && $input['action'] === 'rename_menu') {
            $menu_id = $input['menu_id'] ?? '';
            $new_name = trim($input['new_name'] ?? '');

            if (empty($menu_id) || empty($new_name)) {
                sendJSONResponse(['success' => false, 'message' => 'Data tidak lengkap.'], 400);
            }

            $stmt = $pdo->prepare("UPDATE menus SET menu_name = ? WHERE menu_id = ?");
            $stmt->execute([$new_name, $menu_id]);
            sendJSONResponse(['success' => true, 'message' => 'Nama menu diperbarui.']);
            exit;
        }

        $updates = $input['updates'] ?? [];

        if (empty($updates)) {
            sendJSONResponse(['success' => true, 'message' => 'Tidak ada perubahan data.']);
        }

        $pdo->beginTransaction();
        
        $sql = "INSERT INTO menu_permissions (role_name, menu_id, is_allowed) VALUES (?, ?, ?) 
                ON DUPLICATE KEY UPDATE is_allowed = VALUES(is_allowed)";
        $stmt = $pdo->prepare($sql);

        foreach ($updates as $up) {
            $stmt->execute([$up['role'], $up['menu_id'], $up['allowed']]);
        }

        $pdo->commit();
        
        // Simulasi delay sedikit agar user sempat lihat loading (opsional, 500ms)
        usleep(500000); 

        sendJSONResponse(['success' => true, 'message' => 'Hak akses berhasil disimpan!']);
    }

} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    sendJSONResponse(['success' => false, 'message' => 'Error: ' . $e->getMessage()], 500);
}
?>