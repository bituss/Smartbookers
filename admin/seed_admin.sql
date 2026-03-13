-- SmartBookers admin user seed
-- Email: admin1@admin.hu | Jelszó: admin123

-- 1) Biztosítjuk, hogy létezik az 'admin' role
INSERT IGNORE INTO `roles` (`name`) VALUES ('admin');

-- 2) Admin user beszúrása (ha még nincs ilyen email)
INSERT INTO `users` (`name`, `email`, `password`, `role_id`, `created_at`)
SELECT 'Admin', 'admin1@admin.hu',
       '$2y$10$X8CowXYUA.tqI5OHxuVTj.IvcPNlxBjz/EOPoNVHuJqJDfV/oI8Wq',
       r.id, NOW()
FROM `roles` r
WHERE r.name = 'admin'
  AND NOT EXISTS (SELECT 1 FROM `users` WHERE `email` = 'admin1@admin.hu');
