<?php
header('Content-Type: application/json');
require_once 'db_connect.php';

if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$pdo = getDBConnection();

// --- AUTO MIGRATION: Pastikan tabel dan kolom grade ada ---
$pdo->exec("CREATE TABLE IF NOT EXISTS student_details (
    user_id INT PRIMARY KEY,
    parent_username VARCHAR(50),
    grade VARCHAR(50),
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
)");

// Cek apakah kolom grade ada (jika tabel sudah ada sebelumnya)
try {
    $pdo->query("SELECT grade FROM student_details LIMIT 1");
} catch (Exception $e) {
    $pdo->exec("ALTER TABLE student_details ADD COLUMN grade VARCHAR(50)");
}
// ----------------------------------------------------------

$action = $_GET['action'] ?? '';

try {
    if ($action === 'get_students') {
        // Ambil semua user dengan role Santri
        $sql = "SELECT u.user_id, u.username, u.full_name, u.gender, 
                       COALESCE(sd.grade, 'Belum Diatur') as grade
                FROM users u
                JOIN user_roles ur ON u.user_id = ur.user_id
                LEFT JOIN student_details sd ON u.user_id = sd.user_id
                WHERE ur.role_name IN ('Santri', 'Santri Rijal', 'Santri Nisa\'') 
                  AND ur.status = 'approved'
                ORDER BY 
                    CASE WHEN sd.grade LIKE 'Kelas%' THEN 1 ELSE 2 END, -- Urutkan Kelas dulu
                    sd.grade ASC, 
                    u.full_name ASC";
        
        $stmt = $pdo->query($sql);
        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode(['success' => true, 'data' => $data]);

    } elseif ($action === 'update_bulk') {
        $input = json_decode(file_get_contents('php://input'), true);
        $ids = $input['user_ids'] ?? [];
        $new_grade = $input['new_grade'] ?? '';

        if (empty($ids) || empty($new_grade)) {
            throw new Exception('Pilih minimal satu santri dan status tujuan.');
        }

        $pdo->beginTransaction();
        
        // Gunakan INSERT ... ON DUPLICATE KEY UPDATE agar aman jika data detail belum ada
        $stmt = $pdo->prepare("INSERT INTO student_details (user_id, grade) VALUES (?, ?) ON DUPLICATE KEY UPDATE grade = VALUES(grade)");
        
        foreach ($ids as $id) {
            $stmt->execute([$id, $new_grade]);
        }

        $pdo->commit();
        echo json_encode(['success' => true, 'message' => count($ids) . ' santri berhasil diperbarui menjadi ' . $new_grade]);
    } else {
        throw new Exception('Invalid action');
    }
} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>