<?php
header('Content-Type: application/json');
require_once 'db_connect.php';

if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$pdo = getDBConnection();

// Auto-Create Table jika belum ada
$pdo->exec("CREATE TABLE IF NOT EXISTS tahfizh_reports (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL,
    teacher_id INT NOT NULL,
    report_date DATE NOT NULL,
    type VARCHAR(20) NOT NULL, -- Ziyadah / Murajaah
    surah VARCHAR(100) NOT NULL,
    ayat VARCHAR(50) NOT NULL,
    quality VARCHAR(50) NOT NULL,
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)");

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (empty($input['student_id']) || empty($input['surah']) || empty($input['ayat'])) {
        echo json_encode(['success' => false, 'message' => 'Data tidak lengkap.']);
        exit;
    }

    try {
        $stmt = $pdo->prepare("INSERT INTO tahfizh_reports (student_id, teacher_id, report_date, type, surah, ayat, quality, notes) VALUES (?, ?, CURDATE(), ?, ?, ?, ?, ?)");
        $stmt->execute([
            $input['student_id'],
            $_SESSION['user_id'],
            $input['type'],
            $input['surah'],
            $input['ayat'],
            $input['quality'],
            $input['notes'] ?? ''
        ]);
        echo json_encode(['success' => true, 'message' => 'Laporan Tahfidz berhasil disimpan!']);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Gagal menyimpan: ' . $e->getMessage()]);
    }
}
?>