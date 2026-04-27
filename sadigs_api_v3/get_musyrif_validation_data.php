<?php
header('Content-Type: application/json');
require_once 'db_connect.php';

if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['user_id'])) {
    sendJSONResponse(['success' => false, 'message' => 'Unauthorized'], 401);
}

$pdo = getDBConnection();
$musyrif_id = $_SESSION['user_id'];

try {
    // Ambil laporan ibadah dari santri yang dimentori oleh user ini
    // Urutkan dari yang terbaru, dan prioritaskan yang 'pending'
    $sql = "SELECT 
                ih.id, 
                ih.report_date, 
                ih.validation_status, 
                ih.validated_at,
                s.full_name as student_name, 
                s.username as student_username,
                -- Ringkasan data ibadah untuk preview --
                ih.shalat_subuh, ih.shalat_dzuhur, ih.shalat_ashar, ih.shalat_maghrib, ih.shalat_isya,
                ih.notes
            FROM ibadah_harian ih
            JOIN mentoring_assignments ma ON ih.user_id = ma.student_id
            JOIN users s ON ih.user_id = s.user_id
            WHERE ma.musyrif_id = ?
            ORDER BY (ih.validation_status = 'pending') DESC, ih.report_date DESC
            LIMIT 100";
            
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$musyrif_id]);
    sendJSONResponse(['success' => true, 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
} catch (Exception $e) {
    sendJSONResponse(['success' => false, 'message' => $e->getMessage()], 500);
}
?>