<?php
header('Content-Type: text/html; charset=utf-8');
require_once 'db_connect.php';

echo "<!DOCTYPE html><html><head><title>Setup Batas Semester</title><style>body{font-family: sans-serif; padding: 20px; line-height: 1.6;} h1{color: #26667F;} .ok{color:green;}</style></head><body>";
echo "<h1>Setup Batas Semester Default</h1>";

try {
    $pdo = getDBConnection();
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Menentukan tahun ajaran saat ini
    $current_year = date('Y');
    $next_year = $current_year + 1;

    $semester_boundaries = [
        'start_ganjil' => ['start' => "$current_year-07-01", 'end' => null],
        'end_ganjil'   => ['start' => null, 'end' => "$current_year-12-31"],
        'start_genap'  => ['start' => "$next_year-01-01", 'end' => null],
        'end_genap'    => ['start' => null, 'end' => "$next_year-06-30"],
    ];

    $sql = "INSERT INTO academic_calendar (event_key, start_date, end_date) VALUES (:key, :start, :end)
            ON DUPLICATE KEY UPDATE start_date = VALUES(start_date), end_date = VALUES(end_date)";
    $stmt = $pdo->prepare($sql);

    foreach ($semester_boundaries as $key => $dates) {
        $stmt->execute(['key' => $key, 'start' => $dates['start'], 'end' => $dates['end']]);
        echo "<p class='ok'>✅ Batas semester '{$key}' berhasil diatur.</p>";
    }

    echo "<h3>Setup Selesai.</h3><p>Silakan buka kembali menu 'Perencanaan Akademik' untuk melihat hasilnya.</p>";

} catch (Exception $e) {
    echo "<h2>Error</h2><p style='color:red;'>Gagal menjalankan script: " . $e->getMessage() . "</p>";
}
echo "</body></html>";
?>