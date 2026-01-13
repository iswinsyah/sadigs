<?php
header('Content-Type: application/json');
require_once 'db_connect.php';

if (session_status() === PHP_SESSION_NONE) session_start();

// Cek Izin (Hanya Yayasan, Kepsek, Kepala Asrama)
$allowed_roles = ['Ketua Yayasan', 'Kepala Sekolah', 'Kepala Asrama Putra', 'Kepala Asrama Putri'];
$user_roles = $_SESSION['roles'] ?? [];
if (empty(array_intersect($allowed_roles, $user_roles))) {
    echo json_encode(['success' => false, 'message' => 'Akses ditolak.']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$student_id = $input['student_id'] ?? null;
$musyrif_id = $input['musyrif_id'] ?? null;

if (!$student_id || !$musyrif_id) {
    echo json_encode(['success' => false, 'message' => 'Data tidak lengkap.']);
    exit;
}

$pdo = getDBConnection();

// --- VALIDASI GENDER (RIJAL vs NISA) ---
$stmtCheck = $pdo->prepare("SELECT s.gender as s_gender, m.gender as m_gender FROM users s, users m WHERE s.user_id = ? AND m.user_id = ?");
$stmtCheck->execute([$student_id, $musyrif_id]);
$check = $stmtCheck->fetch(PDO::FETCH_ASSOC);

if ($check) {
    $s_gender = strtolower($check['s_gender'] ?? '');
    $m_gender = strtolower($check['m_gender'] ?? '');
    
    // Jika data gender tersedia di kedua pihak, pastikan sama
    if ($s_gender && $m_gender && $s_gender !== $m_gender) {
         echo json_encode(['success' => false, 'message' => 'Gagal: Santri dan Musyrif harus sesama jenis (Rijal dengan Musyrif, Nisa dengan Musyrifah).']);
         exit;
    }
}

try {
    // Insert atau Update jika sudah ada (ON DUPLICATE KEY UPDATE)
    $stmt = $pdo->prepare("INSERT INTO mentoring_assignments (student_id, musyrif_id) VALUES (?, ?) ON DUPLICATE KEY UPDATE musyrif_id = VALUES(musyrif_id)");
    $stmt->execute([$student_id, $musyrif_id]);
    echo json_encode(['success' => true, 'message' => 'Berhasil disimpan.']);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>