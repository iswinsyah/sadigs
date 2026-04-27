<?php
// API: Get Role Quotas
header('Content-Type: application/json');
require_once 'db_connect.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Izinkan akses publik (untuk halaman login/signup jika perlu) atau batasi ke user login
// Untuk dashboard, user harus login.
if (!isset($_SESSION['user_id'])) {
    // Boleh return unauthorized, atau return data kosong jika dipanggil public
    // sendJSONResponse(['success' => false, 'message' => 'Unauthorized'], 401);
    // exit;
}

try {
    $pdo = getDBConnection();

    // 1. Pastikan tabel quota_settings ada (Auto Migration) - Gunakan nama tabel yang konsisten
    $pdo->exec("CREATE TABLE IF NOT EXISTS quota_settings (
        role_name VARCHAR(50) PRIMARY KEY,
        max_limit INT NOT NULL DEFAULT 0
    )");

    // 2. Ambil Batas Kuota
    $stmt = $pdo->query("SELECT role_name, max_limit FROM quota_settings");
    $limits = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

    // 3. Hitung Pengguna Saat Ini per Peran (Hanya yang statusnya approved)
    // Asumsi tabel user_roles ada. Jika belum ada, query ini akan gagal, tapi kita tangkap errornya.
    $counts = [];
    try {
        $stmt = $pdo->query("SELECT role_name, COUNT(*) as cnt FROM user_roles WHERE status = 'approved' GROUP BY role_name");
        $counts = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
    } catch (Exception $e) {
        // Tabel user_roles mungkin belum ada atau kosong
    }

    // 4. Daftar Peran yang Dikelola
    $allRoles = [
        'Ketua Yayasan', 'Sekretaris Yayasan', 'Bendahara Yayasan',
        'Kepala Sekolah', 'Admin Sekolah', 'Sekretaris Sekolah', 'Bendahara Sekolah',
        'Kepala Ma\'had', 'Kepala Asrama Putra', 'Kepala Asrama Putri',
        'Musyrif', 'Musyrifah', 'Ustadz', 'Ustadzah'
    ];

    $quotas = [];
    foreach ($allRoles as $role) {
        $max = isset($limits[$role]) ? (int)$limits[$role] : 0;
        $current = isset($counts[$role]) ? (int)$counts[$role] : 0;
        
        $quotas[$role] = [
            'max_limit' => $max,
            'current_count' => $current,
            'is_full' => ($max > 0 && $current >= $max)
        ];
    }

    sendJSONResponse(['success' => true, 'quotas' => $quotas]);

} catch (Exception $e) {
    sendJSONResponse(['success' => false, 'message' => 'Database error: ' . $e->getMessage()], 500);
}
?>