<?php
// Script Debug Verifikasi User
header('Content-Type: text/html; charset=utf-8');
require_once 'db_connect.php';

echo "<!DOCTYPE html><html><head><title>Debug Verifikasi</title><style>body{font-family: sans-serif; padding: 20px;} table{border-collapse:collapse; width:100%;} th,td{border:1px solid #ddd; padding:8px;} th{background:#f2f2f2;} .warn{color:orange;} .err{color:red;} .ok{color:green;}</style></head><body>";
echo "<h1>🕵️‍♂️ Detektif Verifikasi User</h1>";

try {
    $pdo = getDBConnection();
    
    // 1. Cek User Pending (Apapun kondisinya)
    echo "<h3>Daftar Semua User dengan Role 'Pending' atau Tanpa Role</h3>";
    
    $sql = "SELECT u.user_id, u.username, u.full_name, u.is_active,
                   ur.role_name, ur.status as role_status
            FROM users u
            LEFT JOIN user_roles ur ON u.user_id = ur.user_id
            WHERE ur.status = 'pending' OR ur.role_name IS NULL
            ORDER BY u.user_id DESC";
            
    $stmt = $pdo->query($sql);
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (count($users) > 0) {
        echo "<table><thead><tr><th>ID</th><th>Username</th><th>Role Dipilih</th><th>Status Role</th><th>Akun Aktif?</th><th>Analisa Yayasan</th></tr></thead><tbody>";
        
        $student_roles = ['Santri', 'Santri Rijal', "Santri Nisa'", 'Walisantri'];
        
        foreach ($users as $u) {
            $role = $u['role_name'] ?: '<i>(Kosong)</i>';
            $isActive = $u['is_active'] ? '<span class="ok">Ya (1)</span>' : '<span class="warn">Tidak (0)</span>';
            
            // Analisa Kenapa Hilang
            $analysis = [];
            
            // Cek 1: Filter Santri
            if (in_array($u['role_name'], $student_roles)) {
                $analysis[] = "<span class='err'>TERSEMBUNYI:</span> Dianggap Santri (Yayasan tidak lihat).";
            }
            
            // Cek 2: User Aktif tapi Tanpa Role
            if ($u['is_active'] == 1 && empty($u['role_name'])) {
                $analysis[] = "<span class='err'>TERSEMBUNYI:</span> Akun sudah aktif tapi belum pilih peran.";
            }
            
            // Cek 3: Normal
            if (empty($analysis)) {
                $analysis[] = "<span class='ok'>MUNCUL:</span> Seharusnya tampil di verifikasi Yayasan.";
            }
            
            echo "<tr>
                    <td>{$u['user_id']}</td>
                    <td><strong>{$u['username']}</strong><br><small>{$u['full_name']}</small></td>
                    <td>$role</td>
                    <td>{$u['role_status']}</td>
                    <td>$isActive</td>
                    <td>" . implode('<br>', $analysis) . "</td>
                  </tr>";
        }
        echo "</tbody></table>";
    } else {
        echo "<p>Tidak ada user pending sama sekali di database.</p>";
    }
    
    echo "<div style='margin-top:20px; background:#e6fffa; padding:15px; border:1px solid #b2f5ea;'>";
    echo "<strong>Tips:</strong><br>";
    echo "1. Jika status <strong>TERSEMBUNYI (Dianggap Santri)</strong>: Login sebagai Kepala Sekolah untuk memverifikasi.<br>";
    echo "2. Jika status <strong>TERSEMBUNYI (Akun Aktif)</strong>: User tersebut harus login dan pilih peran ulang di dashboard.<br>";
    echo "</div>";

} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
?>