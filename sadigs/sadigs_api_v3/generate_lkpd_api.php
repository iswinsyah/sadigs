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
$activity_type = $input['activity_type'] ?? 'Diskusi Kelompok';

if (!$subject || !$topic) {
    sendJSONResponse(['success' => false, 'message' => 'Data tidak lengkap.'], 400);
}

function generateLKPDWithGemini($subject, $grade, $fase, $topic, $tp, $activity_type) {
    $apiKey = ''; // Masukkan API Key Gemini Anda di sini
    if (empty($apiKey)) return "Simulasi: LKPD untuk $topic ($subject Kelas $grade) berhasil dibuat.\n\n(Harap pasang API Key di file generate_lkpd_api.php untuk hasil AI sungguhan)";

    $url = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-pro:generateContent?key=' . $apiKey;
    
    $prompt = "Buatkan Lembar Kerja Peserta Didik (LKPD) untuk mata pelajaran '$subject' Kelas $grade (Fase $fase). \n" .
              "Topik: $topic \n" .
              "Tujuan Pembelajaran (TP): $tp \n" .
              "Jenis Aktivitas: $activity_type \n\n" .
              "Struktur LKPD harus mencakup:\n" .
              "1. Identitas (Nama Kelompok, Kelas, Tanggal)\n" .
              "2. Judul Aktivitas\n" .
              "3. Tujuan Aktivitas\n" .
              "4. Alat dan Bahan (jika ada)\n" .
              "5. Langkah-Langkah Kegiatan (Instruksi jelas)\n" .
              "6. Pertanyaan Diskusi / Isian / Tugas\n" .
              "7. Kesimpulan\n\n" .
              "Gunakan bahasa yang mudah dipahami siswa.";

    $data = ['contents' => [['parts' => [['text' => $prompt]]]]];
    
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    
    $response = curl_exec($ch);
    if (curl_errno($ch)) return 'Error koneksi AI: ' . curl_error($ch);
    curl_close($ch);
    
    $result = json_decode($response, true);
    return $result['candidates'][0]['content']['parts'][0]['text'] ?? 'Maaf, AI tidak memberikan respons.';
}

$result = generateLKPDWithGemini($subject, $grade, $fase, $topic, $tp, $activity_type);
sendJSONResponse(['success' => true, 'data' => $result]);
?>