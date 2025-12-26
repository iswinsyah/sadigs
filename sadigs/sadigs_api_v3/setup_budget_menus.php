<?php
require_once 'db_connect.php';

try {
    $pdo = getDBConnection();
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    echo "<h1>Setup Menu Anggaran Operasional</h1>";

    $menus = [
        'navFormAnggaran' => 'Form Anggaran',
        'navValidasiAnggaran' => 'Validasi Anggaran',
        'navRekapAnggaran' => 'Rekap Anggaran'
    ];

    $perms = [
        'navFormAnggaran' => ['Kepala Sekolah', 'Kepala Asrama Putra', 'Kepala Asrama Putri', 'Bendahara Sekolah', 'Ustadz', 'Musyrif'], // Semua pegawai bisa mengajukan
        'navValidasiAnggaran' => ['Kepala Sekolah', 'Kepala Asrama Putra', 'Kepala Asrama Putri'],
        'navRekapAnggaran' => ['Ketua Yayasan', 'Bendahara Yayasan', 'Sekretaris Yayasan']
    ];

    foreach ($menus as $id => $name) {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM menus WHERE menu_id = ?");
        $stmt->execute([$id]);
        if ($stmt->fetchColumn() == 0) {
            $pdo->prepare("INSERT INTO menus (menu_id, menu_name) VALUES (?, ?)")->execute([$id, $name]);
            echo "<p>✅ Menu '$name' ditambahkan.</p>";
        }

        if (isset($perms[$id])) {
            $stmtP = $pdo->prepare("INSERT IGNORE INTO menu_permissions (menu_id, role_name, can_view) VALUES (?, ?, 1)");
            foreach ($perms[$id] as $role) {
                $stmtP->execute([$id, $role]);
            }
        }
    }
    echo "<h3>Selesai.</h3>";

} catch(PDOException $e) {
    die("ERROR: " . $e->getMessage());
}
?>