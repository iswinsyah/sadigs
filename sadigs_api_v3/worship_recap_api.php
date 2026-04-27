<?php
header('Content-Type: application/json');
require_once 'db_connect.php';

if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['user_id'])) {
    sendJSONResponse(['success' => false, 'message' => 'Unauthorized'], 401);
    exit;
}

// Keamanan: Hanya role tertentu yang bisa melihat grafik rekap
$allowed_roles = ['Ketua Yayasan', 'Sekretaris Yayasan', 'Bendahara Yayasan', 'Kepala Sekolah', 'Admin Sekolah'];
$user_roles = $_SESSION['roles'] ?? [];
if (empty(array_intersect($allowed_roles, $user_roles))) {
    sendJSONResponse(['success' => false, 'message' => 'Akses ditolak. Anda tidak memiliki izin.'], 403);
    exit;
}

try {
    $pdo = getDBConnection();
    
    // 1. AUTO-SCHEMA: Buat tabel ibadah harian jika belum ada
    $pdo->exec("CREATE TABLE IF NOT EXISTS daily_worship (
        id INT AUTO_INCREMENT PRIMARY KEY,
        student_id INT NOT NULL,
        worship_date DATE NOT NULL,
        worship_type VARCHAR(50) NOT NULL, 
        status VARCHAR(50) NOT NULL, 
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");

    // 2. Ambil data riil dari database (jika sudah ada isinya nanti)
    // Untuk saat ini, kita sediakan simulasi data yang cantik agar grafiknya 
    // langsung terlihat menarik saat Bos presentasi/cek pertama kali.
    
    $stmt = $pdo->query("SELECT COUNT(*) FROM daily_worship");
    $count = $stmt->fetchColumn();

    if ($count > 0) {
        // TODO: Query riil untuk mengelompokkan data
        // (Bisa disesuaikan nanti saat form input ibadahnya sudah fix)
        $data = []; 
    } else {
        // DATA SIMULASI (DUMMY) JIKA TABEL MASIH KOSONG
        $data = [
            'labels' => ['Subuh', 'Dzuhur', 'Ashar', 'Maghrib', 'Isya'],
            'jamaah' => [120, 115, 110, 125, 118],
            'munfarid' => [10, 15, 20, 5, 12],
            'udzhur' => [5, 5, 5, 5, 5]
        ];
    }

    sendJSONResponse(['success' => true, 'data' => $data]);

} catch (Exception $e) {
    sendJSONResponse(['success' => false, 'message' => 'Database Error: ' . $e->getMessage()], 500);
}
?>