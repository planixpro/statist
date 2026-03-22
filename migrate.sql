-- ============================================================
-- Statist — migrate.sql
-- For existing installs upgrading from the pre-release version.
-- Safe to run multiple times (IF NOT EXISTS / IGNORE).
-- ============================================================

-- 1. Bot flag on sessions
ALTER TABLE `sessions`
  ADD COLUMN IF NOT EXISTS `is_bot` tinyint DEFAULT '0'
    COMMENT '1 = flagged as bot/spam';

ALTER TABLE `sessions`
  ADD INDEX IF NOT EXISTS `idx_bot` (`is_bot`);

-- 2. Users table (replaces hardcoded config.php credentials)
CREATE TABLE IF NOT EXISTS `users` (
  `id`            int           NOT NULL AUTO_INCREMENT,
  `username`      varchar(64)   NOT NULL,
  `password_hash` varchar(255)  NOT NULL,
  `role`          enum('admin','viewer','site_viewer') NOT NULL DEFAULT 'viewer',
  `created_at`    datetime      DEFAULT CURRENT_TIMESTAMP,
  `last_login`    datetime      DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_username` (`username`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- 3. Site-specific access table
CREATE TABLE IF NOT EXISTS `user_sites` (
  `user_id` int NOT NULL,
  `site_id` int NOT NULL,
  PRIMARY KEY (`user_id`,`site_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- 4. Default admin (password: changeme) — skipped if user already exists
INSERT IGNORE INTO `users` (`username`, `password_hash`, `role`)
VALUES (
  'admin',
  '$2y$12$dnL2G.rgRUgO/WHtYreUue4GuES14/FONXP8o.gpJoxjChd0jrZ1W',
  'admin'
);

-- Done. Remember to change the default password!

-- 5. User locale preference
ALTER TABLE `users`
  ADD COLUMN IF NOT EXISTS `locale` varchar(8) NOT NULL DEFAULT 'en';
