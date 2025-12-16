<?php
// HARAP PASTIKAN db_connect.php SUDAH DITEMPATKAN DI FOLDER INI (/api/)
require_once 'db_connect.php';

// Memulai sesi PHP untuk manajemen otorisasi
session_start();
date_default_timezone_set('Asia/Jakarta');

// =================================================================
// Fungsi Pembantu Cek Otorisasi Yayasan
// =================================================================
function checkYayasanRole() {
    // Di lingkungan nyata, kita harus cek SESSION['user_id'] dan ambil roles dari DB
    // Namun, untuk efisiensi, kita cek SESSION['roles'] yang sudah disimpan saat login
    if (!isset($_SESSION['roles'])) {
        return false;
    }
    
    $yayasan_roles = array('Ketua Yayasan', 'Sekretaris Yayasan', 'Bendahara Yayasan');
    
    foreach ($_SESSION['roles'] as $role) {
        if (in_array($role, $yayasan_roles)) {
            return true;
        }
    }
    return false;
}

try {
    $pdo = getDBConnection();
} catch (\PDOException $e) {
    // Koneksi gagal ditangani di db_connect.php
    exit;
}

// =================================================================
// LOGIKA SAVE (POST) - Memerlukan Otorisasi Yayasan
// =================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 1. Cek Otorisasi
    // Saat ini dikomentari agar Anda bisa menguji API, tapi harus diaktifkan
    // if (!checkYayasanRole()) {
    //     sendJSONResponse(array('success' => false, 'message' => 'Akses ditolak. Perlu otorisasi Yayasan.'), 403);
    // }

    $data = json_decode(file_get_contents("php://input"), true);

    if (empty($data)) {
        sendJSONResponse(array('success' => false, 'message' => 'Data kuota kosong.'), 400);
    }
    
    $pdo->beginTransaction();
    $roles_updated = 0;

    try {
        // Update batch ke tabel `quota_settings`
        $sql_update = "UPDATE quota_settings SET max_limit = :max_limit WHERE role_name = :role_name";
        $stmt_update = $pdo->prepare($sql_update);

        foreach ($data as $role_name => $max_limit) {
            $max_limit_int = (int)$max_limit;
            
            // Pastikan max_limit adalah non-negatif
            if ($max_limit_int >= 0) {
                $stmt_update->execute(array('max_limit' => $max_limit_int, 'role_name' => $role_name));
                $roles_updated++;
            }
        }

        $pdo->commit();
        sendJSONResponse(array(
            'success' => true,
            'message' => "Kuota untuk {$roles_updated} peran berhasil diperbarui."
        ));

    } catch (\PDOException $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log("Quota Save Error: " . $e->getMessage());
        sendJSONResponse(array('success' => false, 'message' => 'Terjadi kesalahan sistem saat menyimpan kuota.'), 500);
    }
} 
// =================================================================
// LOGIKA GET - Mengambil Kuota Saat Ini (Termasuk Perhitungan Real-time)
// =================================================================
elseif ($_SERVER['REQUEST_METHOD'] === 'GET') {
    
    $quota_controlled_roles = ['Kepala Sekolah', 'Sekretaris Sekolah', 'Bendahara Sekolah', 'Kepala Asrama', 'Musyrif', 'Ustadz'];
    $uncontrolled_roles = ['Santri', 'Walisantri'];
    
    $all_roles_to_check = array_merge($quota_controlled_roles, $uncontrolled_roles);

    // 1. Ambil pengaturan kuota (max_limit) untuk peran yang dikontrol
    $in_placeholder = implode(',', array_fill(0, count($quota_controlled_roles), '?'));
    $sql_quota = "SELECT role_name, max_limit FROM quota_settings WHERE role_name IN ({$in_placeholder})";
    $stmt_quota = $pdo->prepare($sql_quota);
    $stmt_quota->execute($quota_controlled_roles);
    $quota_settings = $stmt_quota->fetchAll(PDO::FETCH_KEY_PAIR); // ['RoleName' => 'max_limit']

    $quotas_status = [];
    
    // 2. Hitung jumlah pengguna aktif saat ini untuk SEMUA peran yang ada di form signup
    foreach ($all_roles_to_check as $role_name) {
        $max_limit = $quota_settings[$role_name] ?? -1; // -1 jika role tidak diatur (uncontrolled)

        // SQL untuk menghitung user AKTIF yang memiliki peran ini
        // PERBAIKAN KRITIS: Menggunakan u.user_id yang merupakan Primary Key di tabel users
        $sql_count = "SELECT COUNT(ur.user_id) FROM user_roles ur JOIN users u ON ur.user_id = u.user_id WHERE ur.role_name = :role_name AND u.is_active = TRUE";
        $stmt_count = $pdo->prepare($sql_count);
        $stmt_count->execute(['role_name' => $role_name]);
        $current_count = $stmt_count->fetchColumn();

        $quotas_status[] = [
            'role_name' => $role_name,
            'max_limit' => (int)$max_limit,
            'current_count' => (int)$current_count,
            // Jika max_limit diatur (>= 0) dan sudah penuh, maka is_full=true
            'is_full' => ($max_limit >= 0 && $current_count >= $max_limit)
        ];
    }
    
    // Kirim Respons Sukses
    sendJSONResponse(array(
        'success' => true,
        'quotas' => $quotas_status
    ));
}

sendJSONResponse(array('success' => false, 'message' => 'Akses tidak sah.'), 405);