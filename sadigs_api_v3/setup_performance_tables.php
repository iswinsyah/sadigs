<?php
require_once 'db_connect.php';

try {
    $pdo = getDBConnection();
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    echo "<h1>Setup Tabel Penilaian Kinerja</h1>";

    // 1. Tabel Periode Penilaian (Bulanan)
    $pdo->exec("CREATE TABLE IF NOT EXISTS performance_periods (
        id INT AUTO_INCREMENT PRIMARY KEY,
        period_name VARCHAR(50) NOT NULL, -- Contoh: 'Januari 2025'
        start_date DATE NOT NULL,
        end_date DATE NOT NULL,
        is_active BOOLEAN DEFAULT 1,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");

    // 2. Tabel Indikator Kinerja (KPI)
    $pdo->exec("CREATE TABLE IF NOT EXISTS performance_kpi (
        id INT AUTO_INCREMENT PRIMARY KEY,
        role_name VARCHAR(50) NOT NULL, -- Ustadz, Musyrif, dll
        kpi_name VARCHAR(100) NOT NULL,
        kpi_type ENUM('automatic', 'manual') NOT NULL,
        weight DECIMAL(5,2) NOT NULL, -- Bobot dalam persen (0-100)
        description TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");

    // 3. Tabel Hasil Penilaian
    $pdo->exec("CREATE TABLE IF NOT EXISTS performance_scores (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        period_id INT NOT NULL,
        kpi_id INT NOT NULL,
        score DECIMAL(5,2) DEFAULT 0,
        assessor_id INT NULL, -- Siapa yang menilai (jika manual)
        notes TEXT,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (period_id) REFERENCES performance_periods(id) ON DELETE CASCADE,
        FOREIGN KEY (kpi_id) REFERENCES performance_kpi(id) ON DELETE CASCADE,
        UNIQUE KEY unique_score (user_id, period_id, kpi_id)
    )");

    echo "<p>✅ Tabel database berhasil dibuat.</p>";

    // 4. Seed Default KPI untuk Ustadz
    // Hapus data lama untuk Ustadz agar tidak duplikat saat setup ulang
    $pdo->exec("DELETE FROM performance_kpi WHERE role_name = 'Ustadz'");

    $kpis = [
        ['Ustadz', 'Kedisiplinan Kehadiran', 'automatic', 30, 'Dihitung dari data absensi harian (Hadir/Total Hari Kerja).'],
        ['Ustadz', 'Kelengkapan Administrasi', 'automatic', 30, 'Ketersediaan ATP dan Modul Ajar di sistem.'],
        ['Ustadz', 'Kehadiran Rapat', 'automatic', 10, 'Kehadiran dalam rapat rutin bulanan.'],
        ['Ustadz', 'Kualitas Pengajaran & Akhlak', 'manual', 30, 'Penilaian subjektif Kepala Sekolah (Supervisi).']
    ];

    $stmt = $pdo->prepare("INSERT INTO performance_kpi (role_name, kpi_name, kpi_type, weight, description) VALUES (?, ?, ?, ?, ?)");
    foreach ($kpis as $k) {
        $stmt->execute($k);
    }
    echo "<p>✅ Indikator Penilaian (KPI) untuk Ustadz berhasil di-reset.</p>";

    // 5. Buat Periode Bulan Ini (Jika belum ada)
    $currentMonth = date('F Y'); // e.g., January 2025
    $startDate = date('Y-m-01');
    $endDate = date('Y-m-t');
    
    $stmtCheck = $pdo->prepare("SELECT COUNT(*) FROM performance_periods WHERE period_name = ?");
    $stmtCheck->execute([$currentMonth]);
    if ($stmtCheck->fetchColumn() == 0) {
        $stmtPeriod = $pdo->prepare("INSERT INTO performance_periods (period_name, start_date, end_date) VALUES (?, ?, ?)");
        $stmtPeriod->execute([$currentMonth, $startDate, $endDate]);
        echo "<p>✅ Periode penilaian bulan ini ($currentMonth) berhasil dibuat.</p>";
    }

    echo "<h3>Selesai.</h3>";

} catch (Exception $e) {
    die("Error: " . $e->getMessage());
}
?>