-- Tabel Riwayat Absensi
CREATE TABLE IF NOT EXISTS attendance_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    attendance_type ENUM('Masuk', 'Pulang') NOT NULL,
    timestamp DATETIME DEFAULT CURRENT_TIMESTAMP, -- Waktu Server (Anti-Manipulasi)
    latitude DECIMAL(10, 8),
    longitude DECIMAL(11, 8),
    address TEXT, -- Alamat hasil deteksi lokasi
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
);