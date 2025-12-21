-- Tabel Jadwal Rapat
CREATE TABLE IF NOT EXISTS meetings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    meeting_name VARCHAR(255) NOT NULL,
    meeting_date DATE NOT NULL,
    meeting_time TIME NOT NULL,
    location VARCHAR(255) NOT NULL,
    agenda TEXT,
    inviter VARCHAR(100) NOT NULL, -- Ketua Yayasan, Kepala Sekolah, atau Kepala Asrama
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);