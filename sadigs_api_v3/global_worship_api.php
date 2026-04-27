<?php
header('Content-Type: application/json');
require_once 'db_connect.php';

if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['user_id'])) {
    sendJSONResponse(['success' => false, 'message' => 'Unauthorized'], 401);
}

$user_roles = $_SESSION['roles'] ?? [];
if (empty($user_roles)) {
    sendJSONResponse(['success' => false, 'message' => 'Akses ditolak. Anda tidak memiliki peran aktif.'], 403);
    exit;
}

$pdo = getDBConnection();

// Cek dinamis: Apakah salah satu peran user punya centang hijau di Manajemen Akses untuk menu ini?
$placeholders = implode(',', array_fill(0, count($user_roles), '?'));
$stmtCheck = $pdo->prepare("SELECT COUNT(*) FROM menu_permissions WHERE menu_id = 'navMonitoringIbadah' AND is_allowed = 1 AND role_name IN ($placeholders)");
$stmtCheck->execute($user_roles);
$hasAccess = $stmtCheck->fetchColumn() > 0;

if (!$hasAccess) {
    sendJSONResponse(['success' => false, 'message' => 'Akses ditolak. Anda tidak memiliki izin untuk melihat rekap ibadah (Silakan atur di Manajemen Akses).'], 403);
    exit;
}

$date = $_GET['date'] ?? date('Y-m-d');

try {
    // Ambil data laporan ibadah join dengan data user dan kelas
    $sql = "SELECT 
                dw.id, dw.report_date, dw.created_at,
                u.full_name, u.username,
                sd.grade,
                dw.shalat_subuh, dw.shalat_dzuhur, dw.shalat_ashar, dw.shalat_maghrib, dw.shalat_isya,
                dw.shalat_tahajud, dw.shalat_dhuha, dw.quran_last_page, dw.notes,
                dw.validation_status
            FROM ibadah_harian dw
            JOIN users u ON dw.user_id = u.user_id
            LEFT JOIN student_details sd ON u.user_id = sd.user_id
            WHERE dw.report_date = ?
            ORDER BY sd.grade ASC, u.full_name ASC";
            
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$date]);
    $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Hitung statistik ringkas
    $stats = [
        'total_laporan' => count($data),
        'full_jamaah' => 0,
        'validated' => 0
    ];
    
    foreach ($data as $row) {
        if ($row['validation_status'] === 'approved') $stats['validated']++;
        // Asumsi full jamaah jika semua 5 waktu = 'Jamaah'
        if ($row['shalat_subuh'] == 'Jamaah' && $row['shalat_dzuhur'] == 'Jamaah' && $row['shalat_ashar'] == 'Jamaah' && $row['shalat_maghrib'] == 'Jamaah' && $row['shalat_isya'] == 'Jamaah') {
            $stats['full_jamaah']++;
        }
    }

    sendJSONResponse(['success' => true, 'data' => $data, 'stats' => $stats]);

} catch (Exception $e) {
    sendJSONResponse(['success' => false, 'message' => $e->getMessage()], 500);
}
?>