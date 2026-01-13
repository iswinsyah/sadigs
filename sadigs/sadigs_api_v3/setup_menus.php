<?php
// File: sadigs_api_v3/setup_menus.php
header('Content-Type: text/html; charset=utf-8');
require_once 'db_connect.php';

try {
    $pdo = getDBConnection();
} catch (Exception $e) {
    die("Gagal koneksi database: " . $e->getMessage());
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
    'navFormulirPembayaran' => 'Formulir Pembayaran',
    'navValidasiPembayaran' => 'Validasi Pembayaran',
    'navTabelPembayaran' => 'Data Pembayaran',
    'navRekapPembayaran' => 'Rekap Keuangan',
    // Menu Tahfidz Baru
    'navInputTahfizh' => 'Input Tahfidz',
    'navViewTahfizh' => 'Riwayat Tahfidz',
    'navRekapTahfizh' => 'Rekap Grafik Tahfidz'
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
foreach ($menuDetails as $id => $name) {
    $stmt = $pdo->prepare("INSERT IGNORE INTO menus (menu_id, menu_name) VALUES (?, ?)");
    $stmt->execute([$id, $name]);
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

echo "</ul>";

if ($count > 0) {
    echo "<h4>Selesai! $count izin menu berhasil ditambahkan.</h4>";
} else {
    echo "<h4>Semua menu sudah terdaftar sebelumnya. Tidak ada perubahan.</h4>";
}

echo "<p>Silakan kembali ke <a href='../menu_management.html'>Manajemen Akses</a> untuk mengatur siapa yang boleh melihat menu ini.</p>";
?>