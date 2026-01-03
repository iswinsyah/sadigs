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

    // Helper function to find date from multiple possible keys (New & Old compatibility)
    function find_date($events, $keys, $type = 'start') {
        foreach ($keys as $key) {
            if (isset($events[$key])) {
                if ($type === 'start' && !empty($events[$key]['start_date'])) return $events[$key]['start_date'];
                if ($type === 'end') {
                    if (!empty($events[$key]['end_date'])) return $events[$key]['end_date'];
                    if (!empty($events[$key]['start_date'])) return $events[$key]['start_date']; // Fallback
                }
            }
        }
        return null;
    }

    // Get semester boundaries with fallback to old keys
    $start_ganjil = find_date($events, ['awal_semester_1', 'start_ganjil'], 'start');
    $end_ganjil   = find_date($events, ['terima_raport_1', 'end_ganjil', 'raport_semester_ganjil'], 'end');
    $start_genap  = find_date($events, ['awal_semester_2', 'start_genap'], 'start');
    $end_genap    = find_date($events, ['terima_raport_2', 'end_genap', 'raport_semester_genap'], 'end');

    // Specific error reporting
    if (!$start_ganjil) throw new Exception("Tanggal 'Awal Semester Ganjil' belum diatur.");
    if (!$end_ganjil) throw new Exception("Tanggal 'Terima Raport Ganjil' belum diatur.");
    if (!$start_genap) throw new Exception("Tanggal 'Awal Semester Genap' belum diatur.");
    if (!$end_genap) throw new Exception("Tanggal 'Terima Raport Genap' belum diatur.");

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
        'libur_syawal', // Libur Hari Raya
        // Compatibility with old keys
        'uts_ganjil', 'uas_ganjil', 'uts_genap', 'uas_genap', 'libur_semester_ganjil', 'libur_semester_genap'
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
        $monthly_breakdown = [];
        
        $current = clone $start;
        $current->modify('monday this week');

        while ($current <= $end) {
            $total_weeks++;
            $week_start = clone $current;
            $week_end = (clone $current)->modify('+6 days');

            // Dapatkan bulan untuk pekan ini (berdasarkan hari Senin)
            $month_of_week = (int)$current->format('n'); // 1-12
            if (!isset($monthly_breakdown[$month_of_week])) {
                $monthly_breakdown[$month_of_week] = ['total' => 0, 'non_effective' => 0];
            }
            $monthly_breakdown[$month_of_week]['total']++;

            foreach ($non_effective_periods as $period) {
                // Cek jika pekan ini tumpang tindih dengan periode tidak efektif
                if ($week_start <= $period['end'] && $week_end >= $period['start']) {
                    $non_effective_weeks++;
                    $monthly_breakdown[$month_of_week]['non_effective']++;
                    break; 
                }
            }
            $current->modify('+1 week');
        }

        // Tambahkan pekan efektif ke rincian bulanan
        foreach ($monthly_breakdown as $month => &$data) {
            $data['effective'] = $data['total'] - $data['non_effective'];
        }

        return [
            'total_weeks' => $total_weeks,
            'non_effective_weeks' => $non_effective_weeks,
            'effective_weeks' => $total_weeks - $non_effective_weeks,
            'monthly_breakdown' => $monthly_breakdown
        ];
    }

    $ganjil_result = calculate_semester_weeks($start_ganjil, $end_ganjil, $non_effective_periods);
    $genap_result = calculate_semester_weeks($start_genap, $end_genap, $non_effective_periods);

    sendJSONResponse(['success' => true, 'data' => ['ganjil' => $ganjil_result, 'genap' => $genap_result]]);

} catch (Exception $e) {
    sendJSONResponse(['success' => false, 'message' => $e->getMessage()], 500);
}
?>