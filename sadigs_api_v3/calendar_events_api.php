<?php
header('Content-Type: application/json');
require_once 'db_connect.php';

try {
    $pdo = getDBConnection();
    $method = $_SERVER['REQUEST_METHOD'];

    if ($method === 'GET') {
        $stmt = $pdo->query("SELECT event_key, start_date, end_date FROM academic_calendar");
        // Menggunakan FETCH_KEY_PAIR untuk membuat array asosiatif [event_key => [data]]
        $events = $stmt->fetchAll(PDO::FETCH_UNIQUE | PDO::FETCH_ASSOC);
        sendJSONResponse(['success' => true, 'data' => $events]);

    } elseif ($method === 'POST') {
        if (session_status() === PHP_SESSION_NONE) session_start();
        if (!isset($_SESSION['user_id'])) {
            sendJSONResponse(['success' => false, 'message' => 'Unauthorized'], 401);
            exit;
        }

        $input = json_decode(file_get_contents('php://input'), true);
        $events_to_save = $input['events'] ?? [];

        if (empty($events_to_save)) {
            throw new Exception("No data to save.");
        }

        $sql = "INSERT INTO academic_calendar (event_key, start_date, end_date) VALUES (:key, :start, :end)
                ON DUPLICATE KEY UPDATE start_date = VALUES(start_date), end_date = VALUES(end_date)";
        $stmt = $pdo->prepare($sql);

        $pdo->beginTransaction();
        foreach ($events_to_save as $event) {
            $stmt->execute([
                'key' => $event['key'],
                'start' => !empty($event['start']) ? $event['start'] : null,
                'end' => !empty($event['end']) ? $event['end'] : null
            ]);
        }
        $pdo->commit();

        sendJSONResponse(['success' => true, 'message' => 'Kalender berhasil disimpan.']);
    }

} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    sendJSONResponse(['success' => false, 'message' => $e->getMessage()], 500);
}
?>