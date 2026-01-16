<?php
header('Content-Type: application/json');
require_once 'db_connect.php';

if (session_status() === PHP_SESSION_NONE) session_start();

// 1. Cek Login
if (!isset($_SESSION['user_id'])) {
    sendJSONResponse(['success' => false, 'message' => 'Unauthorized'], 401);
}

// 2. Cek Role & Izin
$roles = $_SESSION['roles'] ?? [];
$is_admin = in_array('Ketua Yayasan', $roles);
$is_walisantri = in_array('Walisantri', $roles);

if (!$is_admin && !$is_walisantri) {
    sendJSONResponse(['success' => false, 'message' => 'Akses Ditolak. Anda tidak memiliki izin.'], 403);
}

$input = json_decode(file_get_contents('php://input'), true);
$target_user_id = $input['user_id'] ?? null;

if (!$target_user_id) {
    sendJSONResponse(['success' => false, 'message' => 'Target user tidak ditemukan.'], 400);
}

try {
    $pdo = getDBConnection();
    
    // JIKA WALISANTRI: Validasi Hubungan Orang Tua - Anak
    if (!$is_admin && $is_walisantri) {
        $walisantri_id = $_SESSION['user_id'];
        
        // Ambil data walisantri
        $stmtWali = $pdo->prepare("SELECT username, full_name FROM users WHERE user_id = ?");
        $stmtWali->execute([$walisantri_id]);
        $wali = $stmtWali->fetch(PDO::FETCH_ASSOC);

        // Cek apakah target adalah anak dari walisantri ini (Logic sama dengan get_my_children)
        $sqlCheck = "SELECT COUNT(*) FROM student_details sd 
                     WHERE sd.user_id = :child_id 
                     AND (
                        sd.parent_username = :username 
                        OR sd.father_name = :fullname 
                        OR sd.mother_name = :fullname 
                        OR sd.parent_name = :fullname
                     )";
        $stmtCheck = $pdo->prepare($sqlCheck);
        $stmtCheck->execute(['child_id' => $target_user_id, 'username' => $wali['username'], 'fullname' => $wali['full_name']]);
        
        if ($stmtCheck->fetchColumn() == 0) {
            sendJSONResponse(['success' => false, 'message' => 'Akses Ditolak. Akun ini tidak terdaftar sebagai anak Anda.'], 403);
        }
    }

    // Ambil data target user
    $stmt = $pdo->prepare("SELECT user_id, username, full_name FROM users WHERE user_id = ?");
    $stmt->execute([$target_user_id]);
    $targetUser = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$targetUser) {
        sendJSONResponse(['success' => false, 'message' => 'User tidak ditemukan.'], 404);
    }

    // Ambil role target user
    $stmtRoles = $pdo->prepare("SELECT role_name FROM user_roles WHERE user_id = ? AND status = 'approved'");
    $stmtRoles->execute([$target_user_id]);
    $targetRoles = $stmtRoles->fetchAll(PDO::FETCH_COLUMN);

    // SIMPAN SESI ASLI (ADMIN) jika belum tersimpan (agar tidak tertimpa jika impersonate berantai)
    if (!isset($_SESSION['impersonator_user_id'])) {
        $_SESSION['impersonator_user_id'] = $_SESSION['user_id'];
        $_SESSION['impersonator_username'] = $_SESSION['username'];
        $_SESSION['impersonator_roles'] = $_SESSION['roles'];
    }

    // SET SESI BARU (TARGET)
    $_SESSION['user_id'] = $targetUser['user_id'];
    $_SESSION['username'] = $targetUser['username'];
    $_SESSION['full_name'] = $targetUser['full_name'];
    $_SESSION['roles'] = $targetRoles;

    sendJSONResponse(['success' => true, 'message' => 'Berhasil login sebagai ' . $targetUser['username']]);

} catch (Exception $e) {
    sendJSONResponse(['success' => false, 'message' => 'Error: ' . $e->getMessage()], 500);
}
?>