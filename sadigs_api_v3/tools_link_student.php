<?php
// Versi 2.0
header('Content-Type: text/html; charset=utf-8');
require_once 'db_connect.php';

if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['user_id'])) {
    die("<h1>Silakan Login Terlebih Dahulu</h1><p>Anda harus login sebagai Walisantri untuk menggunakan alat ini.</p><a href='../index.html'>Ke Halaman Login</a>");
}

$user_id = $_SESSION['user_id'];
$username = $_SESSION['username'];
$pdo = getDBConnection();

// --- 1. PASTIKAN STRUKTUR DATABASE BENAR ---
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS student_details (user_id INT PRIMARY KEY)");
    // Tambah kolom jika belum ada (Silent error handling)
    try { $pdo->exec("ALTER TABLE student_details ADD COLUMN parent_username VARCHAR(100) NULL"); } catch(Exception $e){}
    try { $pdo->exec("ALTER TABLE student_details ADD COLUMN parent_name VARCHAR(100) NULL"); } catch(Exception $e){}
    try { $pdo->exec("ALTER TABLE student_details ADD COLUMN grade VARCHAR(20) NULL"); } catch(Exception $e){}
    
    $pdo->exec("CREATE TABLE IF NOT EXISTS student_guardians (
        id INT AUTO_INCREMENT PRIMARY KEY, 
        student_id INT NOT NULL, walisantri_id INT NOT NULL, 
        UNIQUE KEY unique_relation (student_id, walisantri_id)
    )");
} catch (Exception $e) {
    die("Error Database Init: " . $e->getMessage());
}

// --- 2. PROSES FORMULIR ---
$message = "";
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $target_username = trim($_POST['student_username']);
    
    if (!empty($target_username)) {
        // Cari ID Santri berdasarkan Username
        $stmtFind = $pdo->prepare("SELECT user_id, full_name FROM users WHERE username = ?");
        $stmtFind->execute([$target_username]);
        $student = $stmtFind->fetch(PDO::FETCH_ASSOC);
        
        if ($student) {
            // Lakukan Linking
            $stmtLink = $pdo->prepare("INSERT IGNORE INTO student_guardians (student_id, walisantri_id) VALUES (?, ?)");
            if ($stmtLink->execute([$student['user_id'], $user_id])) {
                $message = "<div class='alert success'>✅ Berhasil menghubungkan santri: <strong>{$student['full_name']}</strong> ({$target_username}) ke akun Anda.</div>";
            } else {
                $message = "<div class='alert error'>❌ Gagal menyimpan ke database.</div>";
            }
        } else {
            $message = "<div class='alert error'>❌ Username Santri <strong>'$target_username'</strong> tidak ditemukan di sistem. Pastikan ejaannya benar.</div>";
        }
    }
}

// --- 3. AMBIL DATA SAAT INI ---
// Data User Login
$stmtUser = $pdo->prepare("SELECT full_name FROM users WHERE user_id = ?");
$stmtUser->execute([$user_id]);
$my_fullname = $stmtUser->fetchColumn();

// Data Anak Terhubung
$sqlChildren = "
    SELECT DISTINCT u.full_name, u.username, sd.grade 
    FROM users u
    LEFT JOIN student_details sd ON u.user_id = sd.user_id
    LEFT JOIN student_guardians sg ON u.user_id = sg.student_id
    WHERE sg.walisantri_id = ? OR sd.parent_username = ?
";
$stmtChildren = $pdo->prepare($sqlChildren);
$stmtChildren->execute([$user_id, $username]);
$children = $stmtChildren->fetchAll(PDO::FETCH_ASSOC);

?>
<!DOCTYPE html>
<html>
<head>
    <title>Alat Penghubung Santri</title>
    <style>
        body { font-family: sans-serif; max-width: 600px; margin: 40px auto; padding: 20px; background: #f4f6f8; }
        .card { background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); margin-bottom: 20px; }
        h1 { color: #26667F; margin-top: 0; }
        label { display: block; margin-bottom: 8px; font-weight: bold; }
        input[type="text"] { width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px; box-sizing: border-box; }
        button { background: #26667F; color: white; border: none; padding: 10px 20px; border-radius: 4px; cursor: pointer; font-weight: bold; margin-top: 10px; }
        button:hover { background: #1e556b; }
        .alert { padding: 15px; border-radius: 4px; margin-bottom: 20px; }
        .success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .error { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        th, td { border-bottom: 1px solid #eee; padding: 8px; text-align: left; }
        .back-link { display: block; margin-top: 20px; text-align: center; color: #666; text-decoration: none; }
    </style>
</head>
<body>

    <?= $message ?>

    <div class="card">
        <h1>🔗 Hubungkan Santri</h1>
        <p>Gunakan alat ini jika data anak tidak muncul otomatis di menu Walisantri.</p>
        
        <div style="background: #eef; padding: 10px; border-radius: 4px; margin-bottom: 15px; font-size: 0.9em;">
            <strong>Info Akun Anda:</strong><br>
            Username: <code><?= htmlspecialchars($username) ?></code><br>
            Nama Lengkap: <code><?= htmlspecialchars($my_fullname) ?></code>
        </div>

        <form method="POST">
            <label>Masukkan Username Santri (Anak Anda):</label>
            <input type="text" name="student_username" placeholder="Contoh: ahmad123" required>
            <p style="font-size: 0.8em; color: #666;">*Username adalah nama pengguna yang dipakai anak Anda untuk login.</p>
            <button type="submit">Hubungkan Sekarang</button>
        </form>
    </div>

    <div class="card">
        <h3>📋 Daftar Anak Terhubung</h3>
        <?php if (count($children) > 0): ?>
            <table>
                <thead>
                    <tr>
                        <th>Nama Lengkap</th>
                        <th>Username</th>
                        <th>Kelas</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($children as $child): ?>
                    <tr>
                        <td><?= htmlspecialchars($child['full_name']) ?></td>
                        <td><?= htmlspecialchars($child['username']) ?></td>
                        <td><?= htmlspecialchars($child['grade'] ?? '-') ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <p style="color: #888; font-style: italic;">Belum ada data santri yang terhubung.</p>
        <?php endif; ?>
    </div>

    <a href="../dashboard.html" class="back-link">← Kembali ke Dashboard</a>
</body>
</html>