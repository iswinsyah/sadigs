<?php
require_once 'db_connect.php';

try {
    $pdo = getDBConnection();
    
    // 1. Pastikan tabel ada
    $sql_table = "CREATE TABLE IF NOT EXISTS menu_permissions (
        id INT AUTO_INCREMENT PRIMARY KEY,
        role_name VARCHAR(50) NOT NULL,
        menu_id VARCHAR(50) NOT NULL,
        can_view BOOLEAN DEFAULT TRUE,
        UNIQUE KEY unique_permission (role_name, menu_id)
    )";
    $pdo->exec($sql_table);

    // 2. Definisi Aturan Menu (Audit Result)
    $permissions = [
        // --- MENU UMUM (Semua Role Aktif) ---
        'navDashboard' => ['Ketua Yayasan', 'Sekretaris Yayasan', 'Bendahara Yayasan', 'Kepala Sekolah', 'Kepala Asrama', 'Sekretaris Sekolah', 'Bendahara Sekolah', 'Musyrif', 'Ustadz', 'Santri', 'Walisantri'],
        'navKalender' => ['Ketua Yayasan', 'Sekretaris Yayasan', 'Bendahara Yayasan', 'Kepala Sekolah', 'Kepala Asrama', 'Sekretaris Sekolah', 'Bendahara Sekolah', 'Musyrif', 'Ustadz', 'Santri', 'Walisantri'],
        'navProfil' => ['Ketua Yayasan', 'Sekretaris Yayasan', 'Bendahara Yayasan', 'Kepala Sekolah', 'Kepala Asrama', 'Sekretaris Sekolah', 'Bendahara Sekolah', 'Musyrif', 'Ustadz', 'Santri', 'Walisantri'],

        // --- MENU PEGAWAI ---
        'navAbsensi' => ['Kepala Sekolah', 'Kepala Asrama', 'Sekretaris Sekolah', 'Bendahara Sekolah', 'Musyrif', 'Ustadz'],
        'navBiodataPegawai' => ['Ketua Yayasan', 'Sekretaris Yayasan', 'Bendahara Yayasan', 'Kepala Sekolah', 'Kepala Asrama', 'Sekretaris Sekolah', 'Bendahara Sekolah', 'Musyrif', 'Ustadz'],
        
        // --- MENU RAPAT ---
        // Pembuat Undangan
        'navRapat' => ['Ketua Yayasan', 'Kepala Sekolah', 'Kepala Asrama'], 
        // Melihat Jadwal (Semua Pegawai + Yayasan)
        'navJadwalRapat' => ['Ketua Yayasan', 'Sekretaris Yayasan', 'Bendahara Yayasan', 'Kepala Sekolah', 'Kepala Asrama', 'Sekretaris Sekolah', 'Bendahara Sekolah', 'Musyrif', 'Ustadz'],

        // --- MENU YAYASAN / KHUSUS ---
        'navVerifikasi' => ['Ketua Yayasan', 'Sekretaris Yayasan', 'Bendahara Yayasan'],
        'navQuota' => ['Ketua Yayasan', 'Sekretaris Yayasan', 'Bendahara Yayasan'],
        'navCalendarSettings' => ['Ketua Yayasan', 'Sekretaris Yayasan', 'Bendahara Yayasan'],
        'navMenuManagement' => ['Ketua Yayasan'],

        // --- MENU SANTRI ---
        'navBiodataSantri' => ['Santri', 'Walisantri'], // Walisantri juga bisa akses
        'navIbadahHarian' => ['Santri'], // Hanya santri yang bisa mengisi

        // --- MENU MENTORING (BARU) ---
        'navMentoring' => ['Ketua Yayasan', 'Kepala Sekolah', 'Kepala Asrama', 'Musyrif', 'Ustadz', 'Santri'],

        // --- MENU VALIDASI IBADAH (BARU) ---
        'navValidasiIbadah' => ['Musyrif', 'Kepala Asrama', 'Kepala Sekolah'],
        // Pastikan menu ini ada untuk Musyrif

        // --- MENU WALISANTRI (BARU) ---
        'navRekapIbadahAnak' => ['Walisantri'],
    ];

    // 3. Eksekusi Insert
    $pdo->beginTransaction();
    
    // Bersihkan tabel dulu agar tidak duplikat saat dijalankan ulang
    $pdo->exec("DELETE FROM menu_permissions");

    $stmt = $pdo->prepare("INSERT INTO menu_permissions (role_name, menu_id, can_view) VALUES (?, ?, 1)");

    $count = 0;
    foreach ($permissions as $menuId => $roles) {
        foreach ($roles as $role) {
            $stmt->execute([$role, $menuId]);
            $count++;
        }
    }

    $pdo->commit();
    
    echo "<h1>Sukses!</h1>";
    echo "<p>Tabel 'menu_permissions' berhasil di-reset dan diisi.</p>";
    echo "<p>Total aturan yang ditambahkan: <strong>$count</strong></p>";
    echo "<hr>";
    echo "<h3>Daftar Menu ID yang diatur:</h3><ul style='font-family:monospace'>";
    foreach ($permissions as $menu => $roles) {
        echo "<li><strong>$menu</strong>: " . implode(", ", $roles) . "</li>";
    }
    echo "</ul>";

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo "<h1>Gagal</h1><p>Error: " . $e->getMessage() . "</p>";
}
?>