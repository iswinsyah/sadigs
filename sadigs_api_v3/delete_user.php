<?php
header('Content-Type: application/json');
require_once 'db_connect.php';

if (session_status() === PHP_SESSION_NONE) session_start();

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$user_id_to_delete = $input['user_id'] ?? null;

if (!$user_id_to_delete) {
    echo json_encode(['success' => false, 'message' => 'Invalid User ID']);
    exit;
}

// Mencegah penghapusan akun sendiri
if ($user_id_to_delete == $_SESSION['user_id']) {
    echo json_encode(['success' => false, 'message' => 'Anda tidak dapat menghapus akun Anda sendiri saat sedang login.']);
    exit;
}

try {
    $pdo = getDBConnection();
    $pdo->beginTransaction();

    // 1. Hapus Role User
    $stmt = $pdo->prepare("DELETE FROM user_roles WHERE user_id = ?");
    $stmt->execute([$user_id_to_delete]);

    // 2. Hapus User Utama
    $stmt = $pdo->prepare("DELETE FROM users WHERE user_id = ?");
    $stmt->execute([$user_id_to_delete]);

    // (Opsional: Tambahkan penghapusan data terkait lain jika perlu, misal data santri/pegawai)

    $pdo->commit();
    echo json_encode(['success' => true, 'message' => 'Pengguna berhasil dihapus.']);

} catch (Exception $e) {
    $pdo->rollBack();
    echo json_encode(['success' => false, 'message' => 'Gagal menghapus: ' . $e->getMessage()]);
}
?>