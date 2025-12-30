<?php
// API: Update Role Quotas
header('Content-Type: application/json');
require_once 'db_connect.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Hanya Admin/Yayasan yang boleh akses (Tambahkan logika cek role jika perlu)
if (!isset($_SESSION['user_id'])) {
    sendJSONResponse(['success' => false, 'message' => 'Unauthorized'], 401);
    exit;
}

// --- TAMBAHAN KEAMANAN: Cek Role Yayasan ---
$allowed_roles = ['Ketua Yayasan', 'Sekretaris Yayasan', 'Bendahara Yayasan'];
$user_roles = $_SESSION['roles'] ?? [];
$has_access = false;
foreach ($allowed_roles as $role) {
    if (in_array($role, $user_roles)) {
        $has_access = true;
        break;
    }
}
if (!$has_access) {
    sendJSONResponse(['success' => false, 'message' => 'Akses ditolak. Hanya Yayasan yang boleh mengubah kuota.'], 403);
    exit;
}
// -------------------------------------------

$input = json_decode(file_get_contents('php://input'), true);
$updates = $input['quotas'] ?? [];

if (empty($updates)) {
    sendJSONResponse(['success' => false, 'message' => 'Tidak ada data yang dikirim.'], 400);
    exit;
}

try {
    $pdo = getDBConnection();

    // Gunakan INSERT ... ON DUPLICATE KEY UPDATE untuk menyimpan/update
    $stmt = $pdo->prepare("INSERT INTO quota_settings (role_name, max_limit) VALUES (?, ?) ON DUPLICATE KEY UPDATE max_limit = VALUES(max_limit)");

    $pdo->beginTransaction();
    foreach ($updates as $role => $limit) {
        $stmt->execute([$role, (int)$limit]);
    }
    $pdo->commit();

    sendJSONResponse(['success' => true, 'message' => 'Pengaturan kuota berhasil disimpan.']);

} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    sendJSONResponse(['success' => false, 'message' => 'Database error: ' . $e->getMessage()], 500);
}
?>