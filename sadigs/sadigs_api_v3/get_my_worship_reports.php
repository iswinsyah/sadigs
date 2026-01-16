<?php
header('Content-Type: application/json');
require_once 'db_connect.php';

if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['user_id'])) {
    sendJSONResponse(['success' => false, 'message' => 'Unauthorized'], 401);
}

$pdo = getDBConnection();
$user_id = $_SESSION['user_id'];

// Auto-create table jika belum ada (Standarisasi Schema)
$pdo->exec("CREATE TABLE IF NOT EXISTS daily_worship (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    report_date DATE NOT NULL,
    subuh VARCHAR(20),
    zuhur VARCHAR(20),
    ashar VARCHAR(20),
    maghrib VARCHAR(20),
    isya VARCHAR(20),
    tahajud TINYINT(1) DEFAULT 0,
    dhuha TINYINT(1) DEFAULT 0,
    quran_reading VARCHAR(100),
    notes TEXT,
    status VARCHAR(20) DEFAULT 'pending', -- pending, approved, rejected
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_report (user_id, report_date)
)");

try {
    // Ambil 30 hari terakhir
    $stmt = $pdo->prepare("SELECT * FROM daily_worship WHERE user_id = ? ORDER BY report_date DESC LIMIT 30");
    $stmt->execute([$user_id]);
    $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    sendJSONResponse(['success' => true, 'data' => $data]);
} catch (Exception $e) {
    sendJSONResponse(['success' => false, 'message' => $e->getMessage()], 500);
}
?>