<?php
header('Content-Type: application/json');
require_once 'db_connect.php';

if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['user_id'])) {
    sendJSONResponse(['success' => false, 'message' => 'Unauthorized'], 401);
}

// Cek Role yang boleh input nilai
$allowed_roles = ['Musyrif', 'Musyrifah', 'Ustadz', 'Ustadzah', 'Kepala Sekolah', 'Kepala Asrama Putra', 'Kepala Asrama Putri'];
$user_roles = $_SESSION['roles'] ?? [];
if (empty(array_intersect($allowed_roles, $user_roles))) {
    sendJSONResponse(['success' => false, 'message' => 'Akses ditolak. Anda tidak memiliki izin menginput nilai.'], 403);
}

$input = json_decode(file_get_contents('php://input'), true);

if (empty($input['student_id']) || empty($input['subject']) || !isset($input['score'])) {
    sendJSONResponse(['success' => false, 'message' => 'Data tidak lengkap. ID Santri, Mapel, dan Nilai wajib diisi.'], 400);
}

$score = (float)$input['score'];

// Hitung Grade Otomatis
$grade = 'E';
if ($score >= 90) $grade = 'A';
elseif ($score >= 85) $grade = 'A-';
elseif ($score >= 80) $grade = 'B+';
elseif ($score >= 75) $grade = 'B';
elseif ($score >= 70) $grade = 'B-';
elseif ($score >= 60) $grade = 'C';
elseif ($score >= 50) $grade = 'D';

try {
    $pdo = getDBConnection();
    $stmt = $pdo->prepare("INSERT INTO student_grades (student_id, academic_year, semester, subject, score, grade, notes) VALUES (?, ?, ?, ?, ?, ?, ?)");
    $stmt->execute([
        $input['student_id'],
        $input['academic_year'] ?? '2024/2025',
        $input['semester'] ?? 'Ganjil',
        $input['subject'],
        $score,
        $grade,
        $input['notes'] ?? ''
    ]);
    
    sendJSONResponse(['success' => true, 'message' => 'Nilai berhasil disimpan.']);
} catch (Exception $e) {
    sendJSONResponse(['success' => false, 'message' => 'Database error: ' . $e->getMessage()], 500);
}
?>