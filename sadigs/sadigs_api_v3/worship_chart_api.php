<?php
header('Content-Type: application/json');
require_once 'db_connect.php';

if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['user_id'])) {
    sendJSONResponse(['success' => false, 'message' => 'Unauthorized'], 401);
}

$user_roles = $_SESSION['roles'] ?? [];
$pdo = getDBConnection();

// Cek Akses (Apakah user punya izin di Manajemen Akses)
$placeholders = implode(',', array_fill(0, count($user_roles), '?'));
$stmtCheck = $pdo->prepare("SELECT COUNT(*) FROM menu_permissions WHERE menu_id = 'navGrafikIbadah' AND is_allowed = 1 AND role_name IN ($placeholders)");
$stmtCheck->execute($user_roles);
if ($stmtCheck->fetchColumn() == 0) {
    sendJSONResponse(['success' => false, 'message' => 'Akses ditolak. Khusus Pengurus Yayasan.'], 403);
    exit;
}

$month = $_GET['month'] ?? date('Y-m');

try {
    // Ambil semua data di bulan tersebut
    $stmt = $pdo->prepare("SELECT shalat_subuh, shalat_dzuhur, shalat_ashar, shalat_maghrib, shalat_isya FROM ibadah_harian WHERE report_date LIKE ?");
    $stmt->execute(["$month%"]);
    $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $summary = [
        'Jamaah' => 0,
        'Sendiri' => 0,
        'Bolong' => 0,
        'Haid' => 0
    ];

    $total_shalat = 0;
    foreach ($data as $row) {
        foreach (['shalat_subuh', 'shalat_dzuhur', 'shalat_ashar', 'shalat_maghrib', 'shalat_isya'] as $waktu) {
            $val = $row[$waktu] ?? '';
            if (isset($summary[$val])) {
                $summary[$val]++;
                $total_shalat++;
            }
        }
    }

    sendJSONResponse(['success' => true, 'data' => $summary, 'total' => $total_shalat]);
} catch (Exception $e) {
    sendJSONResponse(['success' => false, 'message' => $e->getMessage()], 500);
}
?>