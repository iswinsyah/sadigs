<?php
header('Content-Type: application/json');
require_once 'db_connect.php';

if (session_status() === PHP_SESSION_NONE) session_start();

// --- KONFIGURASI LOKASI SEKOLAH (Villa Quran Baron Malang) ---
// Ganti dengan koordinat asli sekolah
define('SCHOOL_LAT', -7.9785); 
define('SCHOOL_LNG', 112.6316);
define('MAX_DISTANCE_METERS', 200); // Radius toleransi jarak

// --- MAPPING JAM PELAJARAN ---
$time_slots = [
    1 => ['start' => '04:30', 'end' => '05:15'],
    2 => ['start' => '05:15', 'end' => '06:00'],
    3 => ['start' => '07:30', 'end' => '08:15'],
    4 => ['start' => '08:15', 'end' => '09:00'],
    5 => ['start' => '09:15', 'end' => '10:00'],
    6 => ['start' => '10:00', 'end' => '10:45'],
    7 => ['start' => '10:45', 'end' => '11:15'],
    8 => ['start' => '15:15', 'end' => '16:00'],
    9 => ['start' => '16:00', 'end' => '16:45'],
    10 => ['start' => '16:45', 'end' => '17:30'],
];

if (!isset($_SESSION['user_id'])) {
    sendJSONResponse(['success' => false, 'message' => 'Unauthorized'], 401);
}

$user_id = $_SESSION['user_id'];
$input = json_decode(file_get_contents('php://input'), true);

$password = $input['password'] ?? '';
$type = $input['type'] ?? ''; // 'Masuk' or 'Pulang'
$category = $input['category'] ?? 'Absensi Harian';
$lat = $input['latitude'] ?? null;
$lng = $input['longitude'] ?? null;
$address = $input['address'] ?? '';

if (!$password || !$type || !$lat || !$lng) {
    sendJSONResponse(['success' => false, 'message' => 'Data tidak lengkap.'], 400);
}

$pdo = getDBConnection();

