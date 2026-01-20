<?php
header('Content-Type: application/json');
require_once 'db_connect.php';

if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['user_id'])) {
    sendJSONResponse(['success' => false, 'message' => 'Unauthorized'], 401);
}

$pdo = getDBConnection();
$user_id = $_SESSION['user_id'];
$username = $_SESSION['username'];
$action = $_GET['action'] ?? '';

// Helper: Ambil Role User
$stmtRoles = $pdo->prepare("SELECT role_name FROM user_roles WHERE user_id = ? AND status = 'approved'");
$stmtRoles->execute([$user_id]);
$roles = $stmtRoles->fetchAll(PDO::FETCH_COLUMN);

try {
    if ($action === 'get_history') {
        $conditions = [];
        $params = [];

        // --- LOGIKA FILTER BERDASARKAN ROLE ---
        
        // 1. Jika Walisantri -> Cari data anak-anaknya
        if (in_array('Walisantri', $roles)) {
            // Ambil Nama Lengkap Walisantri untuk pencarian fallback
            $stmtUser = $pdo->prepare("SELECT full_name FROM users WHERE user_id = ?");
            $stmtUser->execute([$user_id]);
            $fullName = $stmtUser->fetchColumn();

            // Cari ID anak-anak yang terhubung (Support Multi-Parent)
            $stmtKids = $pdo->prepare("
                SELECT DISTINCT u.user_id 
                FROM users u
                LEFT JOIN student_details sd ON u.user_id = sd.user_id
                LEFT JOIN student_guardians sg ON u.user_id = sg.student_id
                WHERE sg.walisantri_id = ? OR sd.parent_username = ? OR sd.parent_name = ?
            ");
            $stmtKids->execute([$user_id, $username, $fullName]);
            $kidIds = $stmtKids->fetchAll(PDO::FETCH_COLUMN);

            if (empty($kidIds)) {
                sendJSONResponse(['success' => false, 'message' => 'Belum ada data anak yang terhubung.']);
                exit;
            }
            
            $inQuery = implode(',', array_fill(0, count($kidIds), '?'));
            $conditions[] = "tr.student_id IN ($inQuery)";
            $params = array_merge($params, $kidIds);

        } 
        // 2. Jika Santri -> Lihat data sendiri
        elseif (in_array('Santri', $roles) || in_array('Santri Rijal', $roles) || in_array("Santri Nisa'", $roles)) {
            $conditions[] = "tr.student_id = ?";
            $params[] = $user_id;

        } 
        // 3. Jika Musyrif -> Lihat santri binaannya
        elseif (in_array('Musyrif', $roles) || in_array('Musyrifah', $roles)) {
            $stmtMentees = $pdo->prepare("SELECT student_id FROM mentoring_groups WHERE musyrif_id = ?");
            $stmtMentees->execute([$user_id]);
            $menteeIds = $stmtMentees->fetchAll(PDO::FETCH_COLUMN);

            if (empty($menteeIds)) {
                sendJSONResponse(['success' => true, 'data' => []]); // Belum ada binaan
                exit;
            }

            $inQuery = implode(',', array_fill(0, count($menteeIds), '?'));
            $conditions[] = "tr.student_id IN ($inQuery)";
            $params = array_merge($params, $menteeIds);

        } 
        // 4. Jika Admin/Yayasan -> Lihat Semua
        elseif (array_intersect(['Ketua Yayasan', 'Kepala Sekolah', 'Kepala Asrama Putra', 'Kepala Asrama Putri', 'Admin Sekolah'], $roles)) {
            // Tidak ada filter = ambil semua
        } else {
             // Role lain tidak punya akses
             sendJSONResponse(['success' => true, 'data' => []]);
             exit;
        }

        // --- QUERY DATA ---
        $sql = "
            SELECT 
                tr.id, 
                tr.report_date, 
                tr.type, 
                tr.surah, 
                tr.ayat, 
                tr.quality, 
                tr.notes,
                s.full_name as student_name,
                m.full_name as musyrif_name
            FROM tahfizh_reports tr
            JOIN users s ON tr.student_id = s.user_id
            LEFT JOIN users m ON tr.musyrif_id = m.user_id
        ";

        if (!empty($conditions)) {
            $sql .= " WHERE " . implode(' AND ', $conditions);
        }

        $sql .= " ORDER BY tr.report_date DESC, tr.created_at DESC LIMIT 200";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

        sendJSONResponse(['success' => true, 'data' => $data]);
    }
    elseif ($action === 'get_recap_stats') {
        // Statistik sederhana untuk grafik (Sebaran Kualitas Hafalan)
        $stmt = $pdo->query("SELECT quality as label, COUNT(*) as count FROM tahfizh_reports GROUP BY quality");
        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Format agar sesuai dengan chart di frontend (mapping ke 'juz' agar chart tetap jalan)
        $formatted = [];
        foreach($data as $row) { $formatted[] = ['juz' => $row['label'], 'count' => $row['count']]; }

        sendJSONResponse(['success' => true, 'data' => $formatted]);
    }
} catch (Exception $e) {
    sendJSONResponse(['success' => false, 'message' => $e->getMessage()], 500);
}
?>