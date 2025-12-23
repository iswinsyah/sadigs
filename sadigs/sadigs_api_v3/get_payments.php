<?php
header('Content-Type: application/json');
require_once 'db_connect.php';

if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['user_id'])) {
    sendJSONResponse(['success' => false, 'message' => 'Unauthorized'], 401);
}

$pdo = getDBConnection();
$user_id = $_SESSION['user_id'];

// Cek Role
$stmtRole = $pdo->prepare("SELECT role_name FROM user_roles WHERE user_id = ? AND status='approved'");
$stmtRole->execute([$user_id]);
$roles = $stmtRole->fetchAll(PDO::FETCH_COLUMN);

$isBendahara = in_array('Bendahara Sekolah', $roles) || in_array('Bendahara Yayasan', $roles) || in_array('Ketua Yayasan', $roles);
$isWalisantri = in_array('Walisantri', $roles);

try {
    $sql = "SELECT p.*, u.full_name as student_name, u.username as student_username 
            FROM payments p 
            JOIN users u ON p.student_id = u.user_id ";
    
    $params = [];
    $conditions = [];

    // Filter Status (jika diminta via GET)
    if (isset($_GET['status']) && !empty($_GET['status'])) {
        $conditions[] = "p.status = ?";
        $params[] = $_GET['status'];
    }

    // Filter Hak Akses
    if ($isBendahara) {
        // Bendahara lihat semua (tidak ada filter tambahan user)
    } elseif ($isWalisantri) {
        // Walisantri hanya lihat anak-anaknya
        // Cari ID anak-anak walisantri ini
        $stmtChildren = $pdo->prepare("SELECT u.user_id FROM users u JOIN student_details sd ON u.user_id = sd.user_id WHERE sd.parent_username = (SELECT username FROM users WHERE user_id = ?)");
        $stmtChildren->execute([$user_id]);
        $childIds = $stmtChildren->fetchAll(PDO::FETCH_COLUMN);
        
        if (empty($childIds)) {
            sendJSONResponse(['success' => true, 'data' => []]); // Tidak punya anak
            exit;
        }
        $placeholders = implode(',', array_fill(0, count($childIds), '?'));
        $conditions[] = "p.student_id IN ($placeholders)";
        $params = array_merge($params, $childIds);
    }

    if (!empty($conditions)) $sql .= " WHERE " . implode(' AND ', $conditions);
    $sql .= " ORDER BY p.payment_date DESC, p.created_at DESC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

    sendJSONResponse(['success' => true, 'data' => $data]);

} catch (Exception $e) {
    sendJSONResponse(['success' => false, 'message' => $e->getMessage()], 500);
}
?>