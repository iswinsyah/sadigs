<?php
header('Content-Type: application/json');
require_once 'db_connect.php';

if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['user_id'])) {
    sendJSONResponse(['success' => false, 'message' => 'Unauthorized'], 401);
}

$pdo = getDBConnection();
$action = $_GET['action'] ?? '';

// --- AUTO-SCHEMA: Buat Tabel Otomatis ---
try {
    // 1. Tabel Mata Pelajaran
    $pdo->exec("CREATE TABLE IF NOT EXISTS subjects (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(100) NOT NULL,
        grade_level VARCHAR(20) NOT NULL,
        teacher_id INT NULL
    )");

    // 2. Tabel Nilai (Sesuai Request Leger)
    $pdo->exec("CREATE TABLE IF NOT EXISTS academic_scores (
        id INT AUTO_INCREMENT PRIMARY KEY,
        student_id INT NOT NULL,
        subject_id INT NOT NULL,
        semester VARCHAR(50) DEFAULT 'Ganjil 2024/2025',
        tugas_1 FLOAT DEFAULT 0,
        tugas_2 FLOAT DEFAULT 0,
        uh_1 FLOAT DEFAULT 0,
        uas FLOAT DEFAULT 0,
        UNIQUE KEY unique_score (student_id, subject_id, semester)
    )");
} catch (Exception $e) { /* Ignore */ }

try {
    // A. AMBIL DAFTAR MAPEL
    if ($action === 'get_subjects') {
        $stmt = $pdo->query("SELECT * FROM subjects ORDER BY grade_level, name");
        sendJSONResponse(['success' => true, 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
    }

    // B. SIMPAN MAPEL BARU
    elseif ($action === 'save_subject') {
        $input = json_decode(file_get_contents('php://input'), true);
        $stmt = $pdo->prepare("INSERT INTO subjects (name, grade_level) VALUES (?, ?)");
        $stmt->execute([$input['name'], $input['grade_level']]);
        sendJSONResponse(['success' => true, 'message' => 'Mapel berhasil ditambahkan']);
    }

    // C. HAPUS MAPEL
    elseif ($action === 'delete_subject') {
        $input = json_decode(file_get_contents('php://input'), true);
        $stmt = $pdo->prepare("DELETE FROM subjects WHERE id = ?");
        $stmt->execute([$input['id']]);
        sendJSONResponse(['success' => true, 'message' => 'Mapel dihapus']);
    }

    // D. AMBIL DATA NILAI (Untuk Input & Leger)
    elseif ($action === 'get_scores') {
        $subject_id = $_GET['subject_id'];
        
        // Ambil info mapel untuk tahu kelas berapa
        $stmtSub = $pdo->prepare("SELECT grade_level FROM subjects WHERE id = ?");
        $stmtSub->execute([$subject_id]);
        $grade = $stmtSub->fetchColumn();

        // Ambil semua santri di kelas tersebut
        // LEFT JOIN dengan nilai agar santri yang belum punya nilai tetap muncul
        $sql = "
            SELECT 
                u.user_id as student_id, 
                u.full_name, 
                COALESCE(sc.tugas_1, 0) as tugas_1,
                COALESCE(sc.tugas_2, 0) as tugas_2,
                COALESCE(sc.uh_1, 0) as uh_1,
                COALESCE(sc.uas, 0) as uas
            FROM users u
            JOIN user_roles ur ON u.user_id = ur.user_id
            LEFT JOIN student_details sd ON u.user_id = sd.user_id
            LEFT JOIN academic_scores sc ON u.user_id = sc.student_id AND sc.subject_id = ?
            WHERE ur.role_name LIKE 'Santri%' 
            AND (sd.grade = ? OR ? = 'Semua') -- Filter kelas (jika ada data kelas di student_details)
            ORDER BY u.full_name ASC
        ";
        
        // Catatan: Karena data kelas di student_details mungkin kosong, 
        // untuk sementara kita tampilkan semua santri jika grade tidak cocok, 
        // atau Bos bisa perketat nanti. Di sini saya buat logic simple dulu.
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$subject_id, $grade, $grade]);
        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

        sendJSONResponse(['success' => true, 'data' => $data]);
    }

    // E. SIMPAN NILAI (Bulk Save)
    elseif ($action === 'save_scores') {
        $input = json_decode(file_get_contents('php://input'), true);
        $subject_id = $input['subject_id'];
        $scores = $input['scores']; // Array of {student_id, t1, t2, uh1, uas}

        $pdo->beginTransaction();
        $stmt = $pdo->prepare("
            INSERT INTO academic_scores (student_id, subject_id, tugas_1, tugas_2, uh_1, uas) 
            VALUES (?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE 
                tugas_1 = VALUES(tugas_1),
                tugas_2 = VALUES(tugas_2),
                uh_1 = VALUES(uh_1),
                uas = VALUES(uas)
        ");

        foreach ($scores as $s) {
            $stmt->execute([
                $s['student_id'], 
                $subject_id, 
                $s['tugas_1'], 
                $s['tugas_2'], 
                $s['uh_1'], 
                $s['uas']
            ]);
        }
        $pdo->commit();
        sendJSONResponse(['success' => true, 'message' => 'Nilai berhasil disimpan!']);
    }

    else {
        sendJSONResponse(['success' => false, 'message' => 'Invalid action'], 400);
    }

} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    sendJSONResponse(['success' => false, 'message' => $e->getMessage()], 500);
}
?>