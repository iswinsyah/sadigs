<?php
// API: Get Allowed Menus for Current User
header('Content-Type: application/json');
require_once 'db_connect.php';

if (session_status() === PHP_SESSION_NONE) session_start();

if (!isset($_SESSION['user_id'])) {
    sendJSONResponse(['success' => false, 'message' => 'Unauthorized'], 401);
    exit;
}

$roles = $_SESSION['roles'] ?? [];
if (empty($roles)) {
    sendJSONResponse(['success' => true, 'permissions' => []]);
    exit;
}

$pdo = getDBConnection();

// Pastikan tabel ada (untuk menghindari error jika file ini dipanggil duluan)
$pdo->exec("CREATE TABLE IF NOT EXISTS menu_permissions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    role_name VARCHAR(50) NOT NULL,
    menu_id VARCHAR(100) NOT NULL,
    is_allowed TINYINT(1) DEFAULT 0,
    UNIQUE KEY unique_perm (role_name, menu_id)
)");

// Ambil menu yang diizinkan untuk role user saat ini
$placeholders = implode(',', array_fill(0, count($roles), '?'));
$sql = "SELECT DISTINCT menu_id FROM menu_permissions WHERE role_name IN ($placeholders) AND is_allowed = 1";
$stmt = $pdo->prepare($sql);
$stmt->execute($roles);
$permissions = $stmt->fetchAll(PDO::FETCH_COLUMN);

// --- BYPASS DARURAT: PAKSA AKSES KETUA YAYASAN ---
// Memastikan Ketua Yayasan SELALU punya akses ke Manajemen Menu & Dashboard
// meskipun database kosong atau error.
if (in_array('Ketua Yayasan', $roles)) {
    $wajibAda = ['navDashboard', 'navMenuManagement', 'navVerifikasi', 'navQuota'];
    foreach ($wajibAda as $m) {
        if (!in_array($m, $permissions)) $permissions[] = $m;
    }
}
// -------------------------------------------------

sendJSONResponse(['success' => true, 'permissions' => $permissions]);
?>