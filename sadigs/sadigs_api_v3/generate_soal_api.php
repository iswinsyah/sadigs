<?php
header('Content-Type: application/json');
require_once 'db_connect.php';

if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['user_id'])) {
    sendJSONResponse(['success' => false, 'message' => 'Unauthorized'], 401);
}

$input = json_decode(file_get_contents('php://input'), true);
$subject = $input['subject'] ?? '';
$grade = $input['grade'] ?? '';
$fase = $input['fase'] ?? '';
$topic = $input['topic'] ?? '';
$tp = $input['tp'] ?? '';
$type = $input['type'] ?? 'Pilihan Ganda';
$count = $input['count'] ?? '5';

if (!$subject || !$topic) {
    sendJSONResponse(['success' => false, 'message' => 'Data tidak lengkap.'], 400);
}

function generateSoalWithGemini($subject, $grade, $fase, $topic, $tp, $type, $count) {
    require 'ai_config.php';
    if (empty($apiKey)) return "Simulasi: $count Soal $type untuk $topic ($subject Kelas $grade) berhasil dibuat.\n\n(Harap pasang API Key Anda di dalam file 'sadigs_api_v3/ai_config.php' untuk hasil AI sungguhan)";

    $url = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-pro:generateContent?key=' . $apiKey;
    
    $prompt = "Buatkan Soal Asesmen untuk mata pelajaran '$subject' Kelas $grade (Fase $fase). \n" .
              "Topik: $topic \n" .
              "Tujuan Pembelajaran (TP): $tp \n" .
              "Jenis Soal: $type \n" .
              "Jumlah Soal: $count butir \n\n" .
              "Instruksi:\n" .
              "1. Sajikan soal dengan jelas dan operasional.\n" .
              "2. Jika Pilihan Ganda, sertakan 5 opsi (A,B,C,D,E) dan Kunci Jawaban di akhir.\n" .
              "3. Jika Essay, sertakan rubrik penilaian singkat atau kunci jawaban.\n" .
              "4. Soal harus mengukur pemahaman siswa terhadap TP di atas.\n\n" .
              "Format Output: Langsung daftar soal dan kunci jawaban.";

    $data = ['contents' => [['parts' => [['text' => $prompt]]]]];
    
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    
    $response = curl_exec($ch);
    if (curl_errno($ch)) {
        $error_msg = curl_error($ch);
        curl_close($ch);
        throw new Exception('Error koneksi ke AI: ' . $error_msg);
    }
    curl_close($ch);
    
    $result = json_decode($response, true);
    return $result['candidates'][0]['content']['parts'][0]['text'] ?? 'Maaf, AI tidak memberikan respons.';
}

try {
    $result = generateSoalWithGemini($subject, $grade, $fase, $topic, $tp, $type, $count);
    sendJSONResponse(['success' => true, 'data' => $result]);
} catch (Exception $e) {
    sendJSONResponse(['success' => false, 'message' => $e->getMessage()], 500);
}
?>