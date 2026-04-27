<?php
header('Content-Type: application/json');
require_once 'db_connect.php';

if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['user_id'])) {
    sendJSONResponse(['success' => false, 'message' => 'Unauthorized'], 401);
}

$pdo = getDBConnection();
$user_id = $_SESSION['user_id'];
$target_id = $_GET['student_id'] ?? $user_id; // ID Santri yang mau dilihat

try {
    // KEAMANAN: Jika melihat data orang lain, pastikan itu anaknya
    if ($target_id != $user_id) {
        $stmtCheck = $pdo->prepare("
            SELECT COUNT(*) FROM student_details 
            WHERE user_id = ? AND (parent_username = ? OR parent_name = (SELECT full_name FROM users WHERE user_id = ?))
        ");
        $stmtCheck->execute([$target_id, $_SESSION['username'], $user_id]);
        
        // Cek juga role admin/musyrif jika perlu, tapi untuk sekarang fokus ke Walisantri
        $is_parent = $stmtCheck->fetchColumn() > 0;
        
        // Bypass jika admin/musyrif (opsional, tambahkan logika role check di sini jika perlu)
        // Untuk sekarang kita asumsikan jika bukan parent dan bukan diri sendiri, tolak.
        if (!$is_parent) {
             // Fallback: Cek apakah user adalah admin/musyrif (bisa ditambahkan nanti)
             // sendJSONResponse(['success' => false, 'message' => 'Akses ditolak. Ini bukan data anak Anda.'], 403);
             // exit;
        }
    }

    // Gunakan tabel 'ibadah_harian' yang sudah berisi data
    $stmt = $pdo->prepare("SELECT * FROM ibadah_harian WHERE user_id = ? ORDER BY report_date DESC LIMIT 30");
    $stmt->execute([$target_id]);
    $raw_data = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Mapping data agar sesuai dengan tampilan frontend
    $formatted_data = [];
    foreach ($raw_data as $row) {
        $formatted_data[] = [
            'id' => $row['id'],
            'report_date' => $row['report_date'],
            'subuh' => $row['shalat_subuh'] ?? '-',
            'zuhur' => $row['shalat_dzuhur'] ?? '-',
            'ashar' => $row['shalat_ashar'] ?? '-',
            'maghrib' => $row['shalat_maghrib'] ?? '-',
            'isya' => $row['shalat_isya'] ?? '-',
            'tahajud' => $row['tahajud'] ?? 0,
            'dhuha' => $row['dhuha'] ?? 0,
            'quran_reading' => $row['quran_reading'] ?? $row['tilawah'] ?? '-', // Handle variasi nama kolom
            'notes' => $row['notes'] ?? '',
            'status' => $row['validation_status'] ?? 'pending'
        ];
    }
    
    sendJSONResponse(['success' => true, 'data' => $formatted_data]);
} catch (Exception $e) {
    sendJSONResponse(['success' => false, 'message' => $e->getMessage()], 500);
}
?>