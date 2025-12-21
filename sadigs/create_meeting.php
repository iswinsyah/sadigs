<?php
// =================================================================
// SADIGS 3.0: CREATE MEETING API
// =================================================================
ob_start();
require_once 'db_connect.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$pdo = getDBConnection();

// 1. Cek Login
if (!isset($_SESSION['user_id'])) {
    sendJSONResponse(['success' => false, 'message' => 'Sesi berakhir.'], 401);
}

// 2. Cek Otorisasi (Ketua Yayasan, Kepala Sekolah, Kepala Asrama)
$allowed_roles = ['Ketua Yayasan', 'Kepala Sekolah', 'Kepala Asrama'];
$user_roles = $_SESSION['roles'] ?? [];
if (empty(array_intersect($allowed_roles, $user_roles))) {
    sendJSONResponse(['success' => false, 'message' => 'Akses ditolak. Anda tidak memiliki izin membuat undangan rapat.'], 403);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $json = file_get_contents("php://input");
    $data = json_decode($json, true) ?? [];

    // Validasi Input
    if (empty($data['meeting_name']) || empty($data['meeting_time']) || empty($data['location']) || empty($data['inviter']) || empty($data['routine'])) {
        sendJSONResponse(['success' => false, 'message' => 'Kolom utama wajib diisi.'], 400);
    }

    // Validasi Kondisional berdasarkan Rutinitas
    $routine = $data['routine'];
    if ($routine === 'sekali' && (empty($data['meeting_date']) || empty($data['day']))) {
        sendJSONResponse(['success' => false, 'message' => 'Tanggal dan Hari wajib diisi untuk rapat sekali.'], 400);
    }
    if ($routine === 'setiap_pekan' && empty($data['day'])) {
        sendJSONResponse(['success' => false, 'message' => 'Hari wajib diisi untuk rapat pekanan.'], 400);
    }
    if ($routine === 'setiap_bulan' && empty($data['meeting_date'])) {
        sendJSONResponse(['success' => false, 'message' => 'Tanggal wajib diisi untuk rapat bulanan.'], 400);
    }

    try {
        $sql = "INSERT INTO meetings (meeting_name, meeting_date, meeting_time, location, agenda, inviter, routine, day) 
                VALUES (:name, :date, :time, :location, :agenda, :inviter, :routine, :day)";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            'name' => $data['meeting_name'],
            'date' => !empty($data['meeting_date']) ? $data['meeting_date'] : null,
            'time' => $data['meeting_time'],
            'location' => $data['location'],
            'agenda' => $data['agenda'] ?? '',
            'inviter' => $data['inviter'],
            'routine' => $data['routine'],
            'day' => !empty($data['day']) ? $data['day'] : null
        ]);

        sendJSONResponse(['success' => true, 'message' => 'Undangan rapat berhasil dibuat.']);
    } catch (Exception $e) {
        sendJSONResponse(['success' => false, 'message' => 'Gagal membuat rapat: ' . $e->getMessage()], 500);
    }
}
?>