<?php
header('Content-Type: application/json');
require_once 'db_connect.php';

if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['user_id'])) {
    sendJSONResponse(['success' => false, 'message' => 'Unauthorized'], 401);
}

$pdo = getDBConnection();
$user_id = $_SESSION['user_id'];
$input = json_decode(file_get_contents('php://input'), true);
$roles = $input['roles'] ?? [];

if (empty($roles)) {
    sendJSONResponse(['success' => false, 'message' => 'Pilih minimal satu peran.'], 400);
}

try {
    $pdo->beginTransaction();

    $stmt = $pdo->prepare("INSERT INTO user_roles (user_id, role_name, status) VALUES (?, ?, ?)");

    foreach ($roles as $role) {
        // Cek duplikasi
        $check = $pdo->prepare("SELECT id FROM user_roles WHERE user_id = ? AND role_name = ?");
        $check->execute([$user_id, $role]);
        
        if ($check->rowCount() == 0) {
            // Default status: PENDING
            // Kecuali Santri/Walisantri jika Anda ingin auto-approve, ubah di sini.
            // Sesuai request: "menunggu akunnya aktifasi oleh admin", jadi semua PENDING.
            $stmt->execute([$user_id, $role, 'pending']);
        }
    }

    $pdo->commit();
    sendJSONResponse(['success' => true, 'message' => 'Peran berhasil diajukan. Mohon tunggu verifikasi admin.']);

} catch (Exception $e) {
    $pdo->rollBack();
    sendJSONResponse(['success' => false, 'message' => 'Gagal menyimpan: ' . $e->getMessage()], 500);
}
?>