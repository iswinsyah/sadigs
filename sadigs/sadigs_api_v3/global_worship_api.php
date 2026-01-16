<?php
header('Content-Type: application/json');
require_once 'db_connect.php';

if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['user_id'])) {
    sendJSONResponse(['success' => false, 'message' => 'Unauthorized'], 401);
}

// Cek Role (Hanya Manajemen & Admin)
$allowed = ['Ketua Yayasan', 'Sekretaris Yayasan', 'Kepala Sekolah', 'Kepala Asrama Putra', 'Kepala Asrama Putri', 'Admin Sekolah'];
$user_roles = $_SESSION['roles'] ?? [];
if (empty(array_intersect($allowed, $user_roles))) {
    sendJSONResponse(['success' => false, 'message' => 'Akses ditolak. Khusus Manajemen.'], 403);
}

$pdo = getDBConnection();
$date = $_GET['date'] ?? date('Y-m-d');

try {
    // Ambil data laporan ibadah join dengan data user dan kelas
    $sql = "SELECT 
                dw.id, dw.report_date, dw.created_at,
                u.full_name, u.username,
                sd.grade,
                dw.subuh, dw.zuhur, dw.ashar, dw.maghrib, dw.isya,
                dw.tahajud, dw.dhuha, dw.quran_reading, dw.notes,
                dw.status as validation_status
            FROM daily_worship dw
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
        if ($row['subuh'] == 'Jamaah' && $row['zuhur'] == 'Jamaah' && $row['ashar'] == 'Jamaah' && $row['maghrib'] == 'Jamaah' && $row['isya'] == 'Jamaah') {
            $stats['full_jamaah']++;
        }
    }

    sendJSONResponse(['success' => true, 'data' => $data, 'stats' => $stats]);

} catch (Exception $e) {
    sendJSONResponse(['success' => false, 'message' => $e->getMessage()], 500);
}
?>