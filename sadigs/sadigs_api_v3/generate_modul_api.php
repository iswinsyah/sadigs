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
$model = $input['model'] ?? '';
$time = $input['time'] ?? '';

if (!$subject || !$topic) {
    sendJSONResponse(['success' => false, 'message' => 'Data tidak lengkap.'], 400);
}

// --- FUNGSI AI GEMINI ---
function generateModulWithGemini($subject, $grade, $fase, $topic, $tp, $model, $time) {
    require 'ai_config.php';
    if (empty($apiKey)) return "Simulasi: Modul Ajar untuk $topic ($subject Kelas $grade) berhasil dibuat.\n\n(Harap pasang API Key Anda di dalam file 'sadigs_api_v3/ai_config.php' untuk hasil AI sungguhan)";

    $url = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-pro:generateContent?key=' . $apiKey;
    
    $prompt = "Buatkan Modul Ajar Lengkap (RPP Plus) untuk mata pelajaran '$subject' Kelas $grade (Fase $fase). \n" .
              "Topik: $topic \n" .
              "Tujuan Pembelajaran (TP): $tp \n" .
              "Model Pembelajaran: $model \n" .
              "Alokasi Waktu: $time \n\n" .
              "Struktur Modul Ajar harus mencakup:\n" .
              "1. Informasi Umum (Identitas, Kompetensi Awal, Profil Pelajar Pancasila, Sarana Prasarana, Target Peserta Didik)\n" .
              "2. Komponen Inti (Tujuan Pembelajaran, Pemahaman Bermakna, Pertanyaan Pemantik, Kegiatan Pembelajaran Pendahuluan-Inti-Penutup)\n" .
              "3. Asesmen (Formatif & Sumatif)\n" .
              "4. Lampiran (LKPD singkat, Bahan Bacaan, Glosarium, Daftar Pustaka)\n\n" .
              "Gunakan bahasa Indonesia yang formal dan operasional.";

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
    $text = $result['candidates'][0]['content']['parts'][0]['text'] ?? 'Maaf, AI tidak memberikan respons. Cek kuota atau API Key Anda.';
    return $text;
}
try {
    $result = generateModulWithGemini($subject, $grade, $fase, $topic, $tp, $model, $time);
    sendJSONResponse(['success' => true, 'data' => $result]);
} catch (Exception $e) {
    sendJSONResponse(['success' => false, 'message' => $e->getMessage()], 500);
}
?>