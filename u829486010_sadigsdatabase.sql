-- phpMyAdmin SQL Dump
-- version 5.2.2
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1:3306
-- Waktu pembuatan: 15 Des 2025 pada 05.29
-- Versi server: 11.8.3-MariaDB-log
-- Versi PHP: 7.2.34

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `u829486010_sadigsdatabase`
--

-- --------------------------------------------------------

--
-- Struktur dari tabel `quota_settings`
--

CREATE TABLE `quota_settings` (
  `role_name` varchar(50) NOT NULL,
  `max_limit` int(11) NOT NULL DEFAULT 0,
  `current_count` int(11) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data untuk tabel `quota_settings`
--

INSERT INTO `quota_settings` (`role_name`, `max_limit`, `current_count`) VALUES
('Bendahara Sekolah', 1, 0),
('Kepala Asrama', 2, 0),
('Kepala Sekolah', 1, 0),
('Musyrif', 4, 0),
('Sekretaris Sekolah', 1, 0),
('Ustadz', 10, 1);

-- --------------------------------------------------------

--
-- Struktur dari tabel `roles`
--

CREATE TABLE `roles` (
  `id` int(11) NOT NULL,
  `role_name` varchar(50) NOT NULL,
  `role_label` varchar(50) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

--
-- Dumping data untuk tabel `roles`
--

INSERT INTO `roles` (`id`, `role_name`, `role_label`) VALUES
(1, 'yayasan_ketua', 'Ketua Yayasan'),
(2, 'yayasan_sekretaris', 'Sekretaris Yayasan'),
(3, 'yayasan_bendahara', 'Bendahara Yayasan'),
(4, 'sekolah_kepala', 'Kepala Sekolah'),
(5, 'asrama_kepala', 'Kepala Asrama'),
(6, 'sekolah_sekretaris', 'Sekretaris Sekolah'),
(7, 'sekolah_bendahara', 'Bendahara Sekolah'),
(8, 'ustadz', 'Ustadz/Guru'),
(9, 'musyrif', 'Musyrif'),
(10, 'santri', 'Santri'),
(11, 'walisantri', 'Wali Santri');

-- --------------------------------------------------------

--
-- Struktur dari tabel `role_quotas`
--

CREATE TABLE `role_quotas` (
  `role_id` int(11) NOT NULL,
  `quota_limit` int(11) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

--
-- Dumping data untuk tabel `role_quotas`
--

INSERT INTO `role_quotas` (`role_id`, `quota_limit`) VALUES
(4, 1),
(5, 1),
(6, 1),
(7, 1),
(8, 50),
(9, 10);

-- --------------------------------------------------------

--
-- Struktur dari tabel `users`
--

CREATE TABLE `users` (
  `user_id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data untuk tabel `users`
--

INSERT INTO `users` (`user_id`, `username`, `email`, `password_hash`, `is_active`, `created_at`) VALUES
(6, 'winsyah', 'ketua@yayasan.com', '$2y$10$n4hTj0XqM5uP8s7V1t6v0.w3Z2C5F4D1B0G2J7K5L9O1M0P8T3W0Y2E1X0A0S0Q0R0U0', 1, '2025-12-13 01:50:13'),
(7, 'sabeq', 'sekretaris@yayasan.com', '$2y$10$n4hTj0XqM5uP8s7V1t6v0.w3Z2C5F4D1B0G2J7K5L9O1M0P8T3W0Y2E1X0A0S0Q0R0U0', 1, '2025-12-13 01:50:13'),
(8, 'haikal', 'bendahara@yayasan.com', '$2y$10$n4hTj0XqM5uP8s7V1t6v0.w3Z2C5F4D1B0G2J7K5L9O1M0P8T3W0Y2E1X0A0S0Q0R0U0', 1, '2025-12-13 01:50:13'),
(9, 'mizan', 'mizanainul@gmail.com', '$2y$10$x3DNRewUbKPnYqbFWqi1MeN0nyOS2wWOzSMc1wpnJHWzK6E9fTMpG', 1, '2025-12-14 12:50:24');

-- --------------------------------------------------------

--
-- Struktur dari tabel `user_roles`
--

CREATE TABLE `user_roles` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `role_name` varchar(50) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data untuk tabel `user_roles`
--

INSERT INTO `user_roles` (`id`, `user_id`, `role_name`) VALUES
(8, 6, 'Ketua Yayasan'),
(9, 7, 'Sekretaris Yayasan'),
(10, 8, 'Bendahara Yayasan'),
(11, 9, 'Kepala Sekolah'),
(12, 9, 'Ustadz');

--
-- Indexes for dumped tables
--

--
-- Indeks untuk tabel `quota_settings`
--
ALTER TABLE `quota_settings`
  ADD PRIMARY KEY (`role_name`);

--
-- Indeks untuk tabel `roles`
--
ALTER TABLE `roles`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `role_name` (`role_name`);

--
-- Indeks untuk tabel `role_quotas`
--
ALTER TABLE `role_quotas`
  ADD PRIMARY KEY (`role_id`);

--
-- Indeks untuk tabel `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`user_id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD UNIQUE KEY `email` (`email`);

--
-- Indeks untuk tabel `user_roles`
--
ALTER TABLE `user_roles`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `user_role_unique` (`user_id`,`role_name`);

--
-- AUTO_INCREMENT untuk tabel yang dibuang
--

--
-- AUTO_INCREMENT untuk tabel `roles`
--
ALTER TABLE `roles`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT untuk tabel `users`
--
ALTER TABLE `users`
  MODIFY `user_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT untuk tabel `user_roles`
--
ALTER TABLE `user_roles`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- Ketidakleluasaan untuk tabel pelimpahan (Dumped Tables)
--

--
-- Ketidakleluasaan untuk tabel `role_quotas`
--
ALTER TABLE `role_quotas`
  ADD CONSTRAINT `fk_quota_role` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE;

--
-- Ketidakleluasaan untuk tabel `user_roles`
--
ALTER TABLE `user_roles`
  ADD CONSTRAINT `user_roles_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
