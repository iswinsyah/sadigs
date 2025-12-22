<?php
header('Content-Type: application/json');
require_once 'db_connect.php';

if (session_status() === PHP_SESSION_NONE) session_start();
$pdo = getDBConnection();

// --- GET: Ambil Data Kuota ---
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    try {
        // 1. Ambil Setting Kuota Maksimal
        $stmt = $pdo->query("SELECT * FROM quota_settings");
        $settings = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // 2. Hitung Penggunaan Saat Ini (Hanya yang Approved)
        $stmtCount = $pdo->query("SELECT role_name, COUNT(*) as total FROM user_roles WHERE status = 'approved' GROUP BY role_name");
        $counts = $stmtCount->fetchAll(PDO::FETCH_KEY_PAIR); // [role_name => total]

        $quotas = [];
        foreach ($settings as $s) {
            $role = $s['role_name'];
            $max = (int)$s['max_limit'];
            $current = isset($counts[$role]) ? (int)$counts[$role] : 0;
            
            $quotas[$role] = [
                'max_limit' => $max,
                'current_count' => $current,
                'is_full' => ($current >= $max && $max > 0)
            ];
        }

        sendJSONResponse(['success' => true, 'quotas' => $quotas]);
    } catch (Exception $e) {
        sendJSONResponse(['success' => false, 'message' => $e->getMessage()], 500);
    }
}

// --- POST: Simpan Setting Kuota (Khusus Admin) ---
elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Cek Otorisasi Admin/Yayasan
    if (!isset($_SESSION['user_id'])) sendJSONResponse(['success' => false, 'message' => 'Unauthorized'], 401);
    
    // (Opsional: Tambahkan cek role 'Ketua Yayasan' di sini jika perlu lebih ketat)

    $input = json_decode(file_get_contents('php://input'), true);
    
    try {
        $pdo->beginTransaction();
        
        // Reset dan Insert Ulang (Simplifikasi)
        $pdo->exec("DELETE FROM quota_settings");
        $stmt = $pdo->prepare("INSERT INTO quota_settings (role_name, max_limit) VALUES (?, ?)");
        
        foreach ($input as $role => $limit) {
            if ($limit !== '') {
                $stmt->execute([$role, (int)$limit]);
            }
        }
        
        $pdo->commit();
        sendJSONResponse(['success' => true, 'message' => 'Pengaturan kuota disimpan.']);
    } catch (Exception $e) {
        $pdo->rollBack();
        sendJSONResponse(['success' => false, 'message' => $e->getMessage()], 500);
    }
}
?>