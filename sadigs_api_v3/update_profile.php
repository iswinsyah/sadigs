<?php
header('Content-Type: application/json');
require_once 'db_connect.php';

if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['user_id'])) {
    sendJSONResponse(['success' => false, 'message' => 'Unauthorized'], 401);
}

$user_id = $_SESSION['user_id'];
$username = $_SESSION['username'];

// --- AUTO-SCHEMA: Pastikan tabel ada SEBELUM query apapun (GET/POST) ---
$pdo = getDBConnection();
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS student_details (user_id INT PRIMARY KEY)");
    // Coba tambahkan kolom parent_username jika belum ada
    try { $pdo->exec("ALTER TABLE student_details ADD COLUMN parent_username VARCHAR(100) NULL"); } catch(Exception $e){}
    
    // --- TABEL BARU: student_guardians (Untuk Multi-Parent) ---
    $pdo->exec("CREATE TABLE IF NOT EXISTS student_guardians (
        id INT AUTO_INCREMENT PRIMARY KEY, 
        student_id INT NOT NULL, 
        walisantri_id INT NOT NULL, 
        UNIQUE KEY unique_relation (student_id, walisantri_id)
    )");
} catch (Exception $e) { /* Abaikan error jika kolom sudah ada */ }

// --- GET HANDLER: Ambil Data Profil ---
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    // Ambil data user
    $stmt = $pdo->prepare("SELECT username, full_name, email, gender, bio FROM users WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Ambil roles
    $stmtRoles = $pdo->prepare("SELECT role_name, status FROM user_roles WHERE user_id = ?");
    $stmtRoles->execute([$user_id]);
    $user['roles'] = $stmtRoles->fetchAll(PDO::FETCH_ASSOC);
    
    // --- AMBIL LINKED CHILDREN (Jika Walisantri) ---
    $user['linked_children'] = [];
    $isWalisantri = false;
    foreach($user['roles'] as $r) {
        if($r['role_name'] === 'Walisantri') $isWalisantri = true;
    }

    if ($isWalisantri) {
        $stmtKids = $pdo->prepare("
            SELECT DISTINCT u.username 
            FROM users u
            LEFT JOIN student_details sd ON u.user_id = sd.user_id
            LEFT JOIN student_guardians sg ON u.user_id = sg.student_id
            WHERE sg.walisantri_id = ? OR sd.parent_username = ?
        ");
        $stmtKids->execute([$user_id, $username]);
        $user['linked_children'] = $stmtKids->fetchAll(PDO::FETCH_COLUMN);
    }
    
    sendJSONResponse(['success' => true, 'data' => $user]);
}

// --- POST HANDLER: Simpan Data ---
$input = json_decode(file_get_contents('php://input'), true);
if (is_null($input)) {
    sendJSONResponse(['success' => false, 'message' => 'Invalid JSON input.'], 400);
    exit;
}

try {
    $pdo->beginTransaction();

    $linking_results = []; // Penampung pesan hasil linking

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
            foreach ($family['child_names'] as $childIdentifier) {
                $childIdentifier = trim($childIdentifier);
                if (empty($childIdentifier)) continue;
                
                // Cari Santri berdasarkan Username ATAU Nama Lengkap
                // Kita cari user yang punya role mengandung kata 'Santri'
                $stmtFind = $pdo->prepare("
                    SELECT u.user_id, u.full_name, u.username FROM users u 
                    JOIN user_roles ur ON u.user_id = ur.user_id 
                    WHERE (u.username = ? OR TRIM(LOWER(u.full_name)) = TRIM(LOWER(?)))
                    AND ur.role_name LIKE 'Santri%'
                    LIMIT 1
                ");
                $stmtFind->execute([$childIdentifier, $childIdentifier]);
                $student = $stmtFind->fetch(PDO::FETCH_ASSOC);
                
                if ($student) {
                    // Link-kan menggunakan tabel student_guardians (Multi-Parent Support)
                    // INSERT IGNORE agar jika sudah terhubung tidak error
                    $stmtLink = $pdo->prepare("INSERT IGNORE INTO student_guardians (student_id, walisantri_id) VALUES (?, ?)");
                    $stmtLink->execute([$student['user_id'], $user_id]);
                    $linking_results[] = "✅ Berhasil menghubungkan santri: {$student['full_name']} ({$student['username']}).";
                } else {
                    $linking_results[] = "❌ GAGAL: Santri '$childIdentifier' tidak ditemukan. Cek Username/Nama.";
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
                    $linking_results[] = "✅ Berhasil terhubung ke Walisantri: $parentName.";
                } else {
                    $linking_results[] = "❌ GAGAL: Walisantri '$parentName' tidak ditemukan.";
                }
            }
        }
    }

    $pdo->commit();
    
    $msg = 'Profil berhasil diperbarui.';
    if (!empty($linking_results)) {
        $msg .= "\n" . implode("\n", $linking_results);
    }
    
    sendJSONResponse(['success' => true, 'message' => $msg]);

} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    sendJSONResponse(['success' => false, 'message' => 'Gagal menyimpan: ' . $e->getMessage()], 500);
}
?>