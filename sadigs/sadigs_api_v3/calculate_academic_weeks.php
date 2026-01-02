<?php
header('Content-Type: application/json');
require_once 'db_connect.php';

try {
    $pdo = getDBConnection();
    $stmt = $pdo->query("SELECT event_key, start_date, end_date FROM academic_calendar");
    $events_raw = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $events = [];
    foreach ($events_raw as $event) {
        $events[$event['event_key']] = $event;
    }

    // Get semester boundaries
    $start_ganjil = $events['start_ganjil']['start_date'] ?? null;
    $end_ganjil = $events['end_ganjil']['end_date'] ?? null;
    $start_genap = $events['start_genap']['start_date'] ?? null;
    $end_genap = $events['end_genap']['end_date'] ?? null;

    if (!$start_ganjil || !$end_ganjil || !$start_genap || !$end_genap) {
        throw new Exception("Batas awal dan akhir semester Ganjil & Genap belum diatur di Kalender Pendidikan.");
    }

    // Identify all non-effective event keys
    $non_effective_keys = [
        'libur_awal_puasa', 'libur_idul_fitri', 'libur_semester_ganjil',
        'libur_semester_genap', 'uts_ganjil', 'uas_ganjil', 'uts_genap', 'uas_genap'
    ];
    for ($i = 1; $i <= 10; $i++) { $non_effective_keys[] = "libur_nasional_$i"; }

    $non_effective_periods = [];
    foreach ($non_effective_keys as $key) {
        if (isset($events[$key])) {
            $event = $events[$key];
            if (!empty($event['start_date'])) {
                 $non_effective_periods[] = [
                    'start' => new DateTime($event['start_date']),
                    'end' => new DateTime($event['end_date'] ?: $event['start_date'])
                ];
            }
        }
    }

    // Function to calculate weeks
    function calculate_semester_weeks($start_date_str, $end_date_str, $non_effective_periods) {
        $start = new DateTime($start_date_str);
        $end = new DateTime($end_date_str);
        
        $total_weeks = 0;
        $non_effective_weeks = 0;
        
        $current = clone $start;
        $current->modify('monday this week');

        while ($current <= $end) {
            $total_weeks++;
            $week_start = clone $current;
            $week_end = (clone $current)->modify('+6 days');

            foreach ($non_effective_periods as $period) {
                // Cek jika pekan ini tumpang tindih dengan periode tidak efektif
                if ($week_start <= $period['end'] && $week_end >= $period['start']) {
                    $non_effective_weeks++;
                    break; 
                }
            }
            $current->modify('+1 week');
        }

        return [
            'total_weeks' => $total_weeks,
            'non_effective_weeks' => $non_effective_weeks,
            'effective_weeks' => $total_weeks - $non_effective_weeks
        ];
    }

    $ganjil_result = calculate_semester_weeks($start_ganjil, $end_ganjil, $non_effective_periods);
    $genap_result = calculate_semester_weeks($start_genap, $end_genap, $non_effective_periods);

    sendJSONResponse(['success' => true, 'data' => ['ganjil' => $ganjil_result, 'genap' => $genap_result]]);

} catch (Exception $e) {
    sendJSONResponse(['success' => false, 'message' => $e->getMessage()], 500);
}
?>