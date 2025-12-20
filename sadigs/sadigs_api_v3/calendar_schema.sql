-- Tabel untuk Kalender Akademik
CREATE TABLE IF NOT EXISTS academic_calendar (
    id INT AUTO_INCREMENT PRIMARY KEY,
    event_key VARCHAR(100) NOT NULL UNIQUE, -- Kunci unik untuk setiap event (misal: 'awal_semester_ganjil')
    start_date DATE NULL,
    end_date DATE NULL, -- Hanya diisi jika event berupa rentang tanggal
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);