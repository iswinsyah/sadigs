<?php
header('Content-Type: application/json');
require_once 'db_connect.php'; // For sendJSONResponse function

if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['user_id'])) {
    sendJSONResponse(['success' => false, 'message' => 'Unauthorized'], 401);
}

$input = json_decode(file_get_contents('php://input'), true);
$subject = $input['subject'] ?? null;
$grade = $input['grade'] ?? null;
$custom_prompt = $input['custom_prompt'] ?? null;

if (!$subject || !$grade) {
    sendJSONResponse(['success' => false, 'message' => 'Mata Pelajaran dan Kelas wajib diisi.'], 400);
}

// --- FUNGSI PANGGIL AI GEMINI (SIAP PAKAI) ---
function generateWithGemini($subject, $grade, $custom_prompt) {
    // Masukkan API Key Gemini Anda di sini nanti
    $apiKey = ''; 
    
    if (empty($apiKey)) return null; // Fallback ke mock jika tidak ada key

    $url = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-pro:generateContent?key=' . $apiKey;
    
    // Gunakan prompt dari user jika ada, jika tidak gunakan default
    $base_prompt = $custom_prompt ? $custom_prompt : 
                   "Bertindaklah sebagai ahli kurikulum. Buatkan daftar Tujuan Pembelajaran (TP) yang ringkas dan operasional untuk mata pelajaran '$subject' kelas $grade (Fase D/E/F) sesuai Kurikulum Merdeka. Pisahkan output untuk Semester Ganjil dan Genap.";

    // Tambahkan instruksi format JSON di akhir agar output konsisten
    $prompt = $base_prompt . 
              "Format output WAJIB JSON murni tanpa markdown: { \"ganjil\": [\"TP 1...\", \"TP 2...\"], \"genap\": [\"TP 3...\", \"TP 4...\"] }";

    $data = ['contents' => [['parts' => [['text' => $prompt]]]]];
    
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    
    $response = curl_exec($ch);
    curl_close($ch);
    
    $result = json_decode($response, true);
    $text = $result['candidates'][0]['content']['parts'][0]['text'] ?? '';
    // Bersihkan markdown ```json jika ada
    $text = str_replace(['```json', '```'], '', $text);
    return json_decode($text, true);
}

// --- SIMULASI DATA (FALLBACK) ---
function getMockPromesSyllabus($subject, $grade) {
    $subject_lower = strtolower($subject);

    // Simulasi perbedaan materi berdasarkan kelas untuk Biologi/IPA
    if (strpos($subject_lower, 'biologi') !== false || strpos($subject_lower, 'ipa') !== false) {
        if ($grade == '10' || $grade == 'X') {
            return [
                'ganjil' => ['Menganalisis berbagai tingkat keanekaragaman hayati.', 'Memahami prinsip klasifikasi makhluk hidup.', 'Menganalisis struktur dan replikasi virus.'],
                'genap' => ['Menganalisis komponen ekosistem dan interaksinya.', 'Menganalisis data perubahan lingkungan.', 'Menerapkan metode ilmiah dalam pengamatan biologi.']
            ];
        } elseif ($grade == '11' || $grade == 'XI') {
            return [
                'ganjil' => ['Menjelaskan komponen kimiawi penyusun sel.', 'Menganalisis bioproses dalam sel (transpor membran).', 'Menganalisis struktur jaringan pada tumbuhan.'],
                'genap' => ['Menganalisis sistem gerak pada manusia.', 'Menganalisis sistem sirkulasi pada manusia.', 'Menganalisis sistem pencernaan makanan.']
            ];
        }
        // Default Biologi/IPA jika kelas lain
    }

    // Data Mock Default Lama
    if (strpos($subject_lower, 'fiqih') !== false) {
        return [
            'ganjil' => [
                'Memahami konsep Thaharah (bersuci) dari hadas dan najis.',
                'Menganalisis tata cara wudhu, tayamum, dan mandi wajib.',
                'Mempraktikkan shalat fardhu lima waktu dengan benar.',
                'Memahami ketentuan puasa Ramadhan dan puasa sunnah.',
                'Menganalisis konsep zakat fitrah dan zakat maal.',
            ],
            'genap' => [
                'Memahami sejarah dan tata cara ibadah haji dan umrah.',
                'Menganalisis konsep jual beli yang sesuai syariat Islam.',
                'Memahami konsep riba dan bahayanya dalam transaksi ekonomi.',
                'Menganalisis ketentuan tentang makanan dan minuman yang halal dan haram.',
                'Memahami konsep pernikahan dan keluarga dalam Islam.',
            ]
        ];
    } elseif (strpos($subject_lower, 'ipa') !== false) {
        return [
            'ganjil' => [
                'Mengidentifikasi ciri-ciri makhluk hidup dan benda mati.',
                'Menganalisis ekosistem dan interaksi antar komponennya.',
                'Memahami konsep energi dan transformasinya dalam kehidupan sehari-hari.',
                'Mendeskripsikan sistem tata surya dan pergerakan benda langit.',
                'Melakukan percobaan sederhana tentang sifat-sifat zat (padat, cair, gas).',
            ],
            'genap' => [
                'Memahami sistem pernapasan pada manusia dan hewan.',
                'Menganalisis sistem peredaran darah dan fungsinya.',
                'Memahami konsep gaya dan gerak serta hukum Newton.',
                'Mendeskripsikan prinsip kerja pesawat sederhana.',
                'Memahami konsep listrik statis dan dinamis dalam rangkaian sederhana.',
            ]
        ];
    }

    // Respons cerdas untuk mapel umum lainnya
    return [
        'ganjil' => [
            "Memahami konsep dasar dan ruang lingkup {$subject}.",
            "Menganalisis fenomena {$subject} dalam kehidupan sehari-hari.",
            "Menerapkan prinsip-prinsip {$subject} untuk menyelesaikan masalah sederhana.",
            "Melakukan pengamatan atau eksperimen terkait materi {$subject} semester ini."
        ],
        'genap' => [
            "Mengevaluasi penerapan {$subject} dalam konteks yang lebih luas.",
            "Merancang proyek sederhana berbasis {$subject}.",
            "Memahami perkembangan terbaru dalam bidang {$subject}.",
            "Menyajikan hasil analisis {$subject} dalam bentuk laporan atau presentasi."
        ]
    ];
}

try {
    // Coba pakai AI dulu, kalau gagal/kosong baru pakai Mock
    $syllabus = generateWithGemini($subject, $grade, $custom_prompt) ?? getMockPromesSyllabus($subject, $grade);
    sendJSONResponse(['success' => true, 'data' => $syllabus]);
} catch (Exception $e) {
    sendJSONResponse(['success' => false, 'message' => 'AI Error: ' . $e->getMessage()], 500);
}
?>