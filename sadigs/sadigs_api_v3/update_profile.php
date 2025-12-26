<?php
header('Content-Type: application/json');
require_once 'db_connect.php';

if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['user_id'])) {
    sendJSONResponse(['success' => false, 'message' => 'Unauthorized'], 401);
}

$user_id = $_SESSION['user_id'];
$input = json_decode(file_get_contents('php://input'), true);

if (is_null($input)) {
    sendJSONResponse(['success' => false, 'message' => 'Invalid JSON input.'], 400);
    exit;
}

$pdo = getDBConnection();

try {
    $pdo->beginTransaction();

    // 1. Update Data Pribadi di tabel 'users'
    $updates = [];
    $params = ['user_id' => $user_id];

    if (isset($input['full_name'])) { $updates[] = "full_name = :full_name"; $params['full_name'] = $input['full_name']; }
    if (isset($input['gender'])) { $updates[] = "gender = :gender"; $params['gender'] = $input['gender']; }
    if (isset($input['bio'])) { $updates[] = "bio = :bio"; $params['bio'] = $input['bio']; }
    if (!empty($input['password'])) {
        if (strlen($input['password']) < 8) throw new Exception("Password baru harus minimal 8 karakter.");
        $updates[] = "password_hash = :password";
        $params['password'] = password_hash($input['password'], PASSWORD_DEFAULT);
    }

    if (!empty($updates)) {
        $sql_user = "UPDATE users SET " . implode(', ', $updates) . " WHERE user_id = :user_id";
        $stmt_user = $pdo->prepare($sql_user);
        $stmt_user->execute($params);
    }

    // 2. Update Peran di tabel 'user_roles'
    if (isset($input['roles']) && is_array($input['roles'])) {
        $new_roles = $input['roles'];
        $protected_roles = ['Ketua Yayasan', 'Sekretaris Yayasan', 'Bendahara Yayasan'];

        // Hapus semua peran LAMA yang TIDAK TERPROTEKSI
        $placeholders = implode(',', array_fill(0, count($protected_roles), '?'));
        $stmt_delete = $pdo->prepare("DELETE FROM user_roles WHERE user_id = ? AND role_name NOT IN ($placeholders)");
        $stmt_delete->execute(array_merge([$user_id], $protected_roles));

        // Tambahkan peran BARU (yang tidak terproteksi) dengan status 'approved'
        $stmt_insert = $pdo->prepare("INSERT IGNORE INTO user_roles (user_id, role_name, status) VALUES (?, ?, 'approved')");
        foreach ($new_roles as $role) {
            if (!in_array($role, $protected_roles)) {
                $stmt_insert->execute([$user_id, $role]);
            }
        }
    }

    $pdo->commit();
    sendJSONResponse(['success' => true, 'message' => 'Profil dan peran berhasil diperbarui. Halaman akan dimuat ulang.']);

} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    sendJSONResponse(['success' => false, 'message' => 'Gagal menyimpan: ' . $e->getMessage()], 500);
}
?>