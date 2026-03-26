-- ============================================================
-- Migráció: roles tábla törlése, messages egyszerűsítése
-- ============================================================

SET FOREIGN_KEY_CHECKS = 0;

-- 1) users: role ENUM oszlop hozzáadása
ALTER TABLE users
  ADD COLUMN `role` ENUM('user','provider','admin') NOT NULL DEFAULT 'user' AFTER `password`;

-- 2) role feltöltése a meglévő role_id alapján
UPDATE users u
  JOIN roles r ON r.id = u.role_id
  SET u.role = r.name;

-- 3) messages: by_provider hozzáadása
ALTER TABLE messages
  ADD COLUMN `by_provider` TINYINT(1) NOT NULL DEFAULT 0;

-- 4) by_provider feltöltése sender_role alapján
UPDATE messages
  SET by_provider = CASE WHEN sender_role = 'provider' THEN 1 ELSE 0 END;

-- 5) messages: régi indexek törlése (érintik a törlendő oszlopokat)
ALTER TABLE messages
  DROP INDEX `uniq_booking_auto`,
  DROP INDEX `idx_booking`,
  DROP INDEX `idx_conv_seen_user`,
  DROP INDEX `idx_conv_seen_provider`;

-- 6) messages: új indexek by_provider-rel
ALTER TABLE messages
  ADD INDEX `idx_conv_seen_user`     (`conversation_id`, `by_provider`, `seen_by_user`),
  ADD INDEX `idx_conv_seen_provider` (`conversation_id`, `by_provider`, `seen_by_provider`);

-- 7) messages: régi oszlopok törlése
ALTER TABLE messages
  DROP COLUMN `sender_role`,
  DROP COLUMN `sender_user_id`,
  DROP COLUMN `sender_provider_id`,
  DROP COLUMN `booking_id`;

-- 8) users: FK + role_id törlése
ALTER TABLE users
  DROP FOREIGN KEY `users_ibfk_1`,
  DROP COLUMN `role_id`;

-- 9) roles tábla törlése
DROP TABLE roles;

SET FOREIGN_KEY_CHECKS = 1;
