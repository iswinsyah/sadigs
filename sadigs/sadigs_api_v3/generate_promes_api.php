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
        if ($grade == '12' || $grade == 'XII') {
            return [
                'ganjil' => [
                    ['topic' => 'Pertumbuhan dan Perkembangan', 'tp' => 'Menjelaskan konsep pertumbuhan dan perkembangan pada tumbuhan.'],
                    ['topic' => 'Metabolisme Sel', 'tp' => 'Menganalisis proses katabolisme dan anabolisme karbohidrat.'],
                    ['topic' => 'Metabolisme Sel', 'tp' => 'Menjelaskan keterkaitan proses metabolisme karbohidrat, lemak, dan protein.']
                ],
                'genap' => [
                    ['topic' => 'Genetika', 'tp' => 'Menganalisis pola-pola hereditas pada makhluk hidup.'],
                    ['topic' => 'Evolusi', 'tp' => 'Menjelaskan teori evolusi dan bukti-bukti yang mendukungnya.'],
                    ['topic' => 'Bioteknologi', 'tp' => 'Menganalisis prinsip-prinsip bioteknologi dan penerapannya.']
                ]
            ];
        } elseif ($grade == '10' || $grade == 'X') {
            return [
                'ganjil' => [
                    ['topic' => 'Ruang Lingkup Biologi', 'tp' => 'Menjelaskan ruang lingkup biologi dan metode ilmiah.'],
                    ['topic' => 'Keanekaragaman Hayati', 'tp' => 'Menganalisis berbagai tingkat keanekaragaman hayati di Indonesia.'],
                    ['topic' => 'Virus dan Peranannya', 'tp' => 'Mendeskripsikan ciri, replikasi, dan peran virus dalam kehidupan.']
                ],
                'genap' => [
                    ['topic' => 'Protista', 'tp' => 'Mengelompokkan protista berdasarkan ciri-ciri umum.'],
                    ['topic' => 'Fungi (Jamur)', 'tp' => 'Mengelompokkan jamur berdasarkan ciri dan peranannya.'],
                    ['topic' => 'Ekologi', 'tp' => 'Menganalisis interaksi antar komponen ekosistem.']
                ]
            ];
        }
    }

    // Data Mock Default Lama
    if (strpos($subject_lower, 'fiqih') !== false) {
        return [
            'ganjil' => [
                ['topic' => 'Thaharah', 'tp' => 'Memahami konsep Thaharah (bersuci) dari hadas dan najis.'],
                ['topic' => 'Thaharah', 'tp' => 'Menganalisis tata cara wudhu, tayamum, dan mandi wajib.'],
                ['topic' => 'Shalat', 'tp' => 'Mempraktikkan shalat fardhu lima waktu dengan benar.'],
                ['topic' => 'Puasa', 'tp' => 'Memahami ketentuan puasa Ramadhan dan puasa sunnah.'],
                ['topic' => 'Zakat', 'tp' => 'Menganalisis konsep zakat fitrah dan zakat maal.']
            ],
            'genap' => [
                ['topic' => 'Haji dan Umrah', 'tp' => 'Memahami sejarah dan tata cara ibadah haji dan umrah.'],
                ['topic' => 'Muamalah', 'tp' => 'Menganalisis konsep jual beli yang sesuai syariat Islam.'],
                ['topic' => 'Muamalah', 'tp' => 'Memahami konsep riba dan bahayanya dalam transaksi ekonomi.'],
                ['topic' => 'Makanan dan Minuman Halal', 'tp' => 'Menganalisis ketentuan tentang makanan dan minuman yang halal dan haram.'],
                ['topic' => 'Pernikahan', 'tp' => 'Memahami konsep pernikahan dan keluarga dalam Islam.']
            ]
        ];
    }

    // Respons cerdas untuk mapel umum lainnya
    return [
        'ganjil' => [
            ['topic' => "Dasar-dasar {$subject}", 'tp' => "Memahami konsep dasar dan ruang lingkup {$subject}."],
            ['topic' => "Dasar-dasar {$subject}", 'tp' => "Menganalisis fenomena {$subject} dalam kehidupan sehari-hari."],
            ['topic' => "Penerapan {$subject}", 'tp' => "Menerapkan prinsip-prinsip {$subject} untuk menyelesaikan masalah sederhana."],
            ['topic' => "Penerapan {$subject}", 'tp' => "Melakukan pengamatan atau eksperimen terkait materi {$subject} semester ini."]
        ],
        'genap' => [
            ['topic' => "Evaluasi {$subject}", 'tp' => "Mengevaluasi penerapan {$subject} dalam konteks yang lebih luas."],
            ['topic' => "Proyek {$subject}", 'tp' => "Merancang proyek sederhana berbasis {$subject}."],
            ['topic' => "Konteks Global {$subject}", 'tp' => "Memahami perkembangan terbaru dalam bidang {$subject}."],
            ['topic' => "Komunikasi Ilmiah", 'tp' => "Menyajikan hasil analisis {$subject} dalam bentuk laporan atau presentasi."]
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