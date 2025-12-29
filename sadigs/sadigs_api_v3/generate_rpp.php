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

// 1. Tentukan Fase Kurikulum Merdeka berdasarkan Kelas
$phase = 'D'; // Default SMP
$g = (int)$grade;
if ($g >= 1 && $g <= 2) $phase = 'A';
elseif ($g >= 3 && $g <= 4) $phase = 'B';
elseif ($g >= 5 && $g <= 6) $phase = 'C';
elseif ($g >= 7 && $g <= 9) $phase = 'D';
elseif ($g == 10) $phase = 'E';
elseif ($g >= 11 && $g <= 12) $phase = 'F';

// 2. Susun prompt untuk AI (Disesuaikan dengan Kurikulum Merdeka)
$prompt = "Buatkan Modul Ajar (Kurikulum Merdeka) lengkap untuk Fase {$phase} (Kelas {$grade}). Mata Pelajaran: {$subject}. Topik: {$topic}. Tujuan Pembelajaran: {$objectives}. Struktur: 1. Informasi Umum, 2. Komponen Inti (Capaian Pembelajaran, Pemahaman Bermakna, Pertanyaan Pemantik, Kegiatan Pembelajaran), 3. Asesmen/Penilaian.";

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
**MODUL AJAR (KURIKULUM MERDEKA)**\n
**Mata Pelajaran:** {$subject}
**Kelas / Fase:** {$grade} / Fase {$phase}
**Materi Pokok:** {$topic}
**Alokasi Waktu:** 2 x 45 Menit\n
**A. KOMPONEN INTI**
1. **Capaian Pembelajaran:** Peserta didik mampu memahami konsep {$topic} secara mendalam.
2. **Tujuan Pembelajaran:** {$objectives}
3. **Pemahaman Bermakna:** Siswa memahami relevansi {$topic} dalam kehidupan sehari-hari.
4. **Pertanyaan Pemantik:** Bagaimana peran {$topic} dalam konteks nyata?\n
**B. KEGIATAN PEMBELAJARAN**
- **Pendahuluan (10'):** Salam, doa, apersepsi, dan penyampaian tujuan.
- **Inti (70'):** Diferensiasi konten (video/artikel), diskusi kelompok (gotong royong), presentasi hasil (bernalar kritis).
- **Penutup (10'):** Refleksi, kesimpulan, dan doa.\n
**C. ASESMEN**
- **Formatif:** Observasi diskusi dan LKPD.
- **Sumatif:** Tes tertulis pemahaman konsep.\n
---
*Catatan: Modul Ajar ini dibuat oleh AI (Draft). Silakan sesuaikan dengan Profil Pelajar Pancasila di sekolah Anda.*
";

sendJSONResponse(['success' => true, 'rpp' => $mockResponse]);
?>