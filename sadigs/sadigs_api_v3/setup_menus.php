<?php
// File: sadigs_api_v3/setup_menus.php
header('Content-Type: text/html; charset=utf-8');
require_once 'db_connect.php';

try {
    $pdo = getDBConnection();
} catch (Exception $e) {
    die("Gagal koneksi database: " . $e->getMessage());
}

// --- AUTO-MIGRATE SCHEMA (Jaring Pengaman Hosting) ---
try {
    $check = $pdo->query("SHOW COLUMNS FROM menu_permissions LIKE 'can_view'");
    if ($check->rowCount() > 0) {
        $pdo->exec("ALTER TABLE menu_permissions CHANGE COLUMN can_view is_allowed TINYINT(1) DEFAULT 0");
        echo "<p style='color:blue'>ℹ️ Auto-fix: Kolom 'can_view' diperbarui menjadi 'is_allowed'.</p>";
    }
} catch (Exception $e) {
    // Abaikan jika tabel belum ada
}

// --- AUTO-CREATE TABLES (Jaring Pengaman) ---
$pdo->exec("CREATE TABLE IF NOT EXISTS menus (
    menu_id VARCHAR(50) PRIMARY KEY,
    menu_name VARCHAR(100) NOT NULL,
    category_id VARCHAR(50),
    icon VARCHAR(50),
    link VARCHAR(255)
)");

$pdo->exec("CREATE TABLE IF NOT EXISTS menu_permissions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    role_name VARCHAR(50) NOT NULL,
    menu_id VARCHAR(100) NOT NULL,
    is_allowed TINYINT(1) DEFAULT 0,
    UNIQUE KEY unique_perm (role_name, menu_id)
)");

// Daftar Menu Baru yang ingin didaftarkan
$menuDetails = [
    'navFormulirPembayaran' => ['name' => 'Formulir Pembayaran', 'link' => 'payment_form.html', 'icon' => 'credit-card'],
    'navValidasiPembayaran' => ['name' => 'Validasi Pembayaran', 'link' => 'payment_validation.html', 'icon' => 'check-circle-2'],
    'navTabelPembayaran' => ['name' => 'Data Pembayaran', 'link' => 'payment_list.html', 'icon' => 'banknote'],
    'navRekapPembayaran' => ['name' => 'Rekap Keuangan', 'link' => 'payment_recap.html', 'icon' => 'pie-chart'],
    // Menu Tahfidz Baru
    'navInputTahfizh' => ['name' => 'Input Tahfidz', 'link' => 'tahfizh_report_form.html', 'icon' => 'book-open'],
    'navViewTahfizh' => ['name' => 'Riwayat Tahfidz', 'link' => 'tahfizh_history.html', 'icon' => 'history'],
    'navRekapTahfizh' => ['name' => 'Rekap Grafik Tahfidz', 'link' => 'tahfizh_recap.html', 'icon' => 'bar-chart-2'],
    // Menu Notifikasi Baru
    'navKirimNotifikasi' => ['name' => 'Kirim Notifikasi', 'link' => 'admin_notifications.html', 'icon' => 'send'],
    'navLihatNotifikasi' => ['name' => 'Notifikasi Saya', 'link' => 'my_notifications.html', 'icon' => 'bell'],
    // Menu Akademik & Santri (Tambahan)
    'navBiodataSantri' => ['name' => 'Biodata Santri', 'link' => 'student_data.html', 'icon' => 'book-user'],
    'navBukuIndukSantri' => ['name' => 'Buku Induk Santri', 'link' => 'student_master_book.html', 'icon' => 'book'],
    'navManajemenKelas' => ['name' => 'Manajemen Kelas', 'link' => 'class_management.html', 'icon' => 'users'],
    'navInputNilai' => ['name' => 'Input Nilai Rapot', 'link' => 'input_grades.html', 'icon' => 'graduation-cap'],
    'navMonitoringAkademik' => ['name' => 'Monitoring Akademik', 'link' => 'academic_monitoring.html', 'icon' => 'monitor-check']
];

// Ambil semua role yang ada di sistem
$stmt = $pdo->query("SELECT DISTINCT role_name FROM user_roles");
$roles = $stmt->fetchAll(PDO::FETCH_COLUMN);

// Tambahkan role default standar untuk memastikan semua tercover
$defaultRoles = ['Ketua Yayasan', 'Kepala Sekolah', 'Bendahara Sekolah', 'Bendahara Yayasan', 'Walisantri', 'Musyrif', 'Santri', 'Sekretaris Yayasan'];
$allRoles = array_unique(array_merge($roles, $defaultRoles));

echo "<h3>Setup Menu Baru SADIGS</h3>";
echo "<p>Memproses pendaftaran menu ke database...</p>";
echo "<ul>";

// 1. Pastikan Menu Terdaftar di Tabel 'menus'
foreach ($menuDetails as $id => $detail) {
    // Gunakan ON DUPLICATE KEY UPDATE untuk memperbaiki link/icon jika sebelumnya kosong
    $stmt = $pdo->prepare("INSERT INTO menus (menu_id, menu_name, link, icon) VALUES (?, ?, ?, ?) ON DUPLICATE KEY UPDATE menu_name = VALUES(menu_name), link = VALUES(link), icon = VALUES(icon)");
    $stmt->execute([$id, $detail['name'], $detail['link'], $detail['icon']]);
}

