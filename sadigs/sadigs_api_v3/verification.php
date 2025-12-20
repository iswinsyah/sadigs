<?php
// =================================================================
// SADIGS 3.0: VERIFICATION API
// =================================================================
ob_start();
require_once 'db_connect.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Cek Otorisasi (Hanya Yayasan)
$allowed_roles = ['Ketua Yayasan', 'Sekretaris Yayasan', 'Bendahara Yayasan'];
$user_roles = $_SESSION['roles'] ?? [];
if (empty(array_intersect($allowed_roles, $user_roles))) {
    sendJSONResponse(['success' => false, 'message' => 'Akses ditolak.'], 403);
}

$pdo = getDBConnection();

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    // Ambil daftar user yang belum aktif (is_active = 0)
    try {
        // Kita gunakan GROUP_CONCAT untuk menggabungkan role jika user punya banyak role
        $sql = "
            SELECT u.user_id, u.username, u.email, u.created_at,
                   GROUP_CONCAT(ur.role_name SEPARATOR ', ') as roles
            FROM users u
            LEFT JOIN user_roles ur ON u.user_id = ur.user_id
            WHERE u.is_active = 0
            GROUP BY u.user_id
            ORDER BY u.created_at DESC
        ";
        $stmt = $pdo->query($sql);
        $users = $stmt->fetchAll();
        
        sendJSONResponse(['success' => true, 'pending_users' => $users]);
    } catch (Exception $e) {
        sendJSONResponse(['success' => false, 'message' => 'Gagal memuat data.'], 500);
    }

} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Handle Aktivasi atau Edit Role
    $data = json_decode(file_get_contents("php://input"), true);
    $user_id = $data['user_id'] ?? null;
    $action = $data['action'] ?? 'activate'; // 'activate' atau 'update_roles'

    if (!$user_id) {
        sendJSONResponse(['success' => false, 'message' => 'User ID diperlukan.'], 400);
    }

    try {
        if ($action === 'activate') {
            // AKTIVASI AKUN
            $sql = "UPDATE users SET is_active = 1 WHERE user_id = :user_id";
            $stmt = $pdo->prepare($sql);
            $stmt->execute(['user_id' => $user_id]);
            
            sendJSONResponse(['success' => true, 'message' => 'Akun berhasil diaktifkan.']);
            
        } elseif ($action === 'update_roles') {
            // UPDATE ROLE
            $new_roles = $data['roles'] ?? [];
            if (empty($new_roles)) {
                sendJSONResponse(['success' => false, 'message' => 'Minimal satu peran harus dipilih.'], 400);
            }

            $pdo->beginTransaction();

            // 1. Hapus role lama
            $stmt_del = $pdo->prepare("DELETE FROM user_roles WHERE user_id = :user_id");
            $stmt_del->execute(['user_id' => $user_id]);

            // 2. Cek Kuota (Opsional: Bisa dilewati untuk Admin, tapi baiknya dicek)
            // Di sini kita skip cek kuota agar Admin punya kuasa penuh (override)

            // 3. Insert role baru
            $stmt_ins = $pdo->prepare("INSERT INTO user_roles (user_id, role_name) VALUES (:user_id, :role_name)");
            foreach ($new_roles as $role) {
                $stmt_ins->execute(['user_id' => $user_id, 'role_name' => $role]);
            }

            $pdo->commit();
            sendJSONResponse(['success' => true, 'message' => 'Peran berhasil diperbarui.']);
        }

    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        sendJSONResponse(['success' => false, 'message' => 'Terjadi kesalahan: ' . $e->getMessage()], 500);
    }
}
?>