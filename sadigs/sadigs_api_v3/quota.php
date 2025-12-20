<?php
// =================================================================
// SADIGS 3.0: QUOTA MANAGEMENT API
// =================================================================
ob_start();
require_once 'db_connect.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$pdo = getDBConnection();

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    // PUBLIC ACCESS: Mengambil data kuota dan status ketersediaan
    try {
        $stmt = $pdo->query("SELECT role_name, max_limit FROM quota_settings");
        $settings = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $result = [];
        // Query untuk menghitung jumlah user AKTIF per role
        $sql_count = "SELECT COUNT(*) FROM user_roles ur JOIN users u ON ur.user_id = u.user_id WHERE ur.role_name = :role AND u.is_active = 1";
        $stmt_count = $pdo->prepare($sql_count);

        foreach ($settings as $row) {
            $role = $row['role_name'];
            $limit = (int)$row['max_limit'];
            
            $stmt_count->execute(['role' => $role]);
            $current = (int)$stmt_count->fetchColumn();
            
            $result[$role] = [
                'max_limit' => $limit,
                'current_count' => $current,
                'is_full' => ($limit > 0 && $current >= $limit)
            ];
        }

        sendJSONResponse(['success' => true, 'quotas' => $result]);
    } catch (Exception $e) {
        sendJSONResponse(['success' => false, 'message' => 'Gagal mengambil data kuota.'], 500);
    }

} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // PROTECTED ACCESS: Hanya Yayasan yang boleh mengubah
    $allowed_roles = ['Ketua Yayasan', 'Sekretaris Yayasan', 'Bendahara Yayasan'];
    $user_roles = $_SESSION['roles'] ?? [];
    if (empty(array_intersect($allowed_roles, $user_roles))) {
        sendJSONResponse(['success' => false, 'message' => 'Akses ditolak. Hanya Yayasan yang berhak.'], 403);
    }

    // Simpan data kuota
    $data = json_decode(file_get_contents("php://input"), true);
    
    if (!$data) {
        sendJSONResponse(['success' => false, 'message' => 'Data tidak valid.'], 400);
    }

    try {
        $pdo->beginTransaction();
        
        // Gunakan INSERT ... ON DUPLICATE KEY UPDATE agar jika belum ada dibuat, jika ada diupdate
        // FIX: Menggunakan parameter berbeda untuk UPDATE karena PDO::ATTR_EMULATE_PREPARES = false
        $sql = "INSERT INTO quota_settings (role_name, max_limit) VALUES (:role, :limit_val) 
                ON DUPLICATE KEY UPDATE max_limit = :limit_update";
        $stmt = $pdo->prepare($sql);

        foreach ($data as $role => $limit) {
            $stmt->execute([
                'role' => $role, 
                'limit_val' => (int)$limit,
                'limit_update' => (int)$limit
            ]);
        }
        
        $pdo->commit();
        sendJSONResponse(['success' => true, 'message' => 'Pengaturan kuota berhasil disimpan.']);
    } catch (Exception $e) {
        $pdo->rollBack();
        sendJSONResponse(['success' => false, 'message' => 'Gagal menyimpan data: ' . $e->getMessage()], 500);
    }
} else {
    sendJSONResponse(['success' => false, 'message' => 'Metode tidak diizinkan.'], 405);
}
?>