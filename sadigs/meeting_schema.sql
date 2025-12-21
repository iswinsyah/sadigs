-- Tabel Jadwal Rapat
CREATE TABLE IF NOT EXISTS meetings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    meeting_name VARCHAR(255) NOT NULL,
    meeting_date DATE, -- Bisa NULL jika rutin pekanan
    meeting_time TIME NOT NULL,
    location VARCHAR(255) NOT NULL,
    agenda TEXT,
    inviter VARCHAR(100) NOT NULL, -- Ketua Yayasan, Kepala Sekolah, atau Kepala Asrama
    routine ENUM('sekali', 'setiap_pekan', 'setiap_bulan') NOT NULL DEFAULT 'sekali',
    day VARCHAR(20), -- Senin, Selasa, dll (Bisa NULL jika rutin bulanan)
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);