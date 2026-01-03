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
    $start_ganjil = $events['awal_semester_1']['start_date'] ?? null;
    $end_ganjil = $events['terima_raport_1']['end_date'] ?? $events['terima_raport_1']['start_date'] ?? null;
    $start_genap = $events['awal_semester_2']['start_date'] ?? null;
    $end_genap = $events['terima_raport_2']['end_date'] ?? $events['terima_raport_2']['start_date'] ?? null;

    if (!$start_ganjil || !$end_ganjil || !$start_genap || !$end_genap) {
        throw new Exception("Batas awal dan akhir semester Ganjil & Genap belum diatur di Kalender Pendidikan.");
    }

    // Identify all non-effective event keys
    $non_effective_keys = [
        'mos', // Masa Orientasi
        'uts_1', // Ujian Tengah Semester Ganjil
        'uas_1', // Ujian Akhir Semester Ganjil
        'pas_1', // Penilaian Akhir Semester Ganjil
        'class_meeting_1', // Pekan Prestasi Ganjil
        'libur_semester_1', // Libur Semester 1
        'ldks', // LDKS
        'uts_2', // Ujian Tengah Semester Genap
        'uas_2', // Ujian Akhir Semester Genap
        'pas_2', // Penilaian Akhir Semester Genap
        'class_meeting_2', // Pekan Prestasi Genap
        'libur_semester_2', // Libur Semester 2
        'hut_ri', // HUT RI
        'maulid_nabi', // Maulid Nabi
        'isra_miraj', // Isra' Mi'raj
        'idul_fitri', // Idul Fitri
        'idul_adha', // Idul Adha
        'tahun_baru_hijriyah', // Tahun Baru Hijriyah
        'libur_ramadhan', // Libur Awal Ramadhan
        'libur_syawal' // Libur Hari Raya
    ];

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