<?php
header('Content-Type: application/json');
require_once 'db_connect.php';

if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['user_id'])) {
    sendJSONResponse(['success' => false, 'message' => 'Unauthorized'], 401);
}

$user_id = $_SESSION['user_id'];
$username = $_SESSION['username'];

// --- GET HANDLER: Ambil Data Profil ---
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $pdo = getDBConnection();
    // Ambil data user
    $stmt = $pdo->prepare("SELECT username, full_name, email, gender, bio FROM users WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Ambil roles
    $stmtRoles = $pdo->prepare("SELECT role_name, status FROM user_roles WHERE user_id = ?");
    $stmtRoles->execute([$user_id]);
    $user['roles'] = $stmtRoles->fetchAll(PDO::FETCH_ASSOC);
    
    sendJSONResponse(['success' => true, 'data' => $user]);
}

// --- POST HANDLER: Simpan Data ---
$input = json_decode(file_get_contents('php://input'), true);
if (is_null($input)) {
    sendJSONResponse(['success' => false, 'message' => 'Invalid JSON input.'], 400);
    exit;
}

$pdo = getDBConnection();

try {
    $pdo->beginTransaction();

    // 1. Update Data Pribadi di tabel 'users'
    $updates = [];
    $params = ['user_id' => $user_id];

    if (isset($input['full_name'])) { $updates[] = "full_name = :full_name"; $params['full_name'] = $input['full_name']; }
    if (isset($input['gender'])) { $updates[] = "gender = :gender"; $params['gender'] = $input['gender']; }
    if (isset($input['bio'])) { $updates[] = "bio = :bio"; $params['bio'] = $input['bio']; }
    if (!empty($input['password'])) {
        if (strlen($input['password']) < 8) throw new Exception("Password baru harus minimal 8 karakter.");
        $updates[] = "password_hash = :password";
        $params['password'] = password_hash($input['password'], PASSWORD_DEFAULT);
    }

    if (!empty($updates)) {
        $sql_user = "UPDATE users SET " . implode(', ', $updates) . " WHERE user_id = :user_id";
        $stmt_user = $pdo->prepare($sql_user);
        $stmt_user->execute($params);
    }

    // 2. Update Peran di tabel 'user_roles'
    if (isset($input['roles']) && is_array($input['roles'])) {
        $requested_roles = $input['roles'];
        
        // Gunakan logika yang sama dengan select_role.php untuk keamanan.
        // Hanya menambahkan peran baru sebagai 'pending' dan tidak mengubah yang sudah ada.
        $stmt_roles = $pdo->prepare("INSERT INTO user_roles (user_id, role_name, status) VALUES (?, ?, 'pending') ON DUPLICATE KEY UPDATE status = status");
        foreach ($requested_roles as $role) {
            $stmt_roles->execute([$user_id, $role]);
        }
    }

    // 3. LOGIKA AUTO-LINKING KELUARGA (Fitur Baru)
    if (isset($input['family_linking'])) {
        $family = $input['family_linking'];
        
        // A. Jika User adalah WALISANTRI -> Input Nama Anak
        if (!empty($family['child_names']) && is_array($family['child_names'])) {
            foreach ($family['child_names'] as $childName) {
                $childName = trim($childName);
                if (empty($childName)) continue;
                
                // Cari Santri berdasarkan Nama Lengkap
                // Kita cari user yang punya role mengandung kata 'Santri'
                $stmtFind = $pdo->prepare("
                    SELECT u.user_id FROM users u 
                    JOIN user_roles ur ON u.user_id = ur.user_id 
                    WHERE TRIM(LOWER(u.full_name)) = TRIM(LOWER(?)) 
                    AND ur.role_name LIKE 'Santri%'
                    LIMIT 1
                ");
                $stmtFind->execute([$childName]);
                $student = $stmtFind->fetch(PDO::FETCH_ASSOC);
                
                if ($student) {
                    // Link-kan Santri tersebut ke Walisantri ini (update parent_username)
                    $stmtLink = $pdo->prepare("INSERT INTO student_details (user_id, parent_username) VALUES (?, ?) ON DUPLICATE KEY UPDATE parent_username = VALUES(parent_username)");
                    $stmtLink->execute([$student['user_id'], $username]);
                }
            }
        }
        
        // B. Jika User adalah SANTRI -> Input Nama Orang Tua
        if (!empty($family['parent_names']) && is_array($family['parent_names'])) {
            foreach ($family['parent_names'] as $parentName) {
                $parentName = trim($parentName);
                if (empty($parentName)) continue;
                
                // Cari Walisantri berdasarkan Nama Lengkap
                $stmtFindP = $pdo->prepare("
                    SELECT u.username FROM users u 
                    JOIN user_roles ur ON u.user_id = ur.user_id 
                    WHERE TRIM(LOWER(u.full_name)) = TRIM(LOWER(?)) 
                    AND ur.role_name = 'Walisantri'
                    LIMIT 1
                ");
                $stmtFindP->execute([$parentName]);
                $parent = $stmtFindP->fetch(PDO::FETCH_ASSOC);
                
                if ($parent) {
                    // Link-kan Diri Sendiri (Santri) ke Walisantri tersebut
                    $stmtLink = $pdo->prepare("INSERT INTO student_details (user_id, parent_username) VALUES (?, ?) ON DUPLICATE KEY UPDATE parent_username = VALUES(parent_username)");
                    $stmtLink->execute([$user_id, $parent['username']]);
                }
            }
        }
    }

    $pdo->commit();
    sendJSONResponse(['success' => true, 'message' => 'Profil berhasil diperbarui. Pengajuan peran baru (jika ada) telah dikirim untuk validasi. Halaman akan dimuat ulang.']);

} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    sendJSONResponse(['success' => false, 'message' => 'Gagal menyimpan: ' . $e->getMessage()], 500);
}
?>