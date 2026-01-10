<?php
header('Content-Type: application/json');
require_once 'db_connect.php';

if (session_status() === PHP_SESSION_NONE) session_start();

if (!isset($_SESSION['user_id'])) {
    sendJSONResponse(['success' => false, 'message' => 'Unauthorized'], 401);
}

$user_roles = $_SESSION['roles'] ?? [];
$pdo = getDBConnection();

// --- LOGIKA PEMISAHAN TUGAS VERIFIKASI ---
$school_verifiers = ['Kepala Sekolah', 'Admin Sekolah', 'Sekretaris Sekolah', 'Bendahara Sekolah'];
$foundation_verifiers = ['Ketua Yayasan', 'Sekretaris Yayasan', 'Bendahara Yayasan'];
$student_roles = ['Santri', 'Santri Rijal', "Santri Nisa'", 'Walisantri'];

// Cek Peran User
$is_foundation = !empty(array_intersect($foundation_verifiers, $user_roles));
$is_school = !empty(array_intersect($school_verifiers, $user_roles));

$filter_mode = 'none';

if ($is_foundation) {
    // Yayasan: Hanya lihat Pegawai (KECUALI Santri/Wali)
    // Jika user punya kedua peran (Yayasan & Sekolah), prioritas Yayasan (sesuai request "Pisah Total")
    $filter_mode = 'employees_only';
} elseif ($is_school) {
    // Sekolah: Hanya lihat Santri & Walisantri
    $filter_mode = 'students_only';
} else {
    sendJSONResponse(['success' => false, 'message' => 'Akses ditolak. Anda tidak memiliki izin verifikasi.'], 403);
}


// --- GET: List Pending Users ---
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    try {
        // Ambil user yang punya setidaknya satu role 'pending'
        $sql = "SELECT u.user_id, u.username, u.email, 
                       GROUP_CONCAT(ur.role_name SEPARATOR ', ') as roles
                FROM users u
                JOIN user_roles ur ON u.user_id = ur.user_id
                WHERE ur.status = 'pending' ";

        // Terapkan Filter Query
        $placeholders = implode(',', array_fill(0, count($student_roles), '?'));
        if ($filter_mode === 'students_only') {
            $sql .= " AND ur.role_name IN ($placeholders) ";
        } else {
            $sql .= " AND ur.role_name NOT IN ($placeholders) ";
        }

        $sql .= " GROUP BY u.user_id";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($student_roles); // Parameter sama untuk IN maupun NOT IN
        $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        sendJSONResponse(['success' => true, 'pending_users' => $users]);
    } catch (Exception $e) {
        sendJSONResponse(['success' => false, 'message' => $e->getMessage()], 500);
    }
}

// --- POST: Activate / Update Roles ---
elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    $target_user_id = $input['user_id'];
    $action = $input['action']; // 'activate' or 'update_roles'

    try {
        $pdo->beginTransaction();

        if ($action === 'activate') {
            // Ubah semua role pending menjadi approved untuk user ini
            $stmt = $pdo->prepare("UPDATE user_roles SET status = 'approved' WHERE user_id = ? AND status = 'pending'");
            $stmt->execute([$target_user_id]);
            
            // Aktifkan user di tabel users juga (jika ada flag is_active)
            $stmt2 = $pdo->prepare("UPDATE users SET is_active = 1 WHERE user_id = ?");
            $stmt2->execute([$target_user_id]);
            
            $msg = "Akun berhasil diaktifkan.";
        } 
        elseif ($action === 'update_roles') {
            // Hapus role lama (yang pending/approved) dan ganti dengan yang baru (langsung approved)
            // Ini fitur "Edit Peran" sebelum aktivasi
            $new_roles = $input['roles'] ?? [];
            
            // Hapus semua role user ini
            $pdo->prepare("DELETE FROM user_roles WHERE user_id = ?")->execute([$target_user_id]);
            
            // Insert role baru dengan status approved (karena diedit oleh admin)
            $stmt = $pdo->prepare("INSERT INTO user_roles (user_id, role_name, status) VALUES (?, ?, 'approved')"); // Langsung approved? Atau pending?
            // Biasanya kalau admin yang edit, langsung approved saja agar sekalian aktif.
            // Tapi jika tombolnya "Simpan Perubahan" lalu ada tombol "Aktifkan" terpisah, bisa pending.
            // Mari kita buat 'pending' agar alurnya konsisten: Edit -> Save -> Klik Aktifkan.
            
            foreach ($new_roles as $role) {
                $stmt->execute([$target_user_id, $role, 'pending']);
            }
            $msg = "Peran diperbarui. Silakan klik 'Aktifkan Akun' untuk memvalidasi.";
        }

        $pdo->commit();
        sendJSONResponse(['success' => true, 'message' => $msg]);

    } catch (Exception $e) {
        $pdo->rollBack();
        sendJSONResponse(['success' => false, 'message' => $e->getMessage()], 500);
    }
}
?>