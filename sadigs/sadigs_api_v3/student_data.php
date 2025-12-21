<?php
ob_start();
header('Content-Type: application/json');
require_once 'db_connect.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id'])) {
    sendJSONResponse(['success' => false, 'message' => 'Unauthorized'], 401);
}

$pdo = getDBConnection();
$user_id = $_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    try {
        $stmt = $pdo->prepare("SELECT * FROM student_details WHERE user_id = ?");
        $stmt->execute([$user_id]);
        $data = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Jika belum ada data, kirim object kosong agar frontend tidak error
        if (!$data) $data = [];
        
        sendJSONResponse(['success' => true, 'data' => $data]);
    } catch (Exception $e) {
        sendJSONResponse(['success' => false, 'message' => $e->getMessage()], 500);
    }
} 
elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    
    try {
        // Gunakan INSERT ... ON DUPLICATE KEY UPDATE
        $sql = "INSERT INTO student_details 
                (user_id, nisn, birth_place, birth_date, address, parent_name, parent_phone, previous_school) 
                VALUES (:uid, :nisn, :bplace, :bdate, :addr, :pname, :pphone, :pschool)
                ON DUPLICATE KEY UPDATE 
                nisn = VALUES(nisn),
                birth_place = VALUES(birth_place),
                birth_date = VALUES(birth_date),
                address = VALUES(address),
                parent_name = VALUES(parent_name),
                parent_phone = VALUES(parent_phone),
                previous_school = VALUES(previous_school)";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            'uid' => $user_id,
            'nisn' => $input['nisn'] ?? '',
            'bplace' => $input['birth_place'] ?? '',
            'bdate' => !empty($input['birth_date']) ? $input['birth_date'] : null,
            'addr' => $input['address'] ?? '',
            'pname' => $input['parent_name'] ?? '',
            'pphone' => $input['parent_phone'] ?? '',
            'pschool' => $input['previous_school'] ?? ''
        ]);

        sendJSONResponse(['success' => true, 'message' => 'Biodata berhasil disimpan.']);
    } catch (Exception $e) {
        sendJSONResponse(['success' => false, 'message' => 'Gagal menyimpan: ' . $e->getMessage()], 500);
    }
}
?>