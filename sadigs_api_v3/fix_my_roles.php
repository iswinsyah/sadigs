<?php
header('Content-Type: text/html; charset=utf-8');
require_once 'db_connect.php';

echo "<!DOCTYPE html><html><head><title>Perbaikan Peran Akun</title><style>body{font-family: sans-serif; padding: 20px; line-height: 1.6;} h1,h2{color: #26667F;} code{background: #f1f1f1; padding: 2px 5px; border-radius: 4px;} .ok{color:green;} .err{color:red;} .warn{color:orange;}</style></head><body>";
echo "<h1>Alat Perbaikan Peran Akun</h1>";

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id'])) {
    echo "<p class='err'>❌ Anda tidak sedang login. Silakan login terlebih dahulu lalu kembali ke halaman ini.</p>";
    echo "</body></html>";
    exit;
}

$user_id = $_SESSION['user_id'];
$username = $_SESSION['username'];

echo "<p>Memeriksa semua peran untuk user: <strong>" . htmlspecialchars($username) . "</strong> (ID: $user_id)</p>";

try {
    $pdo = getDBConnection();

    // Cek apakah ada aksi perbaikan
    if (isset($_GET['fix']) && $_GET['fix'] === 'all') {
        $stmt_fix = $pdo->prepare("UPDATE user_roles SET status = 'approved' WHERE user_id = ? AND status = 'pending'");
        $stmt_fix->execute([$user_id]);
        $affected_rows = $stmt_fix->rowCount();

        if ($affected_rows > 0) {
            echo "<h2 class='ok'>✅ Perbaikan Berhasil!</h2>";
            echo "<p><strong>$affected_rows</strong> peran Anda telah diaktifkan (approved). Sesi Anda akan diperbarui saat Anda kembali ke dashboard.</p>";
            echo "<p>Silakan <a href='../dashboard.html'><strong>kembali ke Dashboard</strong></a> dan refresh halaman (Ctrl + F5).</p><hr>";
        } else {
            echo "<h2 class='warn'>ℹ️ Tidak ada yang diperbaiki.</h2><p>Semua peran Anda sepertinya sudah aktif.</p><hr>";
        }
    }

    $stmt = $pdo->prepare("SELECT role_name, status FROM user_roles WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $roles = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (count($roles) > 0) {
        echo "<h2>Daftar Peran Anda di Database:</h2>";
        echo "<table border='1' cellpadding='5' style='border-collapse:collapse;'><tr><th>Nama Peran</th><th>Status</th></tr>";
        $found_pending = false;
        foreach ($roles as $role) {
            $status_html = htmlspecialchars($role['status']);
            if ($role['status'] !== 'approved') {
                $status_html = "<strong class='warn'>" . $status_html . "</strong>";
                $found_pending = true;
            } else {
                 $status_html = "<strong class='ok'>" . $status_html . "</strong>";
            }
            echo "<tr><td>" . htmlspecialchars($role['role_name']) . "</td><td>$status_html</td></tr>";
        }
        echo "</table>";

        if ($found_pending) {
            echo "<div style='background:#fffbe6; border:1px solid #fde047; padding:15px; margin-top:20px;'>";
            echo "<h3>⚠️ MASALAH DITEMUKAN!</h3>";
            echo "<p>Satu atau lebih peran Anda masih berstatus <strong>pending</strong>. Ini menyebabkan dashboard dan menu Anda tidak muncul.</p>";
            echo "<p><strong>Solusi:</strong> Klik tombol di bawah ini untuk mengaktifkan SEMUA peran Anda yang masih pending.</p>";
            echo "<a href='?fix=all' style='display:inline-block; background:green; color:white; padding:10px 15px; text-decoration:none; border-radius:5px;'>Aktifkan Semua Peran Saya</a>";
            echo "</div>";
        } else {
            echo "<p class='ok' style='margin-top:20px;'>✅ <strong>KESIMPULAN:</strong> Semua peran Anda di database sudah berstatus 'approved'. Jika widget masih tidak muncul, masalahnya ada di tempat lain.</p>";
        }

    } else {
        echo "<p class='err'>❌ Anda tidak memiliki peran apapun di database. Ini menyebabkan dashboard kosong. Silakan gunakan halaman Profil untuk mengajukan peran.</p>";
    }

} catch (Exception $e) {
    echo "<h2>Error</h2>";
    echo "<p class='err'>Gagal terhubung atau menjalankan query: " . $e->getMessage() . "</p>";
}

echo "</body></html>";
?>