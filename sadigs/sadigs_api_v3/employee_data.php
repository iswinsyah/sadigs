<?php
header('Content-Type: application/json');
require_once 'db_connect.php';

if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['user_id'])) {
    sendJSONResponse(['success' => false, 'message' => 'Unauthorized'], 401);
}

$pdo = getDBConnection();
$user_id = $_SESSION['user_id'];

// --- GET: Ambil Data Pegawai ---
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    try {
        $stmt = $pdo->prepare("
            SELECT u.username, u.full_name, u.email, ed.* 
            FROM users u 
            LEFT JOIN employee_details ed ON u.user_id = ed.user_id 
            WHERE u.user_id = ?
        ");
        $stmt->execute([$user_id]);
        $data = $stmt->fetch(PDO::FETCH_ASSOC);
        
        sendJSONResponse(['success' => true, 'data' => $data ?: []]);
    } catch (Exception $e) {
        sendJSONResponse(['success' => false, 'message' => $e->getMessage()], 500);
    }
}

// --- POST: Simpan Data & Upload File ---
elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // 1. Handle Upload File
        if (!defined('UPLOAD_DIR')) define('UPLOAD_DIR', __DIR__ . '/uploads/employee_docs/');
        if (!is_dir(UPLOAD_DIR)) mkdir(UPLOAD_DIR, 0777, true);

        function handle_upload($file_key, $uid, $type, $pdo) {
            if (isset($_FILES[$file_key]) && $_FILES[$file_key]['error'] === UPLOAD_ERR_OK) {
                // Validasi Max 2MB
                if ($_FILES[$file_key]['size'] > 2 * 1024 * 1024) throw new Exception("File $file_key terlalu besar (Max 2MB).");
                
                $ext = pathinfo($_FILES[$file_key]['name'], PATHINFO_EXTENSION);
                $filename = "emp_{$uid}_{$type}_" . time() . "." . $ext;
                
                if (move_uploaded_file($_FILES[$file_key]['tmp_name'], UPLOAD_DIR . $filename)) {
                    return 'uploads/employee_docs/' . $filename;
                }
            }
            return null;
        }

        // Daftar file yang akan diupload
        $files = [
            'application_letter_path' => 'file_lamaran',
            'cv_path' => 'file_cv',
            'ijazah_path' => 'file_ijazah',
            'kk_path' => 'file_kk',
            'ktp_path' => 'file_ktp',
            'certificate_skill_path' => 'file_sertifikat',
            'certificate_award_path' => 'file_piagam'
        ];

        $uploaded_paths = [];
        
        // Ambil path lama dulu agar tidak tertimpa null jika tidak ada upload baru
        $stmtOld = $pdo->prepare("SELECT * FROM employee_details WHERE user_id = ?");
        $stmtOld->execute([$user_id]);
        $oldData = $stmtOld->fetch(PDO::FETCH_ASSOC) ?: [];

        foreach ($files as $db_col => $input_name) {
            $newPath = handle_upload($input_name, $user_id, $input_name, $pdo);
            $uploaded_paths[$db_col] = $newPath ? $newPath : ($oldData[$db_col] ?? null);
        }

        $pdo->beginTransaction();

        // 2. Update Tabel Users (Nama & Email)
        $stmtUser = $pdo->prepare("UPDATE users SET full_name = ?, email = ? WHERE user_id = ?");
        $stmtUser->execute([$_POST['full_name'], $_POST['email'], $user_id]);

        // CACHE BUSTER v1.1
        // 3. Update/Insert Tabel Employee Details
        $sql = "INSERT INTO employee_details (
                    user_id, nik, npwp, birth_place, birth_date, marital_status, phone, address,
                    last_education, graduation_year,
                    entry_date,
                    facebook_url, instagram_url, tiktok_url, threads_url, youtube_url,
                    application_letter_path, cv_path, ijazah_path, kk_path, ktp_path, certificate_skill_path, certificate_award_path
                ) VALUES (
                    :uid, :nik, :npwp, :bplace, :bdate, :mstatus, :phone, :addr,
                    :edu, :grad_year, :entry_date,
                    :fb, :ig, :tt, :th, :yt,
                    :path1, :path2, :path3, :path4, :path5, :path6, :path7
                )
                ON DUPLICATE KEY UPDATE
                    nik = VALUES(nik), npwp = VALUES(npwp), birth_place = VALUES(birth_place), birth_date = VALUES(birth_date),
                    marital_status = VALUES(marital_status), phone = VALUES(phone), address = VALUES(address),
                    last_education = VALUES(last_education), graduation_year = VALUES(graduation_year),
                    entry_date = VALUES(entry_date),
                    facebook_url = VALUES(facebook_url), instagram_url = VALUES(instagram_url), tiktok_url = VALUES(tiktok_url),
                    threads_url = VALUES(threads_url), youtube_url = VALUES(youtube_url),
                    application_letter_path = VALUES(application_letter_path), cv_path = VALUES(cv_path),
                    ijazah_path = VALUES(ijazah_path), kk_path = VALUES(kk_path), ktp_path = VALUES(ktp_path),
                    certificate_skill_path = VALUES(certificate_skill_path), certificate_award_path = VALUES(certificate_award_path)";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            'uid' => $user_id,
            'nik' => $_POST['nik'] ?? '',
            'npwp' => $_POST['npwp'] ?? null,
            'bplace' => $_POST['birth_place'] ?? '',
            'bdate' => !empty($_POST['birth_date']) ? $_POST['birth_date'] : null,
            'mstatus' => $_POST['marital_status'] ?? null,
            'phone' => $_POST['phone'] ?? '',
            'addr' => $_POST['address'] ?? '',
            'edu' => $_POST['last_education'] ?? '',
            'grad_year' => !empty($_POST['graduation_year']) ? $_POST['graduation_year'] : null,
            'entry_date' => !empty($_POST['entry_date']) ? $_POST['entry_date'] : null,
            'fb' => $_POST['facebook_url'] ?? null,
            'ig' => $_POST['instagram_url'] ?? null,
            'tt' => $_POST['tiktok_url'] ?? null,
            'th' => $_POST['threads_url'] ?? null,
            'yt' => $_POST['youtube_url'] ?? null,
            'path1' => $uploaded_paths['application_letter_path'],
            'path2' => $uploaded_paths['cv_path'],
            'path3' => $uploaded_paths['ijazah_path'],
            'path4' => $uploaded_paths['kk_path'],
            'path5' => $uploaded_paths['ktp_path'],
            'path6' => $uploaded_paths['certificate_skill_path'],
            'path7' => $uploaded_paths['certificate_award_path']
        ]);

        $pdo->commit();
        sendJSONResponse(['success' => true, 'message' => 'Biodata pegawai berhasil disimpan.']);

    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        sendJSONResponse(['success' => false, 'message' => 'Gagal menyimpan: ' . $e->getMessage()], 500);
    }
}
?>