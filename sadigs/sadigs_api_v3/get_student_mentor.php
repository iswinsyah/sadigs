<?php
// API: Get a student's mentor name
header('Content-Type: application/json');
require_once 'db_connect.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id'])) {
    sendJSONResponse(['success' => false, 'message' => 'Unauthorized'], 401);
    exit;
}

$student_id = $_GET['student_id'] ?? null;
if (!$student_id) {
    sendJSONResponse(['success' => false, 'message' => 'Student ID is required.'], 400);
    exit;
}

try {
    $pdo = getDBConnection();
    $stmt = $pdo->prepare("SELECT u.full_name FROM users u JOIN mentoring_groups mg ON u.user_id = mg.musyrif_id WHERE mg.student_id = ?");
    $stmt->execute([$student_id]);
    $mentor_name = $stmt->fetchColumn();

    sendJSONResponse(['success' => true, 'mentor_name' => $mentor_name ?: 'Belum Ditentukan']);
} catch (Exception $e) {
    sendJSONResponse(['success' => false, 'message' => 'Database error: ' . $e->getMessage()], 500);
}
?>