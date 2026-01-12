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
        $staff = [];
        $specific = [];
        
        // Daftar peran yang TIDAK dianggap sebagai Staf (untuk logika akses otomatis)
        // Jika user punya peran SELAIN ini, maka dia dianggap Staf.
        $non_staff_roles = ['Ketua Yayasan', 'Sekretaris Yayasan', 'Bendahara Yayasan', 'Walisantri', 'Santri', 'Santri Rijal', "Santri Nisa'"];

        foreach ($all_regulations as $reg) {
            $targets = array_map('trim', explode(',', $reg['target_role']));
            $is_umum = in_array('Umum', $targets);
            $is_staf = in_array('Staf', $targets);
            
            // 1. Cek Hak Akses
            $has_access = false;
            
            if ($is_umum) {
                $has_access = true; // Semua user bisa lihat Umum
            } elseif (in_array('Ketua Yayasan', $user_roles) || in_array('Sekretaris Yayasan', $user_roles)) {
                $has_access = true; // Admin lihat semua (Monitoring)
            } elseif ($is_staf) {
                // Cek apakah user memiliki setidaknya satu peran yang BUKAN (Yayasan/Wali/Santri)
                // Contoh: Kepala Sekolah, Guru, Musyrif akan lolos cek ini.
                $is_user_staff = false;
                foreach ($user_roles as $ur) {
                    if (!in_array($ur, $non_staff_roles)) {
                        $is_user_staff = true;
                        break;
                    }
                }
                if ($is_user_staff) $has_access = true;
            } else {
                // Cek apakah salah satu role user ada di target
                foreach ($user_roles as $ur) {
                    if (in_array($ur, $targets)) {
                        $has_access = true;
                        break;
                    }
                }
            }

            if ($has_access) {
                // 2. Klasifikasi Tab (Umum vs Khusus)
                if ($is_umum) {
                    $general[] = $reg;
                } elseif ($is_staf) {
                    $staff[] = $reg;
                } else {
                    $specific[] = $reg;
                }
            }
        }

        echo json_encode(['success' => true, 'general' => $general, 'staff' => $staff, 'specific' => $specific]);

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