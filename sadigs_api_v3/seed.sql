-- ================================================================
-- SEED DATA: ADMIN DEFAULT
-- Jalankan file ini di phpMyAdmin setelah database_schema.sql
-- ================================================================

-- 1. Insert User Admin (Password: password)
-- Hash '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi' adalah hash BCRYPT standar untuk kata sandi 'password'
INSERT INTO users (username, email, password_hash, full_name, is_active)
VALUES ('admin', 'admin@sadigs.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Administrator Utama', 1);

-- 2. Berikan Role 'Ketua Yayasan' agar bisa akses menu verifikasi
INSERT INTO user_roles (user_id, role_name)
SELECT user_id, 'Ketua Yayasan' FROM users WHERE username = 'admin';