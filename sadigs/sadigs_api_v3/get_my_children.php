<?php
header('Content-Type: application/json');
require_once 'db_connect.php';

if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['user_id'], $_SESSION['username'])) {
    sendJSONResponse(['success' => false, 'message' => 'Unauthorized'], 401);
}

$pdo = getDBConnection();
$walisantri_username = $_SESSION['username'];
$walisantri_id = $_SESSION['user_id'];

try {
    // 1. Ambil Nama Lengkap Walisantri dari profilnya
    $stmtUser = $pdo->prepare("SELECT full_name FROM users WHERE user_id = ?");
    $stmtUser->execute([$walisantri_id]);
    $walisantri_fullname = $stmtUser->fetchColumn();

    // 2. Cari santri berdasarkan username OR nama orang tua di Buku Induk
    $sql = "
        SELECT u.user_id, u.full_name, u.username 
        FROM student_details sd
        JOIN users u ON sd.user_id = u.user_id
        WHERE sd.parent_username = :username
    ";
    
    $params = ['username' => $walisantri_username];

    // Jika walisantri punya nama lengkap, cari juga berdasarkan nama ayah/ibu/wali
    if ($walisantri_fullname) {
        $sql .= " OR sd.father_name = :fullname OR sd.mother_name = :fullname OR sd.parent_name = :fullname";
        $params['fullname'] = $walisantri_fullname;
    }

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $children = $stmt->fetchAll(PDO::FETCH_ASSOC);

    sendJSONResponse(['success' => true, 'children' => $children]);

} catch (Exception $e) {
    sendJSONResponse(['success' => false, 'message' => 'Database error: ' . $e->getMessage()], 500);
}
?>