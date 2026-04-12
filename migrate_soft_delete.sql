-- ============================================================
-- Migráció: Soft delete implementálása a users táblában
-- ============================================================

-- Hozzáadás a deactivated_at mező a users táblához
ALTER TABLE users
ADD COLUMN `deactivated_at` DATETIME DEFAULT NULL AFTER `avatar`;

-- Index hozzáadása a deactivated_at mezőhöz (gyorsabb lekérdezésekhez)
ALTER TABLE users
ADD INDEX `idx_deactivated_at` (`deactivated_at`);

-- Biztonsági index az aktív felhasználók gyors lekérdezéséhez
ALTER TABLE users
ADD INDEX `idx_active_users` (`deactivated_at`, `role`);
