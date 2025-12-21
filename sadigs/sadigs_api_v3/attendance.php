<?php
// =================================================================
// SADIGS 3.0: ATTENDANCE API
// =================================================================
ob_start();
require_once 'db_connect.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$pdo = getDBConnection();

// 1. Cek Login & Sesi
if (!isset($_SESSION['user_id'])) {
    sendJSONResponse(['success' => false, 'message' => 'Sesi berakhir. Silakan login ulang.'], 401);
}

// 2. Cek Otorisasi Peran (Hanya Pegawai)
$allowed_roles = ['Kepala Sekolah', 'Kepala Asrama', 'Sekretaris Sekolah', 'Bendahara Sekolah', 'Musyrif'];
$user_roles = $_SESSION['roles'] ?? [];
if (empty(array_intersect($allowed_roles, $user_roles))) {
    sendJSONResponse(['success' => false, 'message' => 'Akses ditolak. Anda bukan pegawai terdaftar.'], 403);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $json = file_get_contents("php://input");
    $data = json_decode($json, true) ?? []; // Pastikan data selalu array, hindari crash pada PHP versi lama
    
    $password = $data['password'] ?? '';
    $type = $data['type'] ?? ''; // 'Masuk' atau 'Pulang'
    $category = $data['category'] ?? 'Absensi Harian';
    $lat = $data['latitude'] ?? null;
    $lng = $data['longitude'] ?? null;
    $addr = $data['address'] ?? '';

    // Validasi Input
    if (empty($password) || empty($type)) {
        sendJSONResponse(['success' => false, 'message' => 'Password dan tipe absensi wajib diisi.'], 400);
    }

    try {
        // 3. Verifikasi Password (Keamanan Ganda)
        $stmt = $pdo->prepare("SELECT password_hash FROM users WHERE user_id = :id");
        $stmt->execute(['id' => $_SESSION['user_id']]);
        $user = $stmt->fetch();

        if (!$user || !password_verify($password, $user['password_hash'])) {
            sendJSONResponse(['success' => false, 'message' => 'Password salah. Absensi gagal.'], 401);
        }

        // 4. Cek Duplikasi (Cegah absen masuk 2x sehari untuk kategori yang sama)
        if ($type === 'Masuk') {
            $today = date('Y-m-d');
            $stmtCheck = $pdo->prepare("SELECT id FROM attendance_logs WHERE user_id = :uid AND attendance_type = 'Masuk' AND category = :category AND DATE(timestamp) = :today");
            $stmtCheck->execute(['uid' => $_SESSION['user_id'], 'category' => $category, 'today' => $today]);
            if ($stmtCheck->rowCount() > 0) {
                sendJSONResponse(['success' => false, 'message' => "Anda sudah melakukan absen masuk untuk $category hari ini."], 400);
            }
        }

        // 5. Simpan Absensi
        // Kita gunakan NOW() dari database untuk waktu yang tidak bisa dimanipulasi user
        $sql = "INSERT INTO attendance_logs (user_id, attendance_type, category, timestamp, latitude, longitude, address) 
                VALUES (:uid, :type, :category, NOW(), :lat, :lng, :addr)";
        $stmtInsert = $pdo->prepare($sql);
        $stmtInsert->execute([
            'uid' => $_SESSION['user_id'],
            'type' => $type,
            'category' => $category,
            'lat' => $lat,
            'lng' => $lng,
            'addr' => $addr
        ]);

        // Ambil waktu server barusan untuk dikirim balik ke frontend
        $serverTime = date('H:i:s d-m-Y');

        sendJSONResponse([
            'success' => true, 
            'message' => "Absensi $type berhasil tercatat pada $serverTime",
            'server_time' => $serverTime
        ]);

    } catch (Exception $e) {
        sendJSONResponse(['success' => false, 'message' => 'Terjadi kesalahan sistem: ' . $e->getMessage()], 500);
    }
}
?>