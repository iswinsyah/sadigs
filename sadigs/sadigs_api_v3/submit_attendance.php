<?php
header('Content-Type: application/json');
require_once 'db_connect.php';

if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$user_id = $_SESSION['user_id'];
$notes = $_POST['notes'] ?? '';
$lat = $_POST['latitude'] ?? null;
$long = $_POST['longitude'] ?? null;
$category = $_POST['category'] ?? 'Absensi Harian';
$address = $_POST['address'] ?? ''; // Tangkap alamat
$is_duty_outside = isset($_POST['is_duty_outside']) ? true : false;

// --- VALIDASI LOKASI (WAJIB GPS) ---
if (empty($lat) || empty($long)) {
    echo json_encode(['success' => false, 'message' => 'Gagal: Anda HARUS menyalakan fitur lokasi (GPS) untuk melakukan absensi.']);
    exit;
}

$pdo = getDBConnection();

// Update Schema: Tambah kolom category jika belum ada (PENTING: Agar tidak error saat WHERE category=...)
try { $pdo->exec("ALTER TABLE employee_attendance ADD COLUMN category VARCHAR(50) DEFAULT 'Absensi Harian'"); } catch (Exception $e) { }
// Update Schema: Tambah kolom alamat
try { $pdo->exec("ALTER TABLE employee_attendance ADD COLUMN location_address TEXT NULL"); } catch (Exception $e) { }
// Update Schema: Tambah kolom bukti dinas luar
try { $pdo->exec("ALTER TABLE employee_attendance ADD COLUMN proof_file VARCHAR(255) NULL"); } catch (Exception $e) { }

try {
    // Cek apakah sudah absen hari ini UNTUK KATEGORI INI
    $stmt = $pdo->prepare("SELECT id, check_out_time FROM employee_attendance WHERE user_id = ? AND attendance_date = CURDATE() AND category = ?");
    $stmt->execute([$user_id, $category]);
    $existing = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$existing) {
        // BELUM ABSEN -> LAKUKAN CHECK IN
        
        // Handle Upload Bukti jika Dinas Luar
        $proof_path = null;
        $status = 'Hadir';

        if ($is_duty_outside) {
            $status = 'Dinas Luar';
            if (isset($_FILES['proof_file']) && $_FILES['proof_file']['error'] === UPLOAD_ERR_OK) {
                $uploadDir = 'uploads/attendance_proofs/';
                if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);
                
                $ext = pathinfo($_FILES['proof_file']['name'], PATHINFO_EXTENSION);
                $filename = 'duty_' . $user_id . '_' . time() . '.' . $ext;
                
                if (move_uploaded_file($_FILES['proof_file']['tmp_name'], $uploadDir . $filename)) {
                    $proof_path = $uploadDir . $filename;
                }
            }
        }

        $sql = "INSERT INTO employee_attendance (user_id, attendance_date, check_in_time, status, notes, location_lat, location_long, category, location_address, proof_file) 
                VALUES (?, CURDATE(), CURTIME(), ?, ?, ?, ?, ?, ?, ?)";
        $stmtInsert = $pdo->prepare($sql);
        $stmtInsert->execute([$user_id, $status, $notes, $lat, $long, $category, $address, $proof_path]);
        
        echo json_encode(['success' => true, 'message' => "Berhasil Absen Masuk ($category)!"]);
    } else {
        // SUDAH ABSEN MASUK -> CEK APAKAH SUDAH PULANG?
        if ($existing['check_out_time']) {
            echo json_encode(['success' => false, 'message' => "Anda sudah melakukan absen pulang untuk $category hari ini."]);
        } else {
            // LAKUKAN CHECK OUT
            // Append notes jika ada catatan baru
            $newNotes = $notes ? " | Pulang: " . $notes : "";
            
            $sql = "UPDATE employee_attendance 
                    SET check_out_time = CURTIME(), notes = CONCAT(notes, ?) 
                    WHERE id = ?";
            $stmtUpdate = $pdo->prepare($sql);
            $stmtUpdate->execute([$newNotes, $existing['id']]);
            
            echo json_encode(['success' => true, 'message' => "Berhasil Absen Pulang ($category)!"]);
        }
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Database Error: ' . $e->getMessage()]);
}
?>