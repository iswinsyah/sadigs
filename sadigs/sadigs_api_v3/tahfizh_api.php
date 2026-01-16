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
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS tahfizh_reports (
        id INT AUTO_INCREMENT PRIMARY KEY,
        student_id INT NOT NULL,
        musyrif_id INT NOT NULL,
        report_date DATE NOT NULL,
        type VARCHAR(20) NOT NULL,
        surah VARCHAR(100) NOT NULL,
        ayat VARCHAR(50) NOT NULL,
        quality VARCHAR(50) NOT NULL,
        notes TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");

    // --- AUTO-MIGRATE: Pastikan kolom ada (untuk kompatibilitas tabel lama) ---
    $columns = [
        'musyrif_id' => "INT NOT NULL",
        'surah' => "VARCHAR(100) NULL",
        'ayat' => "VARCHAR(50) NULL",
        'quality' => "VARCHAR(50) NULL",
        'type' => "VARCHAR(20) DEFAULT 'Ziyadah'"
    ];
    
    foreach ($columns as $col => $def) {
        try {
            $pdo->query("SELECT $col FROM tahfizh_reports LIMIT 1");
        } catch (Exception $e) {
            $pdo->exec("ALTER TABLE tahfizh_reports ADD COLUMN $col $def");
        }
    }
} catch (Exception $e) { /* Ignore */ }

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $action = $_GET['action'] ?? '';

    if ($action === 'get_my_students') {
        try {
            $teacher_id = $_SESSION['user_id'];
            
            // Ambil santri yang dimentori oleh guru/musyrif yang login
            $stmt = $pdo->prepare("
                SELECT u.user_id, u.full_name
                FROM users u
                JOIN mentoring_assignments ma ON u.user_id = ma.student_id
                WHERE ma.musyrif_id = ?
                ORDER BY u.full_name ASC
            ");
            $stmt->execute([$teacher_id]);
            $students = $stmt->fetchAll(PDO::FETCH_ASSOC);

            echo json_encode(['success' => true, 'data' => $students]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => 'Gagal mengambil data santri: ' . $e->getMessage()]);
        }
        exit;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (empty($input['student_id']) || empty($input['surah']) || empty($input['ayat'])) {
        echo json_encode(['success' => false, 'message' => 'Data tidak lengkap.']);
        exit;
    }

    try {
        $stmt = $pdo->prepare("INSERT INTO tahfizh_reports (student_id, musyrif_id, report_date, type, surah, ayat, quality, notes) VALUES (?, ?, CURDATE(), ?, ?, ?, ?, ?)");
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