<?php
// Matikan tampilan error PHP agar tidak merusak format JSON
ini_set('display_errors', 0);
error_reporting(E_ALL);

// Mulai buffer output untuk menangkap error tak terduga
ob_start();

header('Content-Type: application/json');

// Fungsi cadangan jika db_connect.php gagal dimuat
if (!function_exists('sendJSONResponse')) {
    function sendJSONResponse($data, $code = 200) {
        http_response_code($code);
        echo json_encode($data);
        exit;
    }
}

try {
    require_once 'db_connect.php';

    if (session_status() === PHP_SESSION_NONE) session_start();

    $pdo = getDBConnection();
    
    // Cek apakah tabel role_quotas ada, jika tidak buat dummy/default
    // Untuk saat ini kita kembalikan kuota default yang longgar agar fitur jalan
    $defaultQuotas = [
        'Musyrif' => ['max_limit' => 20, 'current_count' => 0, 'is_full' => false],
        'Musyrifah' => ['max_limit' => 20, 'current_count' => 0, 'is_full' => false],
        'Ustadz' => ['max_limit' => 50, 'current_count' => 0, 'is_full' => false],
        'Ustadzah' => ['max_limit' => 50, 'current_count' => 0, 'is_full' => false],
        'Kepala Asrama Putra' => ['max_limit' => 1, 'current_count' => 0, 'is_full' => false],
        'Kepala Asrama Putri' => ['max_limit' => 1, 'current_count' => 0, 'is_full' => false],
        'Kepala Sekolah' => ['max_limit' => 1, 'current_count' => 0, 'is_full' => false],
        'Bendahara Sekolah' => ['max_limit' => 2, 'current_count' => 0, 'is_full' => false],
        'Sekretaris Sekolah' => ['max_limit' => 2, 'current_count' => 0, 'is_full' => false],
        'Ketua Yayasan' => ['max_limit' => 1, 'current_count' => 0, 'is_full' => false],
        'Sekretaris Yayasan' => ['max_limit' => 1, 'current_count' => 0, 'is_full' => false],
        'Bendahara Yayasan' => ['max_limit' => 1, 'current_count' => 0, 'is_full' => false],
    ];

    // Di sini Anda bisa menambahkan logika query ke database 'role_quotas' jika sudah ada tabelnya.
    // Contoh: SELECT * FROM role_quotas ...

    ob_clean();
    sendJSONResponse(['success' => true, 'quotas' => $defaultQuotas]);

} catch (Throwable $e) {
    ob_clean();
    sendJSONResponse(['success' => false, 'message' => 'Server Error: ' . $e->getMessage()], 500);
}
?>