<?php
// =================================================================
// SADIGS 3.0: QUOTA MANAGEMENT API
// =================================================================
ob_start();
require_once 'db_connect.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Cek Otorisasi (Hanya Yayasan)
$allowed_roles = ['Ketua Yayasan', 'Sekretaris Yayasan', 'Bendahara Yayasan'];
$user_roles = $_SESSION['roles'] ?? [];
$has_access = !empty(array_intersect($allowed_roles, $user_roles));

if (!$has_access) {
    sendJSONResponse(['success' => false, 'message' => 'Akses ditolak. Hanya Yayasan yang berhak.'], 403);
}

$pdo = getDBConnection();

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    // Ambil data kuota saat ini
    try {
        $stmt = $pdo->query("SELECT role_name, max_limit FROM quota_settings");
        $quotas = $stmt->fetchAll(PDO::FETCH_KEY_PAIR); // Output: ['Kepala Sekolah' => 1, 'Ustadz' => 10]
        sendJSONResponse(['success' => true, 'quotas' => $quotas]);
    } catch (Exception $e) {
        sendJSONResponse(['success' => false, 'message' => 'Gagal mengambil data kuota.'], 500);
    }

} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
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