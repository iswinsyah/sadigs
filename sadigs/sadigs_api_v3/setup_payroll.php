<?php
header('Content-Type: text/html; charset=utf-8');
require_once 'db_connect.php';

try {
    $pdo = getDBConnection();
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    echo "<h1>Setup Database Penggajian (Payroll)</h1>";

    // 1. Tabel Komponen Gaji (Master Data)
    $pdo->exec("CREATE TABLE IF NOT EXISTS salary_components (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(100) NOT NULL,
        type ENUM('fixed', 'daily', 'shift', 'teaching_hour', 'meeting', 'deduction', 'bonus') NOT NULL,
        is_active BOOLEAN DEFAULT 1
    )");
    echo "<p>✅ Tabel 'salary_components' siap.</p>";

    // 2. Tabel Standar Gaji per Peran (Default Values)
    $pdo->exec("CREATE TABLE IF NOT EXISTS salary_standards (
        id INT AUTO_INCREMENT PRIMARY KEY,
        role_name VARCHAR(50) NOT NULL,
        component_id INT NOT NULL,
        amount DECIMAL(12,2) DEFAULT 0,
        FOREIGN KEY (component_id) REFERENCES salary_components(id) ON DELETE CASCADE
    )");
    echo "<p>✅ Tabel 'salary_standards' siap.</p>";

    // 3. Tabel Aturan Tarif Mengajar (Teaching Rates)
    $pdo->exec("CREATE TABLE IF NOT EXISTS teaching_rate_rules (
        id INT AUTO_INCREMENT PRIMARY KEY,
        role_name VARCHAR(50) NOT NULL,
        tier_1_limit INT DEFAULT 0,
        tier_1_rate DECIMAL(12,2) DEFAULT 0,
        tier_2_rate DECIMAL(12,2) DEFAULT 0,
        UNIQUE KEY unique_role_rate (role_name)
    )");
    echo "<p>✅ Tabel 'teaching_rate_rules' siap.</p>";

    // 4. Tabel Konfigurasi Global (Zona Waktu, dll)
    $pdo->exec("CREATE TABLE IF NOT EXISTS payroll_config (
        config_key VARCHAR(50) PRIMARY KEY,
        config_value DECIMAL(12,2) DEFAULT 0,
        description VARCHAR(255)
    )");
    echo "<p>✅ Tabel 'payroll_config' siap.</p>";

    // 5. Tabel Periode Penggajian
    $pdo->exec("CREATE TABLE IF NOT EXISTS payroll_periods (
        id INT AUTO_INCREMENT PRIMARY KEY,
        period_name VARCHAR(50) NOT NULL,
        start_date DATE NOT NULL,
        end_date DATE NOT NULL,
        status ENUM('draft', 'locked', 'paid') DEFAULT 'draft',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");
    echo "<p>✅ Tabel 'payroll_periods' siap.</p>";

    // 6. Tabel Slip Gaji (Header)
    $pdo->exec("CREATE TABLE IF NOT EXISTS payroll_slips (
        id INT AUTO_INCREMENT PRIMARY KEY,
        period_id INT NOT NULL,
        user_id INT NOT NULL,
        total_income DECIMAL(12,2) DEFAULT 0,
        total_deduction DECIMAL(12,2) DEFAULT 0,
        net_salary DECIMAL(12,2) DEFAULT 0,
        status ENUM('pending', 'paid') DEFAULT 'pending',
        generated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (period_id) REFERENCES payroll_periods(id) ON DELETE CASCADE
    )");
    echo "<p>✅ Tabel 'payroll_slips' siap.</p>";

    // 7. Tabel Detail Slip Gaji (Rincian)
    $pdo->exec("CREATE TABLE IF NOT EXISTS payroll_items (
        id INT AUTO_INCREMENT PRIMARY KEY,
        slip_id INT NOT NULL,
        component_name VARCHAR(100) NOT NULL,
        amount DECIMAL(12,2) NOT NULL,
        qty INT DEFAULT 1,
        note TEXT,
        type ENUM('income', 'deduction') NOT NULL,
        FOREIGN KEY (slip_id) REFERENCES payroll_slips(id) ON DELETE CASCADE
    )");
    echo "<p>✅ Tabel 'payroll_items' siap.</p>";

    // --- SEEDING DATA AWAL ---
    $components = [
        ['Gaji Pokok', 'fixed'],
        ['Tunjangan Jabatan', 'fixed'],
        ['Uang Makan/Transport Harian', 'daily'],
        ['Insentif Piket Ahad', 'shift'],
        ['Insentif Rapat', 'meeting'],
        ['Honor Mengajar', 'teaching_hour'],
        ['Potongan Keterlambatan', 'deduction']
    ];
    
    $stmt = $pdo->prepare("INSERT IGNORE INTO salary_components (name, type) VALUES (?, ?)");
    foreach ($components as $c) { $stmt->execute($c); }

    // Seed Default Config
    $defaults = [
        'reward_zona_hijau' => 20000,
        'reward_zona_kuning' => 15000,
        'reward_zona_oranye' => 10000,
        'reward_zona_merah' => 5000,
        'insentif_rapat' => 40000,
        'insentif_piket' => 50000
    ];
    $stmtConfig = $pdo->prepare("INSERT IGNORE INTO payroll_config (config_key, config_value) VALUES (?, ?)");
    foreach ($defaults as $key => $val) { $stmtConfig->execute([$key, $val]); }
    
    echo "<h3>Selesai! Database penggajian telah terbentuk.</h3>";

} catch (Exception $e) {
    die("Error: " . $e->getMessage());
}
?>