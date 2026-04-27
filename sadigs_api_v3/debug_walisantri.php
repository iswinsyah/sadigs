<?php
require_once 'db_connect.php';

try {
    $pdo = getDBConnection();
    echo "<h1>Diagnosa Hubungan Walisantri - Santri</h1>";
    echo "<style>body{font-family:sans-serif; padding:20px;} table{border-collapse:collapse; width:100%; margin-top:10px;} th,td{border:1px solid #ddd; padding:8px; text-align:left;} th{background-color:#f2f2f2;}</style>";

    // 1. Cek User Walisantri
    echo "<h3>1. Daftar Akun Walisantri (Copy username dari sini)</h3>";
    $sqlWali = "SELECT u.user_id, u.username, u.full_name 
                FROM users u 
                JOIN user_roles ur ON u.user_id = ur.user_id 
                WHERE ur.role_name = 'Walisantri'";
    $walis = $pdo->query($sqlWali)->fetchAll(PDO::FETCH_ASSOC);
    
    $waliUsernames = array_column($walis, 'username');

    if (count($walis) > 0) {
        echo "<table><thead><tr><th>ID</th><th>Username</th><th>Nama Lengkap</th></tr></thead><tbody>";
        foreach ($walis as $w) {
            echo "<tr><td>{$w['user_id']}</td><td><strong style='color:blue'>{$w['username']}</strong></td><td>{$w['full_name']}</td></tr>";
        }
        echo "</tbody></table>";
    } else {
        echo "<p style='color:red'>❌ Tidak ada user dengan role 'Walisantri'.</p>";
    }

    // 2. Cek Santri dan Kolom parent_username
    echo "<h3>2. Daftar Santri & Link ke Wali</h3>";
    $sqlSantri = "SELECT u.user_id, u.username, u.full_name, sd.parent_username 
                  FROM users u 
                  JOIN user_roles ur ON u.user_id = ur.user_id 
                  LEFT JOIN student_details sd ON u.user_id = sd.user_id
                  WHERE ur.role_name = 'Santri'";
    $santris = $pdo->query($sqlSantri)->fetchAll(PDO::FETCH_ASSOC);

    if (count($santris) > 0) {
        echo "<table><thead><tr><th>ID</th><th>Username Santri</th><th>Nama Santri</th><th>Username Wali (di Database)</th><th>Status</th></tr></thead><tbody>";
        foreach ($santris as $s) {
            $pUser = $s['parent_username'];
            $status = "";
            
            if (empty($pUser)) {
                $status = "<span style='color:red'>❌ Kosong (Belum diisi)</span>";
            } elseif (in_array($pUser, $waliUsernames)) {
                $status = "<span style='color:green'>✅ Terhubung (Valid)</span>";
            } else {
                $status = "<span style='color:orange'>⚠️ Tidak Cocok (Username Wali '$pUser' tidak ditemukan)</span>";
            }

            echo "<tr>
                    <td>{$s['user_id']}</td>
                    <td>{$s['username']}</td>
                    <td>{$s['full_name']}</td>
                    <td>" . ($pUser ? "<strong>$pUser</strong>" : "<em>-</em>") . "</td>
                    <td>$status</td>
                  </tr>";
        }
        echo "</tbody></table>";
    } else {
        echo "<p>Tidak ada data santri.</p>";
    }

} catch (Exception $e) {
    echo "<h1>Error</h1><p>" . $e->getMessage() . "</p>";
}
?>