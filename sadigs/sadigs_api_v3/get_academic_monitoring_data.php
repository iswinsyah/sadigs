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

    // 2. Ambil jumlah artefak (Modul, LKPD, Soal) untuk semua user dalam satu query
    $artifact_counts = [];
    try {
        $sql_artifacts = "SELECT 
                            user_id, 
                            SUM(CASE WHEN type = 'modul' THEN 1 ELSE 0 END) as modul_count,
                            SUM(CASE WHEN type = 'lkpd' THEN 1 ELSE 0 END) as lkpd_count,
                            SUM(CASE WHEN type = 'soal' THEN 1 ELSE 0 END) as soal_count
                          FROM teaching_artifacts
                          GROUP BY user_id";
        $stmt_artifacts = $pdo->query($sql_artifacts);
        while ($row = $stmt_artifacts->fetch(PDO::FETCH_ASSOC)) {
            $artifact_counts[$row['user_id']] = $row;
        }
    } catch (Exception $e) {
        // Jaring pengaman jika tabel 'teaching_artifacts' belum ada. Biarkan $artifact_counts kosong.
    }

    // 3. Gabungkan data
    foreach ($teachers as &$teacher) {
        $user_id = $teacher['user_id'];
        $teacher['modul_count'] = $artifact_counts[$user_id]['modul_count'] ?? 0;
        $teacher['lkpd_count'] = $artifact_counts[$user_id]['lkpd_count'] ?? 0;
        $teacher['soal_count'] = $artifact_counts[$user_id]['soal_count'] ?? 0;
    }

    sendJSONResponse(['success' => true, 'data' => $teachers]);

} catch (Exception $e) {
    // Tangani error utama (misal: gagal konek DB atau query 'users' gagal)
    sendJSONResponse(['success' => false, 'message' => 'Error Server: ' . $e->getMessage()], 500);
}
?>