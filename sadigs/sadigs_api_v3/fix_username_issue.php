<?php
// sadigs_api_v3/fix_username_issue.php
// ALAT BANTU: Memperbaiki Username yang mengandung tanda petik (Bikin error JS)

require_once 'db_connect.php';
$pdo = getDBConnection();

// Username yang bermasalah
$trouble_username = "Maria Hasna'";
// Username baru (Aman untuk sistem)
$safe_username = "MariaHasna";

// 1. Cek apakah user ada
$stmt = $pdo->prepare("SELECT user_id, full_name FROM users WHERE username = ?");
$stmt->execute([$trouble_username]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if ($user) {
    // 2. Update Username
    $update = $pdo->prepare("UPDATE users SET username = ? WHERE user_id = ?");
    $update->execute([$safe_username, $user['user_id']]);
    
    echo "<div style='font-family:sans-serif; padding:20px; background:#dcfce7; color:#166534; border-radius:8px; border: 1px solid #bbf7d0; max-width: 600px; margin: 50px auto;'>";
    echo "✅ <strong>BERHASIL DIPERBAIKI!</strong><br><br>";
    echo "Nama Santri: <strong>{$user['full_name']}</strong><br>";
    echo "Username Lama: <span style='text-decoration:line-through; color:red;'>$trouble_username</span> (Penyebab Error)<br>";
    echo "Username Baru: <strong>$safe_username</strong> (Aman)<br><br>";
    echo "👉 <strong>Solusi:</strong> Silakan kembali ke <u>Tabel Manajemen Pengguna</u> dan <strong>Refresh</strong> halaman. Tombol login untuk santri ini sekarang pasti sudah bisa diklik.";
    echo "</div>";
} else {
    echo "<div style='font-family:sans-serif; padding:20px; background:#fee2e2; color:#991b1b; border-radius:8px; border: 1px solid #fecaca; max-width: 600px; margin: 50px auto;'>";
    echo "❌ Username <strong>$trouble_username</strong> tidak ditemukan di database.<br>";
    echo "Mungkin sudah diperbaiki sebelumnya? Coba cari dengan username <strong>$safe_username</strong>.";
    echo "</div>";
}
?>