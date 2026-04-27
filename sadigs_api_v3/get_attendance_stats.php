<?php
header('Content-Type: application/json');
require_once 'db_connect.php';

if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$type = $_GET['type'] ?? 'daily';
$pdo = getDBConnection();

try {
    $labels = [];
    $data = [];

    if ($type === 'daily') {
        // Data 30 hari terakhir
        $sql = "SELECT DATE_FORMAT(attendance_date, '%d %b') as lbl, COUNT(*) as total 
                FROM employee_attendance 
                WHERE attendance_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY) 
                GROUP BY attendance_date 
                ORDER BY attendance_date ASC";
    } else {
        // Data per bulan tahun ini
        $sql = "SELECT DATE_FORMAT(attendance_date, '%M') as lbl, COUNT(*) as total 
                FROM employee_attendance 
                WHERE YEAR(attendance_date) = YEAR(CURDATE()) 
                GROUP BY MONTH(attendance_date) 
                ORDER BY MONTH(attendance_date) ASC";
    }

    $stmt = $pdo->query($sql);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($rows as $row) {
        $labels[] = $row['lbl'];
        $data[] = $row['total'];
    }

    echo json_encode(['success' => true, 'labels' => $labels, 'data' => $data]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>