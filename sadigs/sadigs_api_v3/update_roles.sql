-- ================================================================
-- UPDATE ROLES KHUSUS (Jalankan di phpMyAdmin Hostinger)
-- ================================================================

-- 1. Winsyah -> Ketua Yayasan
DELETE FROM user_roles WHERE user_id = (SELECT user_id FROM users WHERE username = 'winsyah');
INSERT INTO user_roles (user_id, role_name) VALUES ((SELECT user_id FROM users WHERE username = 'winsyah'), 'Ketua Yayasan');

-- 2. Sabeq -> Sekretaris Yayasan
DELETE FROM user_roles WHERE user_id = (SELECT user_id FROM users WHERE username = 'Sabeq');
INSERT INTO user_roles (user_id, role_name) VALUES ((SELECT user_id FROM users WHERE username = 'Sabeq'), 'Sekretaris Yayasan');

-- 3. Haikal -> Bendahara Yayasan
DELETE FROM user_roles WHERE user_id = (SELECT user_id FROM users WHERE username = 'Haikal');
INSERT INTO user_roles (user_id, role_name) VALUES ((SELECT user_id FROM users WHERE username = 'Haikal'), 'Bendahara Yayasan');