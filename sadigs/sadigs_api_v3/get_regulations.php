<?php
header('Content-Type: application/json');
ini_set('display_errors', 0);
error_reporting(E_ALL);

require_once 'db_connect.php';

try {
    $action = $_GET['action'] ?? '';
    
    if ($action === 'list_active') {
        $pdo = getDBConnection();
        
        // Cek apakah tabel ada (untuk mencegah Error 500 jika belum migrasi)
        $check = $pdo->query("SHOW TABLES LIKE 'regulations'");
        if ($check->rowCount() == 0) {
            echo json_encode(['success' => true, 'data' => []]); // Return kosong aman
            exit;
        }

        // Ambil peraturan aktif
        $stmt = $pdo->query("SELECT * FROM regulations WHERE is_active = 1 ORDER BY created_at DESC LIMIT 5");
        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode(['success' => true, 'data' => $data]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>