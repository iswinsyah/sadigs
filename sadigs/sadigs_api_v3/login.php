<?php
ob_start();
require_once 'db_connect.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    $username = $input['username'] ?? '';
    $password = $input['password'] ?? '';

    if (empty($username) || empty($password)) {
        sendJSONResponse(['success' => false, 'message' => 'Username dan password wajib diisi.'], 400);
        exit;
    }

    try {
        $pdo = getDBConnection();
        $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ?");
        $stmt->execute([$username]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user && password_verify($password, $user['password_hash'])) {
            
            // Ambil semua peran user
            $role_stmt = $pdo->prepare("SELECT role_name, status FROM user_roles WHERE user_id = ?");
            $role_stmt->execute([$user['user_id']]);
            $roles_data = $role_stmt->fetchAll(PDO::FETCH_ASSOC);

            $approved_roles = [];
            $has_pending_roles = false;
            foreach ($roles_data as $role) {
                if ($role['status'] === 'approved') {
                    $approved_roles[] = $role['role_name'];
                }
                if ($role['status'] === 'pending') {
                    $has_pending_roles = true;
                }
            }

            // Cek jika user tidak aktif (ditolak/dinonaktifkan manual)
            if ($user['is_active'] == 0) {
                sendJSONResponse(['success' => false, 'message' => 'Akun Anda tidak aktif. Hubungi administrator.'], 403);
                exit;
            }

            // Buat Sesi
            if (session_status() === PHP_SESSION_NONE) session_start();
            $_SESSION['user_id'] = $user['user_id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['roles'] = $approved_roles;

            // Tentukan halaman redirect
            $redirect_path = ($has_pending_roles || empty($approved_roles)) ? 'profile.html' : 'dashboard.html';

            sendJSONResponse(['success' => true, 'message' => 'Login berhasil!', 'redirect_path' => $redirect_path]);

        } else {
            sendJSONResponse(['success' => false, 'message' => 'Username atau password salah.'], 401);
        }
    } catch (Exception $e) {
        sendJSONResponse(['success' => false, 'message' => 'Terjadi kesalahan server: ' . $e->getMessage()], 500);
    }
}
?>