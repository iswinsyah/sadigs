<?php
header('Content-Type: application/json');
require_once 'db_connect.php';

if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['user_id'])) {
    sendJSONResponse(['success' => false, 'message' => 'Unauthorized'], 401);
}

$pdo = getDBConnection();
$student_id = $_GET['student_id'] ?? null;
$user_id = $_SESSION['user_id'];
$roles = $_SESSION['roles'] ?? [];

// Jika user adalah Walisantri, wajib ada ID Santri dari URL
if (in_array('Walisantri', $roles)) {
    if (!$student_id) {
        sendJSONResponse(['success' => false, 'message' => 'ID Santri tidak valid.'], 400);
    }
} else {
    // Jika user adalah Santri itu sendiri
    if (!$student_id) {
        $student_id = $user_id;
    }
}

try {
    // Ambil nama santri untuk header
    $stmtName = $pdo->prepare("SELECT full_name FROM users WHERE user_id = ?");
    $stmtName->execute([$student_id]);
    $student_name = $stmtName->fetchColumn() ?: 'Santri';

    // Ambil seluruh nilai
    $stmt = $pdo->prepare("SELECT * FROM student_grades WHERE student_id = ? ORDER BY academic_year DESC, semester DESC, subject ASC");
    $stmt->execute([$student_id]);
    $grades = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Kelompokkan berdasarkan Tahun Ajaran & Semester
    $grouped = [];
    foreach ($grades as $g) {
        $period = $g['academic_year'] . ' - Semester ' . $g['semester'];
        if (!isset($grouped[$period])) {
            $grouped[$period] = [];
        }
        $grouped[$period][] = $g;
    }

    sendJSONResponse(['success' => true, 'student_name' => $student_name, 'data' => $grouped]);
} catch (Exception $e) {
    sendJSONResponse(['success' => false, 'message' => 'Database error: ' . $e->getMessage()], 500);
}
?>