$count = 0;

foreach ($allRoles as $role) {
    foreach (array_keys($menuDetails) as $menuId) {
        // 1. Cek apakah kombinasi Role + Menu sudah ada
        $check = $pdo->prepare("SELECT COUNT(*) FROM menu_permissions WHERE role_name = ? AND menu_id = ?");
        $check->execute([$role, $menuId]);
        $exists = $check->fetchColumn();
        
        if ($exists == 0) {
            // 2. Jika belum ada, Insert
            // Default: Hanya Ketua Yayasan yang otomatis aktif (1), lainnya non-aktif (0)
            $canView = ($role === 'Ketua Yayasan') ? 1 : 0;

            $insert = $pdo->prepare("INSERT INTO menu_permissions (role_name, menu_id, is_allowed) VALUES (?, ?, ?)");
            $insert->execute([$role, $menuId, $canView]);
            
            echo "<li style='color: green;'>Berhasil menambahkan: <b>$menuId</b> untuk role <b>$role</b> (Akses: $canView)</li>";
            $count++;
        }
    }
}

// --- FORCE UPDATE ADMIN PERMISSIONS ---
// Pastikan Ketua Yayasan selalu punya akses ke menu baru ini (jika sebelumnya 0)
$adminRole = 'Ketua Yayasan';
foreach (array_keys($menuDetails) as $menuId) {
    $pdo->prepare("UPDATE menu_permissions SET is_allowed = 1 WHERE role_name = ? AND menu_id = ?")->execute([$adminRole, $menuId]);
}
echo "<li><b style='color:blue'>Info:</b> Hak akses Ketua Yayasan untuk menu baru telah dipastikan AKTIF.</li>";

// --- FORCE UPDATE PERMISSIONS FOR TAHFIDZ (SANTRI & WALISANTRI) ---
// Agar otomatis aktif tanpa perlu setting manual di Manajemen Akses
$tahfidzPerms = [
    'navViewTahfizh' => ['Walisantri', 'Santri', 'Santri Rijal', "Santri Nisa'", 'Musyrif', 'Musyrifah'],
    'navInputTahfizh' => ['Musyrif', 'Musyrifah', 'Ustadz', 'Ustadzah'],
    'navRekapTahfizh' => ['Ketua Yayasan', 'Kepala Sekolah', 'Kepala Asrama Putra', 'Kepala Asrama Putri']
];

foreach ($tahfidzPerms as $mId => $rList) {
    foreach ($rList as $rName) {
        // Gunakan INSERT ... ON DUPLICATE KEY UPDATE untuk memastikan permission aktif (1)
        $pdo->prepare("INSERT INTO menu_permissions (role_name, menu_id, is_allowed) VALUES (?, ?, 1) ON DUPLICATE KEY UPDATE is_allowed = 1")
            ->execute([$rName, $mId]);
    }
}
echo "<li><b style='color:blue'>Info:</b> Hak akses Tahfidz untuk Santri, Walisantri, dan Musyrif telah diaktifkan otomatis.</li>";

// --- FORCE UPDATE PERMISSIONS FOR NOTIFICATIONS ---
$notifPerms = [
    'navKirimNotifikasi' => ['Ketua Yayasan', 'Sekretaris Yayasan', 'Bendahara Yayasan', 'Kepala Sekolah', 'Admin Sekolah'],
    'navLihatNotifikasi' => $allRoles // Semua role bisa lihat notifikasi
];
foreach ($notifPerms as $mId => $rList) {
    foreach ($rList as $rName) {
        $pdo->prepare("INSERT INTO menu_permissions (role_name, menu_id, is_allowed) VALUES (?, ?, 1) ON DUPLICATE KEY UPDATE is_allowed = 1")->execute([$rName, $mId]);
    }
}
echo "<li><b style='color:blue'>Info:</b> Hak akses Notifikasi telah diaktifkan otomatis.</li>";

// --- FORCE UPDATE PERMISSIONS FOR ACADEMIC ---
$academicPerms = [
    'navManajemenKelas' => ['Ketua Yayasan', 'Kepala Sekolah', 'Admin Sekolah'],
    'navMonitoringAkademik' => ['Ketua Yayasan', 'Kepala Sekolah', 'Admin Sekolah'],
    'navBukuIndukSantri' => ['Ketua Yayasan', 'Kepala Sekolah', 'Admin Sekolah', 'Bendahara Sekolah']
];
foreach ($academicPerms as $mId => $rList) {
    foreach ($rList as $rName) {
        $pdo->prepare("INSERT INTO menu_permissions (role_name, menu_id, is_allowed) VALUES (?, ?, 1) ON DUPLICATE KEY UPDATE is_allowed = 1")->execute([$rName, $mId]);
    }
}

echo "</ul>";

if ($count > 0) {
    echo "<h4>Selesai! $count izin menu berhasil ditambahkan.</h4>";
} else {
    echo "<h4>Semua menu sudah terdaftar sebelumnya. Tidak ada perubahan.</h4>";
}

echo "<p>Silakan kembali ke <a href='../menu_management.html'>Manajemen Akses</a> untuk mengatur siapa yang boleh melihat menu ini.</p>";
?>