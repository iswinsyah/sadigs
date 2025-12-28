<?php
header('Content-Type: application/json');
require_once 'db_connect.php';

if (session_status() === PHP_SESSION_NONE) session_start();

try {
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

    sendJSONResponse(['success' => true, 'quotas' => $defaultQuotas]);

} catch (Exception $e) {
    // Jika error, tetap kirim success false agar UI tidak macet
    sendJSONResponse(['success' => false, 'message' => $e->getMessage()]);
}
?>