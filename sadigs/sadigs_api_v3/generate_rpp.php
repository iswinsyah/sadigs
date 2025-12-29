<?php
// API: Generate RPP using AI
header('Content-Type: application/json');
require_once 'db_connect.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id'])) {
    sendJSONResponse(['success' => false, 'message' => 'Unauthorized'], 401);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);

if (empty($input['subject']) || empty($input['topic'])) {
    sendJSONResponse(['success' => false, 'message' => 'Mata pelajaran dan topik wajib diisi.'], 400);
    exit;
}

$subject = htmlspecialchars($input['subject']);
$grade = htmlspecialchars($input['grade']);
$topic = htmlspecialchars($input['topic']);
$objectives = htmlspecialchars($input['objectives']);

// 1. Susun prompt untuk AI
$prompt = "Buatkan saya sebuah Rencana Pelaksanaan Pembelajaran (RPP) yang lengkap untuk jenjang {$grade}. Mata pelajarannya adalah {$subject} dengan materi pokok tentang {$topic}. Tujuan pembelajarannya adalah: {$objectives}. Sertakan komponen pendahuluan, kegiatan inti, penutup, dan penilaian.";

// =================================================================
// == TITIK INTEGRASI AI (GEMINI API) ==
// =================================================================
// Di sinilah Anda akan melakukan panggilan ke API Gemini.
// Anda memerlukan API Key dan menggunakan cURL atau library HTTP client seperti Guzzle.
/*
    $geminiApiKey = 'AIza...'; // SIMPAN DI TEMPAT AMAN, JANGAN DI SINI
    $url = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-pro:generateContent?key=' . $geminiApiKey;
    $data = ['contents' => [['parts' => [['text' => $prompt]]]]];
    
    // Contoh dengan cURL
    $ch = curl_init($url);
    // ... (konfigurasi cURL untuk POST request)
    $response = curl_exec($ch);
    curl_close($ch);

    $result = json_decode($response, true);
    $generatedText = $result['candidates'][0]['content']['parts'][0]['text'] ?? 'Gagal menghasilkan teks.';
*/
// =================================================================

// 3. Untuk saat ini, kita kembalikan respon tiruan (mock)
sleep(2); // Simulasi AI sedang berpikir

$mockResponse = "
**RENCANA PELAKSANAAN PEMBELAJARAN (RPP)**\n
**Mata Pelajaran:** {$subject}
**Kelas/Semester:** {$grade}
**Materi Pokok:** {$topic}
**Alokasi Waktu:** 2 x 45 Menit\n
**A. Tujuan Pembelajaran**
{$objectives}\n
**B. Kegiatan Pembelajaran**
1.  **Pendahuluan (10 Menit):** Guru membuka pelajaran dengan salam, doa, dan melakukan apersepsi terkait materi {$topic}.
2.  **Kegiatan Inti (70 Menit):** Guru menjelaskan konsep dasar {$topic} menggunakan media. Siswa dibagi menjadi beberapa kelompok untuk berdiskusi dan mengerjakan lembar kerja. Setiap kelompok mempresentasikan hasilnya.
3.  **Penutup (10 Menit):** Guru bersama siswa membuat kesimpulan, melakukan refleksi, dan menutup pelajaran.\n
**C. Penilaian**
- Penilaian Sikap: Observasi selama diskusi.
- Penilaian Pengetahuan: Tes tulis singkat (post-test).
- Penilaian Keterampilan: Unjuk kerja saat presentasi.\n
---
*Catatan: RPP ini dibuat oleh AI dan merupakan draf. Harap sesuaikan kembali dengan kebutuhan spesifik di kelas Anda.*
";

sendJSONResponse(['success' => true, 'rpp' => $mockResponse]);
?>