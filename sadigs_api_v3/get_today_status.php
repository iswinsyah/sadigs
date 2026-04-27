<?php
header('Content-Type: application/json');
require_once 'db_connect.php';

if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$user_id = $_SESSION['user_id'];
$pdo = getDBConnection();

// Update Schema: Tambah kolom category jika belum ada
try { $pdo->exec("ALTER TABLE employee_attendance ADD COLUMN category VARCHAR(50) DEFAULT 'Absensi Harian'"); } catch (Exception $e) { }

try {
    $stmt = $pdo->prepare("SELECT * FROM employee_attendance WHERE user_id = ? AND attendance_date = CURDATE()");
    $stmt->execute([$user_id]);
    $data = $stmt->fetchAll(PDO::FETCH_ASSOC); // Ambil semua (bisa harian + rapat)

    echo json_encode(['success' => true, 'data' => $data]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>