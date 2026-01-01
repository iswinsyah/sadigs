<?php
// SADIGS DATABASE DOCTOR
// Script untuk mendiagnosa kesehatan database dan izin akses
header('Content-Type: text/html; charset=utf-8');
require_once 'db_connect.php';

echo "<!DOCTYPE html><html><head><title>SADIGS Database Doctor</title>
<style>
    body{font-family: 'Segoe UI', sans-serif; padding: 30px; background: #f4f6f9; color: #333;}
    .card{background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 5px rgba(0,0,0,0.1); margin-bottom: 20px;}
    h1{color: #26667F; margin-top: 0;}
    h3{border-bottom: 2px solid #eee; padding-bottom: 10px; margin-top: 0;}
    .status{font-weight: bold; padding: 5px 10px; border-radius: 4px; display: inline-block;}
    .ok{background: #d4edda; color: #155724;}
    .err{background: #f8d7da; color: #721c24;}
    .warn{background: #fff3cd; color: #856404;}
    table{width: 100%; border-collapse: collapse; margin-top: 10px;}
    th, td{padding: 10px; border-bottom: 1px solid #eee; text-align: left;}
</style>
</head><body>";

echo "<div class='card'><h1>🏥 SADIGS Database Doctor</h1><p>Mendiagnosa masalah menu yang tidak muncul.</p></div>";

try {
    $pdo = getDBConnection();
    
    // 1. CEK KONEKSI
    echo "<div class='card'><h3>1. Koneksi Database</h3>";
    echo "<span class='status ok'>✅ Terhubung ke Database</span>";
    echo "</div>";

    // 2. CEK TABEL MENU_PERMISSIONS
    echo "<div class='card'><h3>2. Tabel Izin Menu (menu_permissions)</h3>";
    try {
        $count = $pdo->query("SELECT COUNT(*) FROM menu_permissions")->fetchColumn();
        if ($count > 0) {
            echo "<p><span class='status ok'>✅ Tabel Ada & Terisi</span> Total baris: <strong>$count</strong></p>";
        } else {
            echo "<p><span class='status err'>❌ Tabel Kosong!</span> Ini penyebab menu tidak muncul.</p>";
            echo "<a href='get_menu_permissions.php' target='_blank' style='background:#26667F; color:white; padding:10px; text-decoration:none; border-radius:5px;'>KLIK DISINI UNTUK PERBAIKI OTOMATIS</a>";
        }
    } catch (Exception $e) {
        echo "<p><span class='status err'>❌ Tabel Tidak Ditemukan</span> " . $e->getMessage() . "</p>";
        echo "<a href='get_menu_permissions.php' target='_blank' style='background:#26667F; color:white; padding:10px; text-decoration:none; border-radius:5px;'>KLIK DISINI UNTUK BUAT TABEL</a>";
    }
    echo "</div>";

    // 3. CEK USER & ROLE SAAT INI
    session_start();
    echo "<div class='card'><h3>3. Status User Login</h3>";
    if (isset($_SESSION['user_id'])) {
        $uid = $_SESSION['user_id'];
        $uname = $_SESSION['username'];
        echo "<p>User ID: <strong>$uid</strong> | Username: <strong>$uname</strong></p>";
        
        // Cek Role di Database
        $stmt = $pdo->prepare("SELECT role_name, status FROM user_roles WHERE user_id = ?");
        $stmt->execute([$uid]);
        $roles = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (count($roles) > 0) {
            echo "<table><tr><th>Role Name</th><th>Status</th><th>Cek Izin Menu</th></tr>";
            foreach ($roles as $r) {
                $rname = $r['role_name'];
                $status = $r['status'];
                $statusClass = ($status == 'approved') ? 'ok' : 'err';
                
                // Cek apakah role ini punya izin di menu_permissions
                $permCount = $pdo->prepare("SELECT COUNT(*) FROM menu_permissions WHERE role_name = ? AND is_allowed = 1");
                $permCount->execute([$rname]);
                $pCount = $permCount->fetchColumn();
                
                $permStatus = ($pCount > 0) ? "<span class='status ok'>Punya $pCount Izin</span>" : "<span class='status err'>0 Izin (Menu Hilang)</span>";

                echo "<tr>
                    <td>$rname</td>
                    <td><span class='status $statusClass'>$status</span></td>
                    <td>$permStatus</td>
                </tr>";
            }
            echo "</table>";
        } else {
            echo "<p class='status err'>User ini tidak memiliki Role apapun di database!</p>";
        }
    } else {
        echo "<p class='status warn'>⚠️ Anda belum login. Silakan login dulu.</p>";
    }
    echo "</div>";

} catch (Exception $e) {
    echo "<div class='card'><h3 style='color:red'>CRITICAL ERROR</h3>" . $e->getMessage() . "</div>";
}

echo "</body></html>";
?>