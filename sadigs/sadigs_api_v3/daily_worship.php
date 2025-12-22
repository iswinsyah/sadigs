<?php
header('Content-Type: application/json');
require_once 'db_connect.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id'])) {
    sendJSONResponse(['success' => false, 'message' => 'Unauthorized'], 401);
}

$pdo = getDBConnection();
$user_id = $_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $report_date = $_GET['date'] ?? date('Y-m-d');

    try {
        $stmt = $pdo->prepare("SELECT * FROM ibadah_harian WHERE user_id = ? AND report_date = ?");
        $stmt->execute([$user_id, $report_date]);
        $data = $stmt->fetch(PDO::FETCH_ASSOC);

        sendJSONResponse(['success' => true, 'data' => $data]);
    } catch (Exception $e) {
        sendJSONResponse(['success' => false, 'message' => $e->getMessage()], 500);
    }
}

elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    
    try {
        $sql = "INSERT INTO ibadah_harian (
                    user_id, report_date, shalat_subuh, shalat_dzuhur, shalat_ashar, shalat_maghrib, shalat_isya,
                    shalat_tahajud, shalat_dhuha, baca_quran, juz_quran, surat_quran, ayat_quran, infaq, notes
                ) VALUES (
                    :uid, :r_date, :s_subuh, :s_dzuhur, :s_ashar, :s_maghrib, :s_isya,
                    :s_tahajud, :s_dhuha, :b_quran, :j_quran, :surat_quran, :ayat_quran, :infaq, :notes
                )
                ON DUPLICATE KEY UPDATE
                    shalat_subuh = VALUES(shalat_subuh), shalat_dzuhur = VALUES(shalat_dzuhur), shalat_ashar = VALUES(shalat_ashar),
                    shalat_maghrib = VALUES(shalat_maghrib), shalat_isya = VALUES(shalat_isya), shalat_tahajud = VALUES(shalat_tahajud),
                    shalat_dhuha = VALUES(shalat_dhuha), baca_quran = VALUES(baca_quran), juz_quran = VALUES(juz_quran),
                    surat_quran = VALUES(surat_quran), ayat_quran = VALUES(ayat_quran), infaq = VALUES(infaq), notes = VALUES(notes)";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            'uid' => $user_id,
            'r_date' => $input['report_date'],
            's_subuh' => $input['shalat_subuh'] ?? 0,
            's_dzuhur' => $input['shalat_dzuhur'] ?? 0,
            's_ashar' => $input['shalat_ashar'] ?? 0,
            's_maghrib' => $input['shalat_maghrib'] ?? 0,
            's_isya' => $input['shalat_isya'] ?? 0,
            's_tahajud' => $input['shalat_tahajud'] ?? 0,
            's_dhuha' => $input['shalat_dhuha'] ?? 0,
            'b_quran' => $input['baca_quran'] ?? 0,
            'j_quran' => !empty($input['juz_quran']) ? $input['juz_quran'] : null,
            'surat_quran' => $input['surat_quran'] ?? null,
            'ayat_quran' => $input['ayat_quran'] ?? null,
            'infaq' => $input['infaq'] ?? 0,
            'notes' => $input['notes'] ?? null
        ]);

        sendJSONResponse(['success' => true, 'message' => 'Laporan ibadah harian berhasil disimpan.']);
    } catch (Exception $e) {
        sendJSONResponse(['success' => false, 'message' => 'Gagal menyimpan: ' . $e->getMessage()], 500);
    }
}
?>