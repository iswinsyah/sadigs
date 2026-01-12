<?php
header('Content-Type: application/json');
require_once 'db_connect.php';

if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$pdo = getDBConnection();

// Pastikan tabel ada (Auto-Create jika belum ada untuk mencegah error)
$pdo->exec("CREATE TABLE IF NOT EXISTS employee_attendance (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    attendance_date DATE NOT NULL,
    check_in_time TIME,
    check_out_time TIME,
    status ENUM('Hadir', 'Izin', 'Sakit', 'Alpa') DEFAULT 'Hadir',
    notes TEXT,
    location_lat VARCHAR(50),
    location_long VARCHAR(50),
    photo_path VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)");

try {
    // Default: Bulan ini
    $date_start = $_GET['start_date'] ?? date('Y-m-01');
    $date_end = $_GET['end_date'] ?? date('Y-m-t');
    
    $sql = "SELECT a.*, u.full_name, u.username 
            FROM employee_attendance a 
            JOIN users u ON a.user_id = u.user_id 
            WHERE a.attendance_date BETWEEN ? AND ? 
            ORDER BY a.attendance_date DESC, a.check_in_time DESC";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$date_start, $date_end]);
    $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(['success' => true, 'data' => $data]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>