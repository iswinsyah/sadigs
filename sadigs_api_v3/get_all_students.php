<?php
header('Content-Type: application/json');
require_once 'db_connect.php';

if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['user_id'])) {
    sendJSONResponse(['success' => false, 'message' => 'Unauthorized'], 401);
}

// Cek Izin (Hanya Admin/Kepsek/Yayasan/Bendahara)
$allowed = ['Ketua Yayasan', 'Kepala Sekolah', 'Admin Sekolah', 'Bendahara Sekolah', 'Sekretaris Sekolah'];
$user_roles = $_SESSION['roles'] ?? [];
if (empty(array_intersect($allowed, $user_roles))) {
    sendJSONResponse(['success' => false, 'message' => 'Akses ditolak.'], 403);
}

$pdo = getDBConnection();

try {
    // Pastikan tabel student_details ada (Auto-Create jika belum ada)
    $pdo->exec("CREATE TABLE IF NOT EXISTS student_details (
        user_id INT PRIMARY KEY,
        nisn VARCHAR(50),
        nik VARCHAR(50),
        birth_place VARCHAR(100),
        birth_date DATE,
        address TEXT,
        student_phone VARCHAR(20),
        entry_date DATE,
        previous_school VARCHAR(100),
        previous_school_address TEXT,
        child_order INT,
        siblings_count INT,
        step_siblings_count INT,
        medical_history TEXT,
        father_name VARCHAR(100),
        father_phone VARCHAR(20),
        father_job VARCHAR(100),
        father_address TEXT,
        mother_name VARCHAR(100),
        mother_phone VARCHAR(20),
        mother_job VARCHAR(100),
        mother_address TEXT,
        responsible_party VARCHAR(50),
        parent_name VARCHAR(100),
        parent_username VARCHAR(50),
        parent_phone VARCHAR(20),
        guardian_job VARCHAR(100),
        guardian_address TEXT,
        student_photo_path VARCHAR(255),
        kk_photo_path VARCHAR(255),
        birth_cert_photo_path VARCHAR(255),
        ijazah_photo_path VARCHAR(255),
        grade VARCHAR(20),
        status VARCHAR(20) DEFAULT 'Aktif',
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )");

    // Query Data Gabungan (Users + Details)
    // Menggunakan LEFT JOIN agar santri yang belum isi biodata tetap muncul namanya
    $sql = "SELECT 
                u.user_id, u.username, u.full_name, u.gender,
                sd.*
            FROM users u
            JOIN user_roles ur ON u.user_id = ur.user_id
            LEFT JOIN student_details sd ON u.user_id = sd.user_id
            WHERE ur.role_name IN ('Santri', 'Santri Rijal', 'Santri Nisa\'')
            GROUP BY u.user_id
            ORDER BY u.full_name ASC";

    $stmt = $pdo->query($sql);
    $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

    sendJSONResponse(['success' => true, 'data' => $data]);

} catch (Exception $e) {
    sendJSONResponse(['success' => false, 'message' => $e->getMessage()], 500);
}
?>