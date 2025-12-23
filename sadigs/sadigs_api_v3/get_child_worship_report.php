<?php
header('Content-Type: application/json');
require_once 'db_connect.php';

if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['user_id'])) {
    sendJSONResponse(['success' => false, 'message' => 'Unauthorized'], 401);
}

$walisantri_user_id = $_SESSION['user_id'];
$student_id = $_GET['id'] ?? null;

if (!$student_id) {
    sendJSONResponse(['success' => false, 'message' => 'ID Santri tidak disediakan.'], 400);
}

$pdo = getDBConnection();

try {
    // --- SECURITY CHECK ---
    // 1. Dapatkan username Walisantri yang sedang login
    $stmtWali = $pdo->prepare("SELECT username FROM users WHERE user_id = ?");
    $stmtWali->execute([$walisantri_user_id]);
    $walisantri_username = $stmtWali->fetchColumn();

    // 2. Dapatkan data santri dan pastikan ia adalah anak dari walisantri ini
    $stmtStudent = $pdo->prepare("SELECT parent_username, full_name, username FROM users WHERE user_id = ? AND user_id IN (SELECT user_id FROM user_roles WHERE role_name = 'Santri')");
    $stmtStudent->execute([$student_id]);
    $student = $stmtStudent->fetch(PDO::FETCH_ASSOC);

    if (!$student || $student['parent_username'] !== $walisantri_username) {
        sendJSONResponse(['success' => false, 'message' => 'Akses ditolak. Anda bukan wali dari santri ini.'], 403);
    }
    // --- END SECURITY CHECK ---

    // Ambil data laporan ibadah milik santri tersebut
    $sql = "SELECT 
                ih.*,
                v.full_name as validator_name
            FROM ibadah_harian ih
            LEFT JOIN users v ON ih.validator_id = v.user_id
            WHERE ih.user_id = ?
            ORDER BY ih.report_date DESC
            LIMIT 100";
            
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$student_id]);
    
    sendJSONResponse(['success' => true, 'student_name' => $student['full_name'] ?: $student['username'], 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
} catch (Exception $e) {
    sendJSONResponse(['success' => false, 'message' => $e->getMessage()], 500);
}
?>