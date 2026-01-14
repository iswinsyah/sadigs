<?php
header('Content-Type: application/json');
require_once 'db_connect.php';

if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['user_id'])) {
    sendJSONResponse(['success' => false, 'message' => 'Unauthorized'], 401);
}

$pdo = getDBConnection();
$user_id = $_SESSION['user_id'];
$action = $_GET['action'] ?? '';

try {
    // 1. KIRIM NOTIFIKASI (Admin Only)
    if ($action === 'send') {
        $input = json_decode(file_get_contents('php://input'), true);
        $target_username = trim($input['target_username'] ?? '');
        $title = trim($input['title'] ?? '');
        $message = trim($input['message'] ?? '');

        if (empty($target_username) || empty($title) || empty($message)) {
            sendJSONResponse(['success' => false, 'message' => 'Username, Judul, dan Pesan wajib diisi.'], 400);
        }

        // Cari User ID berdasarkan Username
        $stmtUser = $pdo->prepare("SELECT user_id FROM users WHERE username = ?");
        $stmtUser->execute([$target_username]);
        $target_id = $stmtUser->fetchColumn();

        if (!$target_id) {
            sendJSONResponse(['success' => false, 'message' => "Username '$target_username' tidak ditemukan."], 404);
        }

        $stmt = $pdo->prepare("INSERT INTO notifications (user_id, title, message, created_by) VALUES (?, ?, ?, ?)");
        $stmt->execute([$target_id, $title, $message, $user_id]);

        sendJSONResponse(['success' => true, 'message' => 'Notifikasi berhasil dikirim ke ' . $target_username]);
    }

    // 2. AMBIL NOTIFIKASI SAYA (User)
    elseif ($action === 'get_my') {
        $stmt = $pdo->prepare("SELECT id, title, message, is_read, created_at FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT 50");
        $stmt->execute([$user_id]);
        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        sendJSONResponse(['success' => true, 'data' => $data]);
    }

    // 3. TANDAI SUDAH DIBACA
    elseif ($action === 'mark_read') {
        $input = json_decode(file_get_contents('php://input'), true);
        $notif_id = $input['id'] ?? null;

        if ($notif_id) {
            $stmt = $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ?");
            $stmt->execute([$notif_id, $user_id]);
        } else {
            // Mark all as read
            $stmt = $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ?");
            $stmt->execute([$user_id]);
        }
        sendJSONResponse(['success' => true]);
    }

    // 4. HAPUS NOTIFIKASI
    elseif ($action === 'delete') {
        $input = json_decode(file_get_contents('php://input'), true);
        $notif_id = $input['id'] ?? null;

        if ($notif_id) {
            $stmt = $pdo->prepare("DELETE FROM notifications WHERE id = ? AND user_id = ?");
            $stmt->execute([$notif_id, $user_id]);
            sendJSONResponse(['success' => true, 'message' => 'Pesan dihapus.']);
        } else {
            sendJSONResponse(['success' => false, 'message' => 'ID tidak valid.'], 400);
        }
    }

    else {
        sendJSONResponse(['success' => false, 'message' => 'Action invalid'], 400);
    }

} catch (Exception $e) {
    sendJSONResponse(['success' => false, 'message' => $e->getMessage()], 500);
}
?>