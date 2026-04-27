<?php
header('Content-Type: application/json');
require_once 'db_connect.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id'])) {
    sendJSONResponse(['success' => false, 'message' => 'Unauthorized'], 401);
}

$pdo = getDBConnection();

// Pastikan tabel assignment ada (Auto-Create)
$pdo->exec("CREATE TABLE IF NOT EXISTS mentoring_assignments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL,
    musyrif_id INT NOT NULL,
    assigned_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_assignment (student_id)
)");

try {
    // Ambil semua santri dan musyrifnya (LEFT JOIN agar santri yang belum dapat musyrif tetap muncul)
    $sql = "SELECT 
                s.user_id AS student_id,
                s.username AS student_username,
                s.full_name AS student_name,
                s.gender AS student_gender,
                ur.role_name AS student_role,
                ma.musyrif_id,
                m.username AS musyrif_username
            FROM users s
            JOIN user_roles ur ON s.user_id = ur.user_id
            LEFT JOIN mentoring_assignments ma ON s.user_id = ma.student_id
            LEFT JOIN users m ON ma.musyrif_id = m.user_id
            WHERE ur.role_name IN ('Santri Rijal', 'Santri Nisa\'') AND ur.status = 'approved'
            GROUP BY s.user_id
            ORDER BY s.full_name ASC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

    sendJSONResponse(['success' => true, 'data' => $data]);
} catch (Exception $e) {
    sendJSONResponse(['success' => false, 'message' => $e->getMessage()], 500);
}
?>