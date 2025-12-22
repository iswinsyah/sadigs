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
        // Ambil data gabungan dari users dan student_details
        $stmt = $pdo->prepare("SELECT u.username, u.full_name, u.gender, sd.* FROM users u LEFT JOIN student_details sd ON u.user_id = sd.user_id WHERE u.user_id = ?");
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
    // Input sekarang dari $_POST (untuk data teks) dan $_FILES (untuk file)
    $input = $_POST;
    
    try {
        // --- LOGIKA UPLOAD FILE ---
        define('UPLOAD_DIR', __DIR__ . '/uploads/student_docs/');
        if (!is_dir(UPLOAD_DIR)) mkdir(UPLOAD_DIR, 0777, true);

        function handle_upload($file_key, $user_id, $doc_type, $pdo) {
            if (isset($_FILES[$file_key]) && $_FILES[$file_key]['error'] === UPLOAD_ERR_OK) {
                // Validasi Ukuran File (Maksimal 2MB = 2 * 1024 * 1024 bytes)
                if ($_FILES[$file_key]['size'] > 2 * 1024 * 1024) {
                    throw new Exception("Ukuran file '{$_FILES[$file_key]['name']}' terlalu besar. Maksimal 2MB.");
                }

                // Hapus file lama jika ada
                $stmt_old = $pdo->prepare("SELECT {$doc_type}_path FROM student_details WHERE user_id = ?");
                $stmt_old->execute([$user_id]);
                $old_path = $stmt_old->fetchColumn();
                if ($old_path && file_exists(__DIR__ . '/' . $old_path)) {
                    unlink(__DIR__ . '/' . $old_path);
                }

                $file = $_FILES[$file_key];
                $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
                $filename = "{$user_id}_{$doc_type}_" . time() . "." . $ext;
                $destination = UPLOAD_DIR . $filename;
                
                if (move_uploaded_file($file['tmp_name'], $destination)) {
                    // Return path relatif dari folder sadigs_api_v3
                    return 'uploads/student_docs/' . $filename;
                }
            }
            return null; // Tidak ada file baru yang diupload
        }

        $paths = [
            'student_photo_path' => handle_upload('student_photo', $user_id, 'student_photo', $pdo),
            'kk_photo_path' => handle_upload('kk_photo', $user_id, 'kk_photo', $pdo),
            'birth_cert_photo_path' => handle_upload('birth_cert_photo', $user_id, 'birth_cert', $pdo),
            'ijazah_photo_path' => handle_upload('ijazah_photo', $user_id, 'ijazah', $pdo),
        ];

        // Ambil path lama jika tidak ada upload baru, agar tidak terhapus
        $stmt_current = $pdo->prepare("SELECT student_photo_path, kk_photo_path, birth_cert_photo_path, ijazah_photo_path FROM student_details WHERE user_id = ?");
        $stmt_current->execute([$user_id]);
        $current_paths = $stmt_current->fetch(PDO::FETCH_ASSOC);
        if ($current_paths) {
            foreach ($paths as $key => &$value) {
                if (is_null($value)) {
                    $value = $current_paths[$key];
                }
            }
        }

        // --- VALIDASI KETERHUBUNGAN (RELASI) ---
        
        // 1. Cek Validitas Username Walisantri (Jika diisi)
        $parent_username = trim($input['parent_username'] ?? '');
        if (!empty($parent_username)) {
            $stmtCheckParent = $pdo->prepare("SELECT user_id FROM users WHERE username = ?");
            $stmtCheckParent->execute([$parent_username]);
            if ($stmtCheckParent->rowCount() == 0) {
                sendJSONResponse(['success' => false, 'message' => "Username Walisantri '$parent_username' tidak ditemukan di sistem. Pastikan Walisantri sudah mendaftar."], 400);
                exit;
            }
        }

        // 2. Cek Unik Username Santri (Jika diubah)
        $new_username = trim($input['username'] ?? '');
        if (!empty($new_username)) {
            // Cek apakah username ini dipakai orang lain (bukan diri sendiri)
            $stmtCheckUser = $pdo->prepare("SELECT user_id FROM users WHERE username = ? AND user_id != ?");
            $stmtCheckUser->execute([$new_username, $user_id]);
            if ($stmtCheckUser->rowCount() > 0) {
                sendJSONResponse(['success' => false, 'message' => "Username Santri '$new_username' sudah digunakan oleh akun lain."], 409);
                exit;
            }
        }

        $pdo->beginTransaction();

        // 1. Update Data Akun Utama (Users Table)
        // Poin: 1 (Username), 2 (Nama Lengkap), 3 (Gender)
        $stmtUser = $pdo->prepare("UPDATE users SET username = ?, full_name = ?, gender = ? WHERE user_id = ?");
        $stmtUser->execute([
            $input['username'] ?? '',
            $input['full_name'] ?? '',
            $input['gender'] ?? '',
            $user_id
        ]);

        // 2. Update Detail Santri (Student Details Table)
        // Gunakan INSERT ... ON DUPLICATE KEY UPDATE
        $sql = "INSERT INTO student_details 
                (user_id, student_photo_path, ijazah_photo_path, kk_photo_path, birth_cert_photo_path, nik, nisn, birth_place, birth_date, student_phone, address, previous_school, previous_school_address, child_order, siblings_count, step_siblings_count, medical_history, responsible_party, parent_username, father_name, father_phone, father_job, father_address, mother_name, mother_phone, mother_job, mother_address, parent_name, parent_phone, guardian_job, guardian_address) 
                VALUES (:uid, :s_photo, :ijazah_photo, :kk_photo, :akte_photo, :nik, :nisn, :bplace, :bdate, :sphone, :addr, :pschool, :pschool_addr, :corder, :sib_cnt, :step_sib_cnt, :med_hist, :resp_party, :p_username, :fname, :fphone, :fjob, :faddr, :mname, :mphone, :mjob, :maddr, :pname, :pphone, :gjob, :gaddr)
                ON DUPLICATE KEY UPDATE 
                student_photo_path = VALUES(student_photo_path),
                ijazah_photo_path = VALUES(ijazah_photo_path),
                kk_photo_path = VALUES(kk_photo_path),
                birth_cert_photo_path = VALUES(birth_cert_photo_path),
                nik = VALUES(nik),
                nisn = VALUES(nisn),
                birth_place = VALUES(birth_place),
                birth_date = VALUES(birth_date),
                student_phone = VALUES(student_phone),
                address = VALUES(address),
                previous_school = VALUES(previous_school),
                previous_school_address = VALUES(previous_school_address),
                child_order = VALUES(child_order),
                siblings_count = VALUES(siblings_count),
                step_siblings_count = VALUES(step_siblings_count),
                medical_history = VALUES(medical_history),
                responsible_party = VALUES(responsible_party),
                parent_username = VALUES(parent_username),
                father_name = VALUES(father_name),
                father_phone = VALUES(father_phone),
                father_job = VALUES(father_job),
                father_address = VALUES(father_address),
                mother_name = VALUES(mother_name),
                mother_phone = VALUES(mother_phone),
                mother_job = VALUES(mother_job),
                mother_address = VALUES(mother_address),
                parent_name = VALUES(parent_name),
                parent_phone = VALUES(parent_phone),
                guardian_job = VALUES(guardian_job),
                guardian_address = VALUES(guardian_address)";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            'uid' => $user_id,
            's_photo' => $paths['student_photo_path'],
            'ijazah_photo' => $paths['ijazah_photo_path'],
            'kk_photo' => $paths['kk_photo_path'],
            'akte_photo' => $paths['birth_cert_photo_path'],
            'nik' => $input['nik'] ?? '',
            'nisn' => $input['nisn'] ?? '',
            'bplace' => $input['birth_place'] ?? '',
            'bdate' => !empty($input['birth_date']) ? $input['birth_date'] : null,
            'sphone' => $input['student_phone'] ?? '',
            'addr' => $input['address'] ?? '',
            'pschool' => $input['previous_school'] ?? '',
            'pschool_addr' => $input['previous_school_address'] ?? '',
            'corder' => $input['child_order'] ?? 0,
            'sib_cnt' => $input['siblings_count'] ?? 0,
            'step_sib_cnt' => $input['step_siblings_count'] ?? 0,
            'med_hist' => $input['medical_history'] ?? '',
            'resp_party' => $input['responsible_party'] ?? '',
            'p_username' => $input['parent_username'] ?? '',
            'fname' => $input['father_name'] ?? '',
            'fphone' => $input['father_phone'] ?? '',
            'fjob' => $input['father_job'] ?? '',
            'faddr' => $input['father_address'] ?? '',
            'mname' => $input['mother_name'] ?? '',
            'mphone' => $input['mother_phone'] ?? '',
            'mjob' => $input['mother_job'] ?? '',
            'maddr' => $input['mother_address'] ?? '',
            'pname' => $input['parent_name'] ?? '', // Walisantri Name
            'pphone' => $input['parent_phone'] ?? '', // Walisantri Phone
            'gjob' => $input['guardian_job'] ?? '', // Walisantri Job
            'gaddr' => $input['guardian_address'] ?? '' // Walisantri Address
        ]);

        $pdo->commit();
        sendJSONResponse(['success' => true, 'message' => 'Biodata berhasil disimpan.']);
    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        sendJSONResponse(['success' => false, 'message' => 'Gagal menyimpan: ' . $e->getMessage()], 500);
    }
}
?>