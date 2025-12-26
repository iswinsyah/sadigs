<?php
header('Content-Type: application/json');
require_once 'db_connect.php';

if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['user_id'])) {
    sendJSONResponse(['success' => false, 'message' => 'Unauthorized'], 401);
}

$data = json_decode(file_get_contents("php://input"), true);
$id = $data['id'];
$action = $data['action']; // 'approve_unit', 'reject_unit', 'approve_foundation', 'reject_foundation', 'update'
$details = $data['details'] ?? null; // Jika ada edit rincian

$pdo = getDBConnection();

try {
    if ($action === 'update') {
        // Hitung ulang total
        $new_total = 0;
        foreach ($details as $item) $new_total += (float)$item['amount'];
        
        $stmt = $pdo->prepare("UPDATE operational_budgets SET details = ?, total_amount = ? WHERE id = ?");
        $stmt->execute([json_encode($details), $new_total, $id]);
        $msg = "Rencana anggaran berhasil diperbarui.";
    } 
    elseif ($action === 'approve_unit') {
        $stmt = $pdo->prepare("UPDATE operational_budgets SET status_unit = 'approved' WHERE id = ?");
        $stmt->execute([$id]);
        $msg = "Disetujui oleh Pimpinan Unit.";
    }
    elseif ($action === 'reject_unit') {
        $stmt = $pdo->prepare("UPDATE operational_budgets SET status_unit = 'rejected' WHERE id = ?");
        $stmt->execute([$id]);
        $msg = "Ditolak oleh Pimpinan Unit.";
    }
    elseif ($action === 'approve_foundation') {
        // Jika disetujui yayasan, status final jadi established (Anggaran Belanja)
        $stmt = $pdo->prepare("UPDATE operational_budgets SET status_foundation = 'approved', final_status = 'established' WHERE id = ?");
        $stmt->execute([$id]);
        $msg = "Disetujui Yayasan. Anggaran ditetapkan.";
    }
    elseif ($action === 'reject_foundation') {
        $stmt = $pdo->prepare("UPDATE operational_budgets SET status_foundation = 'rejected' WHERE id = ?");
        $stmt->execute([$id]);
        $msg = "Ditolak Yayasan.";
    }

    sendJSONResponse(['success' => true, 'message' => $msg]);

} catch (Exception $e) {
    sendJSONResponse(['success' => false, 'message' => $e->getMessage()], 500);
}
?>