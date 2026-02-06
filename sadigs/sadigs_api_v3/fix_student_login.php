<?php
// sadigs_api_v3/fix_student_login.php
// ALAT BANTU DARURAT: Reset Password & Cek Akun Santri

require_once 'db_connect.php';
$pdo = getDBConnection();
$message = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $target_username = trim($_POST['username']);
    $new_password = trim($_POST['password']);

    if (!empty($target_username) && !empty($new_password)) {
        // 1. Cari User
        $stmt = $pdo->prepare("SELECT user_id, full_name FROM users WHERE username = ?");
        $stmt->execute([$target_username]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user) {
            // 2. Reset Password
            $hash = password_hash($new_password, PASSWORD_DEFAULT);
            $stmtUpd = $pdo->prepare("UPDATE users SET password_hash = ? WHERE user_id = ?");
            $stmtUpd->execute([$hash, $user['user_id']]);

            // 3. Pastikan Role Santri Ada
            $stmtRole = $pdo->prepare("INSERT INTO user_roles (user_id, role_name, status) VALUES (?, 'Santri Rijal', 'approved') ON DUPLICATE KEY UPDATE status='approved'");
            $stmtRole->execute([$user['user_id']]);

            $message = "<div style='background:#dcfce7; color:#166534; padding:10px; border-radius:5px;'>
                ✅ Berhasil! Password untuk santri <strong>{$user['full_name']}</strong> ({$target_username}) 
                telah diubah menjadi: <strong>$new_password</strong>.<br>
                Role 'Santri Rijal' juga sudah dipastikan aktif. Silakan coba login sekarang.
            </div>";
        } else {
            $message = "<div style='background:#fee2e2; color:#991b1b; padding:10px; border-radius:5px;'>
                ❌ Username <strong>$target_username</strong> tidak ditemukan di database. Cek ejaan.
            </div>";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Perbaiki Login Santri</title>
    <style>
        body { font-family: sans-serif; display: flex; justify-content: center; padding-top: 50px; background: #f3f4f6; }
        .card { background: white; padding: 30px; border-radius: 10px; box-shadow: 0 4px 6px rgba(0,0,0,0.1); width: 100%; max-width: 400px; }
        input { width: 100%; padding: 10px; margin: 5px 0 15px; border: 1px solid #ddd; border-radius: 5px; box-sizing: border-box; }
        button { width: 100%; padding: 12px; background: #2563eb; color: white; border: none; border-radius: 5px; cursor: pointer; font-weight: bold; }
        button:hover { background: #1d4ed8; }
        label { font-weight: bold; font-size: 0.9rem; color: #374151; }
    </style>
</head>
<body>
    <div class="card">
        <h2 style="margin-top:0; color:#1f2937;">🔧 Perbaiki Login Santri</h2>
        <p style="font-size:0.9rem; color:#6b7280; margin-bottom:20px;">
            Gunakan alat ini jika Anda tidak bisa masuk ke akun anak/santri tertentu.
        </p>
        
        <?= $message ?>

        <form method="POST">
            <label>Username Santri (Anak)</label>
            <input type="text" name="username" placeholder="Contoh: ahmad123" required>
            
            <label>Password Baru</label>
            <input type="text" name="password" placeholder="Masukkan password baru" required>
            
            <button type="submit">Reset Password & Perbaiki Akun</button>
        </form>
        
        <div style="margin-top: 20px; font-size: 0.8rem; color: #666; border-top: 1px solid #eee; padding-top: 10px;">
            <strong>Tips:</strong><br>
            Setelah reset, coba login menggunakan Username dan Password baru tersebut di halaman login utama.
        </div>
    </div>
</body>
</html>