try {
    // 1. Verifikasi Password
    $stmtUser = $pdo->prepare("SELECT password_hash FROM users WHERE user_id = ?");
    $stmtUser->execute([$user_id]);
    $user = $stmtUser->fetch(PDO::FETCH_ASSOC);

    if (!$user || !password_verify($password, $user['password_hash'])) {
        sendJSONResponse(['success' => false, 'message' => 'Password salah.'], 401);
    }

    // 2. Cek Lokasi (Geofencing)
    $distance = calculateDistance($lat, $lng, SCHOOL_LAT, SCHOOL_LNG);
    if ($distance > MAX_DISTANCE_METERS) {
        sendJSONResponse(['success' => false, 'message' => "Anda berada di luar area sekolah ($distance meter). Harap absen di lokasi."], 400);
    }

    // 3. Logika Khusus Ustadz (Berdasarkan Jadwal)
    // Cek apakah user punya role Ustadz
    $isUstadz = false;
    $roles = $_SESSION['roles'] ?? [];
    if (in_array('Ustadz', $roles) || in_array('Ustadzah', $roles)) {
        $isUstadz = true;
    }

    $status = 'Hadir'; // Default
    $note = '';

    if ($isUstadz && $category === 'Absensi Harian') {
        // Ambil hari ini (Senin, Selasa, dll)
        $days = ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'];
        $today = $days[date('w')];

        // Ambil jadwal mengajar hari ini
        $stmtSched = $pdo->prepare("SELECT MIN(period_index) as first_period, MAX(period_index) as last_period FROM schedule_assignments WHERE teacher_id = ? AND day = ?");
        $stmtSched->execute([$user_id, $today]);
        $schedule = $stmtSched->fetch(PDO::FETCH_ASSOC);

        if ($schedule && $schedule['first_period']) {
            $firstPeriod = $schedule['first_period'];
            $lastPeriod = $schedule['last_period'];

            $startTime = $time_slots[$firstPeriod]['start'];
            $endTime = $time_slots[$lastPeriod]['end'];
            $currentTime = date('H:i');

            if ($type === 'Masuk') {
                // Cek Keterlambatan
                if ($currentTime > $startTime) {
                    $status = 'Telat';
                    // Hitung selisih menit
                    $start = strtotime($startTime);
                    $now = strtotime($currentTime);
                    $diffMins = round(($now - $start) / 60);
                    $note = "Telat $diffMins menit (Jadwal: $startTime)";
                } else {
                    $note = "Tepat Waktu (Jadwal: $startTime)";
                }
            } elseif ($type === 'Pulang') {
                // Cek Pulang Cepat
                if ($currentTime < $endTime) {
                    // Cek Izin
                    $hasPermission = checkPermission($pdo, $user_id);
                    if (!$hasPermission) {
                        sendJSONResponse(['success' => false, 'message' => "Belum waktunya pulang. Jadwal Anda selesai pukul $endTime. Hubungi Kepala Sekolah jika ada keperluan mendesak."], 400);
                    } else {
                        $note = "Pulang Awal dengan Izin (Jadwal: $endTime)";
                    }
                } else {
                    $note = "Pulang Sesuai Jadwal ($endTime)";
                }
            }
        } else {
            // Tidak ada jadwal hari ini
            $note = "Tidak ada jadwal mengajar hari ini.";
        }
    }

    // 4. Simpan Absensi
    // Pastikan tabel ada
    $pdo->exec("CREATE TABLE IF NOT EXISTS attendance (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        date DATE NOT NULL,
        time TIME NOT NULL,
        type ENUM('Masuk', 'Pulang') NOT NULL,
        category VARCHAR(50),
        status VARCHAR(50),
        latitude DECIMAL(10, 8),
        longitude DECIMAL(11, 8),
        address TEXT,
        notes TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");

    // Cek double absen (opsional, untuk mencegah spam)
    $stmtCheck = $pdo->prepare("SELECT id FROM attendance WHERE user_id = ? AND date = CURDATE() AND type = ? ORDER BY id DESC LIMIT 1");
    $stmtCheck->execute([$user_id, $type]);
    if ($stmtCheck->fetch()) {
        // Jika sudah absen, update saja atau tolak? Kita tolak biar rapi.
        sendJSONResponse(['success' => false, 'message' => "Anda sudah melakukan absen $type hari ini."], 400);
    }

    $stmt = $pdo->prepare("INSERT INTO attendance (user_id, date, time, type, category, status, latitude, longitude, address, notes) VALUES (?, CURDATE(), CURTIME(), ?, ?, ?, ?, ?, ?, ?)");
    $stmt->execute([$user_id, $type, $category, $status, $lat, $lng, $address, $note]);

    sendJSONResponse(['success' => true, 'message' => "Absensi $type berhasil dicatat. Status: $status. $note"]);

} catch (Exception $e) {
    sendJSONResponse(['success' => false, 'message' => 'Error: ' . $e->getMessage()], 500);
}

// --- HELPER FUNCTIONS ---

function calculateDistance($lat1, $lon1, $lat2, $lon2) {
    $earthRadius = 6371000; // meters
    $dLat = deg2rad($lat2 - $lat1);
    $dLon = deg2rad($lon2 - $lon1);
    $a = sin($dLat / 2) * sin($dLat / 2) +
         cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
         sin($dLon / 2) * sin($dLon / 2);
    $c = 2 * atan2(sqrt($a), sqrt(1 - $a));
    return $earthRadius * $c;
}

function checkPermission($pdo, $user_id) {
    // Cek tabel leave_requests
    // Asumsi struktur: user_id, start_date, end_date, approvals (JSON)
    $today = date('Y-m-d');
    $stmt = $pdo->prepare("SELECT approvals FROM leave_requests WHERE user_id = ? AND ? BETWEEN start_date AND end_date");
    $stmt->execute([$user_id, $today]);
    $request = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($request) {
        $approvals = json_decode($request['approvals'], true);
        // Cek apakah Kepala Sekolah sudah approve
        if (isset($approvals['Kepala Sekolah']) && $approvals['Kepala Sekolah'] === 'approved') {
            return true;
        }
        // Atau jika semua recipient sudah approve
        if (!in_array('pending', $approvals) && !in_array('rejected', $approvals)) {
            return true;
        }
    }
    return false;
}
?>