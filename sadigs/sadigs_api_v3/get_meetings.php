<?php
require_once '../db_connect.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id'])) {
    sendJSONResponse(['success' => false, 'message' => 'Unauthorized'], 401);
}

$pdo = getDBConnection();
$user_roles = $_SESSION['roles'] ?? [];

try {
    // Ambil rapat:
    // 1. Rutinitas 'sekali' yang terjadi di bulan & tahun ini
    // 2. Rutinitas 'setiap_pekan' atau 'setiap_bulan' (selalu relevan)
    $sql = "SELECT * FROM meetings 
            WHERE (routine = 'sekali' AND MONTH(meeting_date) = MONTH(CURRENT_DATE()) AND YEAR(meeting_date) = YEAR(CURRENT_DATE()))
            OR routine IN ('setiap_pekan', 'setiap_bulan')
            ORDER BY meeting_date ASC, meeting_time ASC";
    
    $stmt = $pdo->query($sql);
    $all_meetings = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $filtered_meetings = [];
    
    foreach ($all_meetings as $meeting) {
        // Decode invited_roles
        $invited_roles = json_decode($meeting['invited_roles'] ?? '[]', true);
        if (!is_array($invited_roles)) $invited_roles = [];
        
        // Cek apakah user memiliki salah satu peran yang diundang
        $has_access = false;
        foreach ($user_roles as $role) {
            if (in_array($role, $invited_roles)) {
                $has_access = true;
                break;
            }
        }
        
        // Pengundang juga bisa melihat (opsional, tapi logis)
        if (in_array($meeting['inviter'], $user_roles)) {
            $has_access = true;
        }

        if ($has_access) {
            // Format Tanggal untuk tampilan
            if ($meeting['routine'] === 'setiap_pekan') {
                $meeting['display_date'] = 'Setiap Pekan';
            } elseif ($meeting['routine'] === 'setiap_bulan') {
                $d = date('d', strtotime($meeting['meeting_date']));
                $meeting['display_date'] = "Setiap tanggal $d";
            } else {
                $meeting['display_date'] = date('d M Y', strtotime($meeting['meeting_date']));
            }
            $filtered_meetings[] = $meeting;
        }
    }
    
    sendJSONResponse(['success' => true, 'meetings' => $filtered_meetings]);

} catch (Exception $e) {
    sendJSONResponse(['success' => false, 'message' => $e->getMessage()], 500);
}
?>