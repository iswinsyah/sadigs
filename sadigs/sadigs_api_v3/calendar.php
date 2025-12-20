<?php
// =================================================================
// SADIGS 3.0: CALENDAR API
// =================================================================
ob_start();
require_once 'db_connect.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$pdo = getDBConnection();

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    // PUBLIC ACCESS: Mengambil data kalender
    try {
        $stmt = $pdo->query("SELECT event_key, start_date, end_date FROM academic_calendar");
        $events = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $data = [];
        foreach ($events as $event) {
            $data[$event['event_key']] = [
                'start' => $event['start_date'],
                'end' => $event['end_date']
            ];
        }
        sendJSONResponse(['success' => true, 'events' => $data]);
    } catch (Exception $e) {
        error_log("Calendar API Error: " . $e->getMessage());
        sendJSONResponse(['success' => false, 'message' => 'Gagal memuat data kalender.'], 500);
    }

} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // PROTECTED ACCESS: Hanya Yayasan yang boleh mengubah
    $allowed_roles = ['Ketua Yayasan', 'Sekretaris Yayasan', 'Bendahara Yayasan'];
    $user_roles = $_SESSION['roles'] ?? [];
    if (empty(array_intersect($allowed_roles, $user_roles))) {
        sendJSONResponse(['success' => false, 'message' => 'Akses ditolak. Hanya Yayasan yang berhak.'], 403);
    }

    $data = json_decode(file_get_contents("php://input"), true);
    
    try {
        $pdo->beginTransaction();
        
        $sql = "INSERT INTO academic_calendar (event_key, start_date, end_date) VALUES (:key, :start, :end)
                ON DUPLICATE KEY UPDATE start_date = :start_update, end_date = :end_update";
        $stmt = $pdo->prepare($sql);

        foreach ($data as $key => $dates) {
            $start = !empty($dates['start']) ? $dates['start'] : null;
            $end = !empty($dates['end']) ? $dates['end'] : null;
            
            $stmt->execute([
                'key' => $key,
                'start' => $start,
                'end' => $end,
                'start_update' => $start,
                'end_update' => $end
            ]);
        }
        
        $pdo->commit();
        sendJSONResponse(['success' => true, 'message' => 'Kalender pendidikan berhasil disimpan.']);
    } catch (Exception $e) {
        $pdo->rollBack();
        sendJSONResponse(['success' => false, 'message' => 'Gagal menyimpan: ' . $e->getMessage()], 500);
    }
} else {
    sendJSONResponse(['success' => false, 'message' => 'Metode tidak diizinkan.'], 405);
}
?>