<?php
header('Content-Type: application/json');
require_once 'db_connect.php';

if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$user_id = $_SESSION['user_id'];
$notes = $_POST['notes'] ?? '';
$lat = $_POST['latitude'] ?? null;
$long = $_POST['longitude'] ?? null;

$pdo = getDBConnection();

try {
    // Cek apakah sudah absen hari ini
    $stmt = $pdo->prepare("SELECT id, check_out_time FROM employee_attendance WHERE user_id = ? AND attendance_date = CURDATE()");
    $stmt->execute([$user_id]);
    $existing = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$existing) {
        // BELUM ABSEN -> LAKUKAN CHECK IN
        $sql = "INSERT INTO employee_attendance (user_id, attendance_date, check_in_time, status, notes, location_lat, location_long) 
                VALUES (?, CURDATE(), CURTIME(), 'Hadir', ?, ?, ?)";
        $stmtInsert = $pdo->prepare($sql);
        $stmtInsert->execute([$user_id, $notes, $lat, $long]);
        
        echo json_encode(['success' => true, 'message' => 'Berhasil Absen Masuk! Semangat bekerja.']);
    } else {
        // SUDAH ABSEN MASUK -> CEK APAKAH SUDAH PULANG?
        if ($existing['check_out_time']) {
            echo json_encode(['success' => false, 'message' => 'Anda sudah melakukan absen pulang hari ini.']);
        } else {
            // LAKUKAN CHECK OUT
            // Append notes jika ada catatan baru
            $newNotes = $notes ? " | Pulang: " . $notes : "";
            
            $sql = "UPDATE employee_attendance 
                    SET check_out_time = CURTIME(), notes = CONCAT(notes, ?) 
                    WHERE id = ?";
            $stmtUpdate = $pdo->prepare($sql);
            $stmtUpdate->execute([$newNotes, $existing['id']]);
            
            echo json_encode(['success' => true, 'message' => 'Berhasil Absen Pulang! Hati-hati di jalan.']);
        }
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Database Error: ' . $e->getMessage()]);
}
?>