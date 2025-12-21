<?php
require_once 'db_connect.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id'])) {
    sendJSONResponse(['success' => false, 'message' => 'Sesi tidak valid.'], 401);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    $selected_roles = $input['roles'] ?? [];
    $user_id = $_SESSION['user_id'];

    if (empty($selected_roles)) {
        sendJSONResponse(['success' => false, 'message' => 'Pilih setidaknya satu peran.'], 400);
        exit;
    }

    try {
        $pdo = getDBConnection();
        $pdo->beginTransaction();

        // 1. Hapus peran lama jika ada (untuk kasus pengajuan ulang)
        $stmt = $pdo->prepare("DELETE FROM user_roles WHERE user_id = ?");
        $stmt->execute([$user_id]);

        // 2. Masukkan peran baru yang diajukan
        $sql = "INSERT INTO user_roles (user_id, role_name, status) VALUES (?, ?, 'pending')";
        $stmt = $pdo->prepare($sql);
        foreach ($selected_roles as $role) {
            $stmt->execute([$user_id, $role]);
        }

        // 3. **PENTING**: Jangan non-aktifkan akunnya. Biarkan is_active = 1
        // agar user bisa login kembali dan melihat status "pending" di profilnya.
        // Cukup hancurkan sesi agar mereka harus login ulang.

        $pdo->commit();

        session_destroy(); // Hancurkan sesi agar user harus login ulang
        sendJSONResponse(['success' => true, 'message' => 'Peran berhasil diajukan. Silakan login kembali. Akun Anda sedang menunggu verifikasi.']);

    } catch (Exception $e) {
        $pdo->rollBack();
        sendJSONResponse(['success' => false, 'message' => 'Terjadi kesalahan server: ' . $e->getMessage()], 500);
    }
}
?>