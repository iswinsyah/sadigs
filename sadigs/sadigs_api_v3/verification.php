<?php
header('Content-Type: application/json');
require_once 'db_connect.php';

if (session_status() === PHP_SESSION_NONE) session_start();
// (Tambahkan cek role 'Ketua Yayasan'/'Sekretaris Yayasan' di sini untuk keamanan)

$pdo = getDBConnection();

// --- GET: List Pending Users ---
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    try {
        // Ambil user yang punya setidaknya satu role 'pending'
        $sql = "SELECT u.user_id, u.username, u.email, 
                       GROUP_CONCAT(ur.role_name SEPARATOR ', ') as roles
                FROM users u
                JOIN user_roles ur ON u.user_id = ur.user_id
                WHERE ur.status = 'pending'
                GROUP BY u.user_id";
        
        $stmt = $pdo->query($sql);
        $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        sendJSONResponse(['success' => true, 'pending_users' => $users]);
    } catch (Exception $e) {
        sendJSONResponse(['success' => false, 'message' => $e->getMessage()], 500);
    }
}

// --- POST: Activate / Update Roles ---
elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    $target_user_id = $input['user_id'];
    $action = $input['action']; // 'activate' or 'update_roles'

    try {
        $pdo->beginTransaction();

        if ($action === 'activate') {
            // Ubah semua role pending menjadi approved untuk user ini
            $stmt = $pdo->prepare("UPDATE user_roles SET status = 'approved' WHERE user_id = ? AND status = 'pending'");
            $stmt->execute([$target_user_id]);
            
            // Aktifkan user di tabel users juga (jika ada flag is_active)
            $stmt2 = $pdo->prepare("UPDATE users SET is_active = 1 WHERE user_id = ?");
            $stmt2->execute([$target_user_id]);
            
            $msg = "Akun berhasil diaktifkan.";
        } 
        elseif ($action === 'update_roles') {
            // Hapus role lama (yang pending/approved) dan ganti dengan yang baru (langsung approved)
            // Ini fitur "Edit Peran" sebelum aktivasi
            $new_roles = $input['roles'] ?? [];
            
            // Hapus semua role user ini
            $pdo->prepare("DELETE FROM user_roles WHERE user_id = ?")->execute([$target_user_id]);
            
            // Insert role baru dengan status approved (karena diedit oleh admin)
            $stmt = $pdo->prepare("INSERT INTO user_roles (user_id, role_name, status) VALUES (?, ?, 'approved')"); // Langsung approved? Atau pending?
            // Biasanya kalau admin yang edit, langsung approved saja agar sekalian aktif.
            // Tapi jika tombolnya "Simpan Perubahan" lalu ada tombol "Aktifkan" terpisah, bisa pending.
            // Mari kita buat 'pending' agar alurnya konsisten: Edit -> Save -> Klik Aktifkan.
            
            foreach ($new_roles as $role) {
                $stmt->execute([$target_user_id, $role, 'pending']);
            }
            $msg = "Peran diperbarui. Silakan klik 'Aktifkan Akun' untuk memvalidasi.";
        }

        $pdo->commit();
        sendJSONResponse(['success' => true, 'message' => $msg]);

    } catch (Exception $e) {
        $pdo->rollBack();
        sendJSONResponse(['success' => false, 'message' => $e->getMessage()], 500);
    }
}
?>