-- ================================================================
-- SADIGS 3.0 DATABASE SCHEMA (CLEAN VERSION)
-- Jalankan file ini di phpMyAdmin (Tab Import atau SQL)
-- PERINGATAN: Ini akan menghapus data lama!
-- ================================================================

DROP TABLE IF EXISTS user_roles;
DROP TABLE IF EXISTS users;
DROP TABLE IF EXISTS quota_settings;

-- 1. Tabel Users (Pondasi Akun)
CREATE TABLE users (
    user_id INT AUTO_INCREMENT PRIMARY KEY, -- Konsisten menggunakan user_id
    username VARCHAR(50) NOT NULL UNIQUE,
    email VARCHAR(100) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    full_name VARCHAR(100) NULL,
    bio TEXT NULL,
    is_active TINYINT(1) DEFAULT 0, -- 0: Belum aktif, 1: Aktif
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- 2. Tabel User Roles (Untuk Multi-Role)
CREATE TABLE user_roles (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    role_name VARCHAR(50) NOT NULL,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
);

-- 3. Tabel Quota Settings (Untuk Batasan Pendaftaran)
CREATE TABLE quota_settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    role_name VARCHAR(50) NOT NULL UNIQUE,
    max_limit INT DEFAULT 0
);