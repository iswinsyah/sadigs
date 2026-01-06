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
    $configPath = __DIR__ . '/ai_config.php';
    if (!file_exists($configPath)) {
        return null; // Fallback ke mock jika file config tidak ada
    }
    require $configPath;
    
    if (empty(trim($apiKey)) || strlen(trim($apiKey)) < 30) {
        return null; // Fallback ke mock jika API key kosong atau tidak valid
    }

    $url = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-1.5-flash-001:generateContent?key=' . $apiKey;
    
    // Gunakan prompt dari user jika ada, jika tidak gunakan default
    $base_prompt = $custom_prompt && strlen(trim($custom_prompt)) > 10 ? $custom_prompt :
                   "Bertindaklah sebagai ahli kurikulum. Untuk mata pelajaran '$subject' kelas $grade (Kurikulum Merdeka), cantumkan teks CP yang ada di kurikulum merdeka, dan buatkan daftar Materi Pokok/Topik beserta Tujuan Pembelajarannya (TP) yang ringkas dan operasional. Pisahkan output untuk Semester Ganjil dan Genap.";

    // Tambahkan instruksi format JSON di akhir agar output konsisten
    $prompt = $base_prompt . 
              " Format output WAJIB JSON murni tanpa markdown: {\"cp_text\": \"Teks CP resmi di sini...\", \"ganjil\": [{\"topic\": \"Materi Pokok 1\", \"tp\": \"Tujuan Pembelajaran 1.1\"}], \"genap\": [{\"topic\": \"Materi Pokok 2\", \"tp\": \"Tujuan Pembelajaran 2.1\"}]}";

    $data = ['contents' => [['parts' => [['text' => $prompt]]]]];
    
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    
    $response = curl_exec($ch);
    curl_close($ch);

    $result = json_decode($response, true);

    // Perbaikan: Cek jika ada pesan error spesifik dari Google
    if (isset($result['error'])) {
        $google_error_message = $result['error']['message'] ?? 'Pesan error tidak ditemukan.';
        throw new Exception("Error dari Google AI: " . $google_error_message . " (Pastikan API telah diaktifkan di Google Cloud Console dan billing telah di-setup).");
    }
    if (!isset($result['candidates'])) {
        throw new Exception("Gagal memproses respons dari AI untuk ATP. Respons tidak mengandung data 'candidates'.\n\nRaw Response:\n" . substr(strip_tags($response), 0, 500));
    }

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
                'cp_text' => "Pada akhir fase F, peserta didik memiliki kemampuan mendeskripsikan bioproses yang terjadi dalam sel, dan menganalisis keterkaitan struktur organ pada sistem organ dengan fungsinya serta kelainan atau gangguan yang muncul pada sistem organ tersebut. Selanjutnya peserta didik memiliki kemampuan menerapkan konsep pewarisan sifat, evolusi dan bioteknologi dalam kehidupan sehari-hari.",
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
                'cp_text' => "Pada akhir fase E, peserta didik memiliki kemampuan untuk mendeskripsikan ciri-ciri, replikasi dan peran virus dalam kehidupan, serta menerapkan prinsip-prinsip pengelompokan mahluk hidup untuk mengkaji keanekaragaman hayati. Peserta didik dapat menganalisis hubungan antar komponen ekosistem dan peran manusia dalam menjaga keseimbangan ekosistem.",
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
            'cp_text' => "Pada akhir fase D, peserta didik dapat melaksanakan bersuci, salat fardu, zikir dan doa setelah salat. Peserta didik juga dapat mempraktikkan puasa wajib dan salat sunah (tarawih dan witir).",
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
        'cp_text' => "Capaian Pembelajaran untuk {$subject} kelas {$grade} belum terdefinisi secara spesifik dalam data mock. AI akan mencoba membuatnya berdasarkan mata pelajaran.",
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