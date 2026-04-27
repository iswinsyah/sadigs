<?php
require_once 'db_connect.php';

try {
    $pdo = getDBConnection();
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    echo "<h1>Setup Menu Jadwal Pelajaran</h1>";

    $menus = [
        'navKetersediaanMengajar' => 'Ketersediaan Mengajar',
        'navAturKurikulum' => 'Atur Kurikulum'
    ];

    $perms = [
        'navKetersediaanMengajar' => ['Ustadz', 'Ustadzah', 'Kepala Sekolah', 'Musyrif', 'Musyrifah'],
        'navAturKurikulum' => ['Kepala Sekolah', 'Ketua Yayasan', 'Sekretaris Sekolah']
    ];

    foreach ($menus as $id => $name) {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM menus WHERE menu_id = ?");
        $stmt->execute([$id]);
        if ($stmt->fetchColumn() == 0) {
            $pdo->prepare("INSERT INTO menus (menu_id, menu_name) VALUES (?, ?)")->execute([$id, $name]);
            echo "<p>✅ Menu '$name' ditambahkan.</p>";
        }

        if (isset($perms[$id])) {
            $stmtP = $pdo->prepare("INSERT IGNORE INTO menu_permissions (menu_id, role_name, is_allowed) VALUES (?, ?, 1)");
            foreach ($perms[$id] as $role) {
                $stmtP->execute([$id, $role]);
            }
        }
    }
    echo "<h3>Selesai.</h3>";
    echo "<p>Silakan refresh Dashboard.</p>";

} catch(PDOException $e) {
    die("ERROR: " . $e->getMessage());
}
?>