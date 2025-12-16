<?php
// =================================================================
// SADIGS 3.0: LOGIKA PENDAFTARAN AKUN
// File ini HARUS dimulai dengan '<?php' tanpa spasi sebelumnya.
// =================================================================

require_once 'db_connect.php';

// Pastikan metode request adalah POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendJSONResponse(array('success' => false, 'message' => 'Permintaan tidak sah.'), 400);
}

$data = json_decode(file_get_contents("php://input"), true);

$username = $data['username'] ?? '';
$email = $data['email'] ?? '';
$password = $data['password'] ?? '';
$roles = $data['roles'] ?? []; // Array of selected roles

if (empty($username) || empty($email) || empty($password) || empty($roles)) {
    sendJSONResponse(array('success' => false, 'message' => 'Semua bidang wajib diisi.'), 400);
}

if (strlen($password) < 8) {
    sendJSONResponse(array('success' => false, 'message' => 'Kata sandi minimal 8 karakter.'), 400);
}

try {
    $pdo = getDBConnection();
    
    // 1. Cek Ketersediaan Username dan Email
    $sql_check = "SELECT COUNT(*) FROM users WHERE username = :username OR email = :email";
    $stmt_check = $pdo->prepare($sql_check);
    $stmt_check->execute(['username' => $username, 'email' => $email]);
    if ($stmt_check->fetchColumn() > 0) {
        sendJSONResponse(array('success' => false, 'message' => 'Username atau Email sudah terdaftar.'), 409);
    }
    
    // 2. Cek Kuota (Hanya untuk peran yang diatur kuotanya)
    $quota_controlled_roles = ['Kepala Sekolah', 'Sekretaris Sekolah', 'Bendahara Sekolah', 'Kepala Asrama', 'Musyrif', 'Ustadz'];
    $check_roles = array_intersect($roles, $quota_controlled_roles);
    
    if (!empty($check_roles)) {
        $in_placeholder = implode(',', array_fill(0, count($check_roles), '?'));
        // NOTE: current_count diabaikan karena akan dihitung secara real-time
        $sql_quota = "SELECT role_name, max_limit FROM quota_settings WHERE role_name IN ({$in_placeholder})";
        $stmt_quota = $pdo->prepare($sql_quota);
        $stmt_quota->execute($check_roles);
        $quotas = $stmt_quota->fetchAll(PDO::FETCH_KEY_PAIR); // ['RoleName' => 'max_limit']

        // Optimasi: Hitung semua peran yang relevan dalam satu query menggunakan GROUP BY
        $sql_count = "
            SELECT ur.role_name, COUNT(ur.user_id) as current_count
            FROM user_roles ur 
            JOIN users u ON ur.user_id = u.user_id 
            WHERE ur.role_name IN ({$in_placeholder}) AND u.is_active = TRUE
            GROUP BY ur.role_name
        ";
        $stmt_count = $pdo->prepare($sql_count);
        $stmt_count->execute($check_roles);
        $current_counts = $stmt_count->fetchAll(PDO::FETCH_KEY_PAIR); // ['RoleName' => 'current_count']

        foreach ($check_roles as $role_to_check) {
            $current_count = $current_counts[$role_to_check] ?? 0;
            if (isset($quotas[$role_to_check]) && $current_count >= $quotas[$role_to_check]) {
                sendJSONResponse(array('success' => false, 'message' => "Pendaftaran gagal: Kuota untuk peran '{$role_to_check}' sudah penuh."), 403);
            }
        }
    }

    // 3. Proses Pendaftaran (Transaksi Database)
    $pdo->beginTransaction();
    
    $password_hash = password_hash($password, PASSWORD_BCRYPT); // Hash password
    
    // Default akun baru non-aktif (is_active = 0), harus diaktifkan oleh Admin
    $sql_insert_user = "INSERT INTO users (username, email, password_hash, is_active) VALUES (:username, :email, :password_hash, 0)";
    $stmt_user = $pdo->prepare($sql_insert_user);
    $stmt_user->execute(['username' => $username, 'email' => $email, 'password_hash' => $password_hash]);
    $user_id = $pdo->lastInsertId();

    $sql_insert_role = "INSERT INTO user_roles (user_id, role_name) VALUES (:user_id, :role_name)";
    $stmt_role = $pdo->prepare($sql_insert_role);

    foreach ($roles as $role) {
        $stmt_role->execute(['user_id' => $user_id, 'role_name' => $role]);
    }
    
    $pdo->commit();

    sendJSONResponse(array(
        'success' => true,
        'message' => 'Pendaftaran berhasil. Akun Anda harus menunggu persetujuan Admin/Yayasan.'
    ));

} catch (\PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log("Signup DB Error: " . $e->getMessage());
    sendJSONResponse(array('success' => false, 'message' => 'Terjadi kesalahan sistem saat pendaftaran.'), 500);
}