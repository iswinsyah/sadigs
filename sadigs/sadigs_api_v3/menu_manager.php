<?php
header('Content-Type: application/json');
require_once 'db_connect.php';

if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['user_id'])) {
    sendJSONResponse(['success' => false, 'message' => 'Unauthorized'], 401);
}

$pdo = getDBConnection();

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    try {
        // 1. Ambil Daftar Menu dari tabel 'menus'
        $stmt = $pdo->query("SELECT menu_id FROM menus ORDER BY menu_name ASC");
        $menus = $stmt->fetchAll(PDO::FETCH_COLUMN);

        // 2. Ambil Daftar Role (Gabungan dari permissions dan user_roles agar lengkap)
        $roles = [];
        $stmt = $pdo->query("SELECT DISTINCT role_name FROM menu_permissions UNION SELECT DISTINCT role_name FROM user_roles");
        while ($row = $stmt->fetch(PDO::FETCH_COLUMN)) {
            if ($row) $roles[] = $row;
        }
        
        // Tambahkan role standar jika database masih kosong/baru
        $standard_roles = ['Ketua Yayasan', 'Kepala Sekolah', 'Kepala Asrama', 'Sekretaris Sekolah', 'Bendahara Sekolah', 'Musyrif', 'Ustadz', 'Santri', 'Walisantri'];
        $roles = array_unique(array_merge($roles, $standard_roles));
        sort($roles);

        // 3. Ambil Matrix Permission
        $matrix = [];
        $stmt = $pdo->query("SELECT menu_id, role_name, can_view FROM menu_permissions");
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $mid = $row['menu_id'];
            $rname = $row['role_name'];
            $val = (int)$row['can_view'];
            
            if (!isset($matrix[$mid])) $matrix[$mid] = [];
            $matrix[$mid][$rname] = $val;
        }

        sendJSONResponse([
            'success' => true,
            'menus' => $menus,
            'roles' => array_values($roles),
            'matrix' => $matrix
        ]);

    } catch (Exception $e) {
        sendJSONResponse(['success' => false, 'message' => 'Database error: ' . $e->getMessage()], 500);
    }
} 
elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    $updates = $input['updates'] ?? [];

    if (empty($updates)) {
        sendJSONResponse(['success' => false, 'message' => 'Tidak ada data perubahan.'], 400);
    }

    try {
        $pdo->beginTransaction();
        
        // Prepare statement untuk update permission
        $stmt = $pdo->prepare("INSERT INTO menu_permissions (menu_id, role_name, can_view) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE can_view = VALUES(can_view)");
        
        // Prepare statement untuk auto-insert menu jika belum ada (untuk menghindari error FK)
        $stmtCheckMenu = $pdo->prepare("SELECT COUNT(*) FROM menus WHERE menu_id = ?");
        $stmtInsertMenu = $pdo->prepare("INSERT INTO menus (menu_id, menu_name) VALUES (?, ?)");

        foreach ($updates as $u) {
            $menuId = $u['menu'];
            $roleName = $u['role'];
            $state = $u['state'];

            // Cek apakah menu ada di tabel master 'menus', jika tidak, tambahkan otomatis
            $stmtCheckMenu->execute([$menuId]);
            if ($stmtCheckMenu->fetchColumn() == 0) {
                // Buat nama menu dari ID (misal: navDashboard -> Dashboard)
                $menuName = trim(preg_replace('/(?<!\ )[A-Z]/', ' $0', str_replace('nav', '', $menuId)));
                $stmtInsertMenu->execute([$menuId, $menuName]);
            }

            $stmt->execute([$menuId, $roleName, $state]);
        }

        $pdo->commit();
        sendJSONResponse(['success' => true, 'message' => 'Pengaturan hak akses berhasil disimpan.']);
    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        sendJSONResponse(['success' => false, 'message' => 'Gagal menyimpan: ' . $e->getMessage()], 500);
    }
}
?>