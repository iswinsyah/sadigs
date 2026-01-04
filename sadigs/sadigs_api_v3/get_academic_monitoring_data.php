<?php
header('Content-Type: application/json');
require_once 'db_connect.php';

if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['user_id'])) {
    sendJSONResponse(['success' => false, 'message' => 'Unauthorized'], 401);
}

// Cek apakah user adalah Kepala Sekolah (atau peran admin lain)
$allowed_roles = ['Kepala Sekolah', 'Ketua Yayasan'];
$user_roles = $_SESSION['roles'] ?? [];
if (count(array_intersect($allowed_roles, $user_roles)) == 0) {
    sendJSONResponse(['success' => false, 'message' => 'Access Denied'], 403);
}

$pdo = getDBConnection();

try {
    // 1. Ambil semua user yang memiliki peran Ustadz atau Ustadzah
    $sql_teachers = "SELECT u.user_id, u.username, ud.full_name 
                     FROM users u
                     JOIN user_roles ur ON u.user_id = ur.user_id
                     LEFT JOIN user_details ud ON u.user_id = ud.user_id
                     WHERE ur.role_name IN ('Ustadz', 'Ustadzah') AND ur.status = 'approved'
                     GROUP BY u.user_id";
    $stmt_teachers = $pdo->query($sql_teachers);
    $teachers = $stmt_teachers->fetchAll(PDO::FETCH_ASSOC);

    if (empty($teachers)) {
        sendJSONResponse(['success' => true, 'data' => []]);
        exit;
    }

    // 2. Ambil jumlah Buku Kerja (Promes) yang sudah 'submitted'
    $sql_promes = "SELECT user_id, COUNT(*) as promes_count FROM saved_promes WHERE status = 'submitted' GROUP BY user_id";
    $stmt_promes = $pdo->query($sql_promes);
    $promes_counts = $stmt_promes->fetchAll(PDO::FETCH_KEY_PAIR);

    // 3. Ambil jumlah Modul Ajar (RPP) yang sudah dibuat
    $sql_rpp = "SELECT user_id, COUNT(*) as rpp_count FROM rpp_album GROUP BY user_id";
    $stmt_rpp = $pdo->query($sql_rpp);
    $rpp_counts = $stmt_rpp->fetchAll(PDO::FETCH_KEY_PAIR);

    // 4. Gabungkan data
    foreach ($teachers as &$teacher) {
        $teacher['promes_count'] = $promes_counts[$teacher['user_id']] ?? 0;
        $teacher['rpp_count'] = $rpp_counts[$teacher['user_id']] ?? 0;
    }

    sendJSONResponse(['success' => true, 'data' => $teachers]);

} catch (Exception $e) {
    sendJSONResponse(['success' => false, 'message' => $e->getMessage()], 500);
}
?>