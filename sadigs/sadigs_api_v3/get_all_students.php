<?php
header('Content-Type: application/json');
require_once 'db_connect.php';

if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['user_id'])) {
    sendJSONResponse(['success' => false, 'message' => 'Unauthorized'], 401);
}

$pdo = getDBConnection();

try {
    // Ambil data user yang memiliki role Santri
    $sql = "SELECT u.user_id, u.username, u.full_name, u.gender,
                   sd.*
            FROM users u
            JOIN user_roles ur ON u.user_id = ur.user_id
            LEFT JOIN student_details sd ON u.user_id = sd.user_id
            WHERE ur.role_name = 'Santri'
            AND ur.status = 'approved'
            GROUP BY u.user_id
            ORDER BY u.full_name ASC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

    sendJSONResponse(['success' => true, 'data' => $data]);

} catch (Exception $e) {
    sendJSONResponse(['success' => false, 'message' => $e->getMessage()], 500);
}
?>