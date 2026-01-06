<?php
// Matikan output error HTML agar tidak merusak format JSON
ini_set('display_errors', 0);
error_reporting(E_ALL);

header('Content-Type: application/json');
require_once 'db_connect.php';

if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

try {
    // Ambil input JSON
    $input = json_decode(file_get_contents('php://input'), true);
    $action = $input['action'] ?? '';

    // --- VALIDASI INPUT ---
    if ($action === 'save' || $action === 'submit') {
        // Cek field wajib. Content boleh teks biasa (HTML/Markdown), tidak harus JSON.
        $required_fields = ['subject', 'grade', 'fase', 'type', 'content'];
        foreach ($required_fields as $field) {
            if (!isset($input[$field]) || trim((string)$input[$field]) === '') {
                throw new Exception("Data wajib '$field' tidak boleh kosong.");
            }
        }
    } elseif ($action === 'unlock') {
        if (empty($input['id'])) {
            throw new Exception("ID artefak wajib diisi untuk membuka kunci.");
        }
    } else {
        throw new Exception("Aksi (save/submit/unlock) tidak valid.");
    }

    $pdo = getDBConnection();
    
    // Pastikan tabel ada (Auto-Create jika belum ada)
    $pdo->exec("CREATE TABLE IF NOT EXISTS teaching_artifacts (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        subject VARCHAR(100),
        grade VARCHAR(20),
        fase VARCHAR(5),
        type VARCHAR(20),
        topic TEXT,
        tp TEXT,
        content LONGTEXT,
        status ENUM('draft', 'submitted') NOT NULL DEFAULT 'draft',
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )");

    // Cek kolom status (untuk update dari versi lama)
    try {
        $pdo->query("SELECT status FROM teaching_artifacts LIMIT 1");
    } catch (Exception $e) {
        $pdo->exec("ALTER TABLE teaching_artifacts ADD COLUMN status ENUM('draft', 'submitted') NOT NULL DEFAULT 'draft' AFTER content");
        $pdo->exec("ALTER TABLE teaching_artifacts ADD COLUMN updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER created_at");
    }

    $id = $input['id'] ?? null;
    $user_id = $_SESSION['user_id'];
    $new_id = null;

    if ($action === 'unlock') {
        $stmt = $pdo->prepare("UPDATE teaching_artifacts SET status = 'draft' WHERE id = ? AND user_id = ?");
        $stmt->execute([$id, $user_id]);
        $message = 'Kunci berhasil dibuka. Silakan edit kembali.';
    } elseif ($id) { // Update Data Lama
        $status = ($action === 'submit') ? 'submitted' : 'draft';
        $stmt = $pdo->prepare("UPDATE teaching_artifacts SET content = ?, status = ?, topic = ?, tp = ? WHERE id = ? AND user_id = ?");
        $stmt->execute([$input['content'], $status, $input['topic'] ?? '', $input['tp'] ?? '', $id, $user_id]);
        $message = 'Berhasil diperbarui!';
    } else { // Simpan Data Baru
        $status = ($action === 'submit') ? 'submitted' : 'draft';
        $stmt = $pdo->prepare("INSERT INTO teaching_artifacts (user_id, subject, grade, fase, type, topic, tp, content, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            $user_id,
            $input['subject'],
            $input['grade'],
            $input['fase'],
            $input['type'],
            $input['topic'] ?? '',
            $input['tp'] ?? '',
            $input['content'],
            $status
        ]);
        $new_id = $pdo->lastInsertId();
        $message = 'Berhasil disimpan ke Album!';
    }

    echo json_encode(['success' => true, 'message' => $message, 'new_id' => $new_id]);

} catch (Exception $e) {
    // Tangkap semua error dan kirim sebagai JSON
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error Server: ' . $e->getMessage()]);
}
?>