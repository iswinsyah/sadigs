<?php
header('Content-Type: application/json');
require_once 'db_connect.php';

if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$action = $_GET['action'] ?? '';
$user_id = $_SESSION['user_id'];
$user_roles = $_SESSION['roles'] ?? [];

$pdo = getDBConnection();

if ($action === 'list_active') {
    // Definisi 10 Peran Staf untuk kriteria Amanah Umum
    $staff_roles = [
        'Kepala Sekolah', 'Sekretaris Sekolah', 'Bendahara Sekolah', 'Admin Sekolah',
        'Kepala Asrama Putra', 'Kepala Asrama Putri', 'Musyrif', 'Musyrifah', 'Ustadz', 'Ustadzah'
    ];

    try {
        // Ambil semua peraturan yang sudah divalidasi (approved)
        $stmt = $pdo->query("
            SELECT r.*, u.full_name as creator_name, DATE_FORMAT(r.created_at, '%d %b %Y') as formatted_date 
            FROM regulations r 
            LEFT JOIN users u ON r.created_by = u.user_id 
            WHERE r.status = 'approved' 
            ORDER BY r.created_at DESC
        ");
        $all_regulations = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $general = [];
        $specific = [];

        foreach ($all_regulations as $reg) {
            $targets = array_map('trim', explode(',', $reg['target_role']));
            
            // 1. Cek apakah user punya hak melihat ini (salah satu perannya ada di target)
            $has_access = false;
            foreach ($user_roles as $ur) {
                if (in_array($ur, $targets)) {
                    $has_access = true;
                    break;
                }
            }

            if ($has_access) {
                // 2. Cek Logika Amanah Umum: Apakah target mencakup SEMUA 10 peran staf?
                $intersection = array_intersect($staff_roles, $targets);
                $is_general_staff = (count($intersection) == count($staff_roles));

                if ($is_general_staff) {
                    $general[] = $reg;
                } else {
                    $specific[] = $reg;
                }
            }
        }

        echo json_encode(['success' => true, 'general' => $general, 'specific' => $specific]);

    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }

} elseif ($action === 'list_pending') {
    // Untuk halaman Validasi Ketua Yayasan
    try {
        $stmt = $pdo->query("SELECT r.*, u.full_name as creator_name, DATE_FORMAT(r.created_at, '%d %b %Y %H:%i') as created_at FROM regulations r LEFT JOIN users u ON r.created_by = u.user_id WHERE r.status = 'pending' ORDER BY r.created_at DESC");
        echo json_encode(['success' => true, 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}
?>