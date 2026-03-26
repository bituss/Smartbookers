-- SmartBookers admin user seed
-- Email: admin1@admin.hu | Jelszó: admin123

-- Admin user beszúrása (ha még nincs ilyen email)
INSERT INTO `users` (`name`, `email`, `password`, `role`, `created_at`)
SELECT 'Admin', 'admin1@admin.hu',
       '$2y$10$X8CowXYUA.tqI5OHxuVTj.IvcPNlxBjz/EOPoNVHuJqJDfV/oI8Wq',
       'admin', NOW()
WHERE NOT EXISTS (SELECT 1 FROM `users` WHERE `email` = 'admin1@admin.hu');
