<?php
header('Content-Type: application/json');
require_once 'db_connect.php';

if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['user_id'])) {
    sendJSONResponse(['success' => false, 'message' => 'Unauthorized'], 401);
}

$pdo = getDBConnection();
$user_id = $_SESSION['user_id'];

try {
    // Gunakan tabel 'ibadah_harian' yang sudah berisi data
    $stmt = $pdo->prepare("SELECT * FROM ibadah_harian WHERE user_id = ? ORDER BY report_date DESC LIMIT 30");
    $stmt->execute([$user_id]);
    $raw_data = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Mapping data agar sesuai dengan tampilan frontend
    $formatted_data = [];
    foreach ($raw_data as $row) {
        $formatted_data[] = [
            'id' => $row['id'],
            'report_date' => $row['report_date'],
            'subuh' => $row['shalat_subuh'] ?? '-',
            'zuhur' => $row['shalat_dzuhur'] ?? '-',
            'ashar' => $row['shalat_ashar'] ?? '-',
            'maghrib' => $row['shalat_maghrib'] ?? '-',
            'isya' => $row['shalat_isya'] ?? '-',
            'tahajud' => $row['tahajud'] ?? 0,
            'dhuha' => $row['dhuha'] ?? 0,
            'quran_reading' => $row['quran_reading'] ?? $row['tilawah'] ?? '-', // Handle variasi nama kolom
            'notes' => $row['notes'] ?? '',
            'status' => $row['validation_status'] ?? 'pending'
        ];
    }
    
    sendJSONResponse(['success' => true, 'data' => $formatted_data]);
} catch (Exception $e) {
    sendJSONResponse(['success' => false, 'message' => $e->getMessage()], 500);
}
?>