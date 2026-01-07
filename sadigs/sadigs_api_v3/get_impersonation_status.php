<?php
header('Content-Type: application/json');
if (session_status() === PHP_SESSION_NONE) session_start();

$is_impersonating = isset($_SESSION['impersonator_user_id']);
$admin_name = $_SESSION['impersonator_username'] ?? '';

echo json_encode([
    'success' => true,
    'is_impersonating' => $is_impersonating,
    'admin_name' => $admin_name
]);
?>