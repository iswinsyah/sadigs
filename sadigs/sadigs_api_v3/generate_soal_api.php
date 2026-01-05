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
    $configPath = __DIR__ . '/ai_config.php';
    if (!file_exists($configPath)) {
        throw new Exception("DEBUG: File 'ai_config.php' TIDAK DITEMUKAN di server. Pastikan file tersebut sudah di-upload ke folder 'sadigs_api_v3'.");
    }
    require $configPath;

    if (!isset($apiKey)) {
        throw new Exception("DEBUG: Variabel \$apiKey TIDAK DITEMUKAN setelah memuat 'ai_config.php'. Cek isi file tersebut, pastikan ada baris `\$apiKey = '...';`");
    }

    if (empty(trim($apiKey))) {
        throw new Exception("DEBUG: API Key di 'ai_config.php' terdeteksi KOSONG. Harap isi dengan API Key dari Google AI Studio.");
    }

    if (strlen(trim($apiKey)) < 30) {
        throw new Exception("DEBUG: API Key terdeteksi, tapi TERLALU PENDEK dan tidak valid. Cek kembali hasil copy-paste Anda.");
    }

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

    // Perbaikan: Cek jika ada pesan error spesifik dari Google
    if (isset($result['error'])) {
        $google_error_message = $result['error']['message'] ?? 'Pesan error tidak ditemukan.';
        throw new Exception("Error dari Google AI: " . $google_error_message . " (Pastikan API telah diaktifkan di Google Cloud Console dan billing telah di-setup).");
    }
    if (!isset($result['candidates'])) {
        throw new Exception("Gagal memproses respons dari AI. Respons tidak mengandung data 'candidates'.\n\nRaw Response:\n" . substr(strip_tags($response), 0, 500));
    }

    return $result['candidates'][0]['content']['parts'][0]['text'] ?? 'Maaf, AI memberikan respons kosong. Coba generate ulang.';
}

try {
    $result = generateSoalWithGemini($subject, $grade, $fase, $topic, $tp, $type, $count);
    sendJSONResponse(['success' => true, 'data' => $result]);
} catch (Exception $e) {
    sendJSONResponse(['success' => false, 'message' => $e->getMessage()], 500);
}
?>