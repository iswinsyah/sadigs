<?php
header('Content-Type: application/json');
require_once 'db_connect.php';

if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['user_id'])) {
    sendJSONResponse(['success' => false, 'message' => 'Unauthorized'], 401);
}

$pdo = getDBConnection();
$user_id = $_SESSION['user_id'];

// --- HANDLE GET (AMBIL DATA UNTUK FORM) ---
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $target_id = $_GET['id'] ?? $user_id;
    
    // TODO: Tambahkan validasi akses jika perlu (misal: santri hanya boleh lihat data sendiri)
    
    try {
        $stmt = $pdo->prepare("
            SELECT u.username, u.full_name, u.gender, sd.* 
            FROM users u 
            LEFT JOIN student_details sd ON u.user_id = sd.user_id 
            WHERE u.user_id = ?
        ");
        $stmt->execute([$target_id]);
        $data = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($data) {
            sendJSONResponse(['success' => true, 'data' => $data]);
        } else {
            sendJSONResponse(['success' => false, 'message' => 'Data tidak ditemukan']);
        }
    } catch (Exception $e) {
        sendJSONResponse(['success' => false, 'message' => $e->getMessage()], 500);
    }
    exit;
}

// --- HANDLE POST (SIMPAN DATA) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $target_id = $_POST['target_user_id'] ?? $user_id;
    
    // Fungsi Helper Upload
    function uploadFile($fileInputName, $targetDir = 'uploads/documents/') {
        if (!isset($_FILES[$fileInputName]) || $_FILES[$fileInputName]['error'] !== UPLOAD_ERR_OK) {
            return null;
        }
        
        if (!is_dir($targetDir)) mkdir($targetDir, 0755, true);
        
        $ext = pathinfo($_FILES[$fileInputName]['name'], PATHINFO_EXTENSION);
        $filename = uniqid() . '_' . $fileInputName . '.' . $ext;
        $targetPath = $targetDir . $filename;
        
        if (move_uploaded_file($_FILES[$fileInputName]['tmp_name'], $targetPath)) {
            return $targetPath;
        }
        return null;
    }

    try {
        // Cek apakah data sudah ada di student_details
        $check = $pdo->prepare("SELECT user_id FROM student_details WHERE user_id = ?");
        $check->execute([$target_id]);
        $exists = $check->fetchColumn();

        // Daftar kolom yang akan disimpan
        $fields = [
            'nisn', 'nik', 'birth_place', 'birth_date', 'address', 'student_phone',
            'entry_date', 'previous_school', 'previous_school_address',
            'child_order', 'siblings_count', 'step_siblings_count', 'medical_history',
            'father_name', 'father_phone', 'father_job', 'father_address',
            'mother_name', 'mother_phone', 'mother_job', 'mother_address',
            'responsible_party', 'parent_name', 'parent_username', 'parent_phone',
            'guardian_job', 'guardian_address'
        ];

        $params = [];
        $updateFields = [];
        $insertFields = ['user_id'];
        $insertPlaceholders = ['?'];
        $insertValues = [$target_id];

        foreach ($fields as $field) {
            $val = $_POST[$field] ?? null;
            $params[] = $val;
            $updateFields[] = "$field = ?";
            
            $insertFields[] = $field;
            $insertPlaceholders[] = '?';
            $insertValues[] = $val;
        }

        // Handle File Uploads
        $fileFields = ['student_photo', 'kk_photo', 'birth_cert_photo', 'ijazah_photo'];
        foreach ($fileFields as $fileField) {
            $path = uploadFile($fileField);
            if ($path) {
                $dbCol = $fileField . '_path';
                $params[] = $path;
                $updateFields[] = "$dbCol = ?";
                
                $insertFields[] = $dbCol;
                $insertPlaceholders[] = '?';
                $insertValues[] = $path;
            }
        }

        if ($exists) {
            $params[] = $target_id; // Untuk WHERE clause
            $sql = "UPDATE student_details SET " . implode(', ', $updateFields) . " WHERE user_id = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
        } else {
            $sql = "INSERT INTO student_details (" . implode(', ', $insertFields) . ") VALUES (" . implode(', ', $insertPlaceholders) . ")";
            $stmt = $pdo->prepare($sql);
            $stmt->execute($insertValues);
        }
        
        // Update data dasar di tabel users (Nama & Gender)
        if (isset($_POST['full_name']) || isset($_POST['gender'])) {
            $uSql = "UPDATE users SET ";
            $uParams = [];
            if (isset($_POST['full_name'])) { $uSql .= "full_name = ?, "; $uParams[] = $_POST['full_name']; }
            if (isset($_POST['gender'])) { $uSql .= "gender = ?, "; $uParams[] = $_POST['gender']; }
            $uSql = rtrim($uSql, ", ") . " WHERE user_id = ?";
            $uParams[] = $target_id;
            $pdo->prepare($uSql)->execute($uParams);
        }

        sendJSONResponse(['success' => true, 'message' => 'Biodata berhasil disimpan.']);

    } catch (Exception $e) {
        sendJSONResponse(['success' => false, 'message' => $e->getMessage()], 500);
    }
}
?>