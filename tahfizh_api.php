<?php
header('Content-Type: application/json');
require_once 'db_connect.php';

if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['user_id'])) {
    sendJSONResponse(['success' => false, 'message' => 'Unauthorized'], 401);
}

$pdo = getDBConnection();
$user_id = $_SESSION['user_id'];
$action = $_GET['action'] ?? '';

// --- AUTO-FIX: Pastikan tabel dan kolom ada (Self-Healing) ---
try {
    // 1. Cek apakah tabel ada, jika tidak buat
    $pdo->exec("CREATE TABLE IF NOT EXISTS tahfizh_reports (
        id INT AUTO_INCREMENT PRIMARY KEY,
        student_id INT NOT NULL,
        musyrif_id INT NOT NULL,
        report_date DATE NOT NULL,
        type ENUM('Ziyadah', 'Murajaah') NOT NULL,
        surah VARCHAR(100),
        ayat VARCHAR(50),
        quality VARCHAR(50),
        notes TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");

    // 2. Cek apakah kolom 'notes' ada (Fix untuk error 1054)
    $stmtCheck = $pdo->query("SHOW COLUMNS FROM tahfizh_reports LIKE 'notes'");
    if ($stmtCheck->rowCount() == 0) {
        $pdo->exec("ALTER TABLE tahfizh_reports ADD COLUMN notes TEXT");
    }

} catch (Exception $e) {
    // Lanjut saja, mungkin error permission tapi kita coba insert
}
// -----------------------------------------------------------

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    
    try {
        // Validasi input
        if (empty($input['student_id']) || empty($input['surah']) || empty($input['quality'])) {
            throw new Exception("Data tidak lengkap.");
        }

        $stmt = $pdo->prepare("INSERT INTO tahfizh_reports (student_id, musyrif_id, report_date, type, surah, ayat, quality, notes) VALUES (?, ?, CURDATE(), ?, ?, ?, ?, ?)");
        $stmt->execute([
            $input['student_id'],
            $user_id,
            $input['type'],
            $input['surah'],
            $input['ayat'],
            $input['quality'],
            $input['notes'] ?? ''
        ]);
        
        sendJSONResponse(['success' => true, 'message' => 'Laporan Tahfidz berhasil disimpan.']);
    } catch (Exception $e) {
        sendJSONResponse(['success' => false, 'message' => 'Gagal menyimpan: ' . $e->getMessage()], 500);
    }
}
elseif ($action === 'get_my_students') {
    try {
        // Coba ambil dari kelompok mentoring dulu
        $stmt = $pdo->prepare("
            SELECT u.user_id, u.full_name 
            FROM mentoring_groups mg
            JOIN users u ON mg.student_id = u.user_id
            WHERE mg.musyrif_id = ?
        ");
        $stmt->execute([$user_id]);
        $students = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Jika kosong, ambil semua santri (Fallback untuk testing)
        if (empty($students)) {
            $stmt = $pdo->query("
                SELECT u.user_id, u.full_name 
                FROM users u 
                JOIN user_roles ur ON u.user_id = ur.user_id 
                WHERE ur.role_name IN ('Santri', 'Santri Rijal', 'Santri Nisa\'') AND ur.status = 'approved'
            ");
            $students = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
        
        sendJSONResponse(['success' => true, 'data' => $students]);
    } catch (Exception $e) {
        sendJSONResponse(['success' => false, 'message' => $e->getMessage()], 500);
    }
}
?>