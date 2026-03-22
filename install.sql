-- ============================================================
-- Statist — install.sql
-- Run once on a fresh database named `stats`
-- ============================================================

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";
SET NAMES utf8mb4;

-- --------------------------------------------------------
-- sites
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `sites` (
  `id`         int           NOT NULL AUTO_INCREMENT,
  `domain`     varchar(255)  DEFAULT NULL,
  `name`       varchar(255)  DEFAULT NULL,
  `created_at` datetime      DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_domain` (`domain`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------
-- sessions
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `sessions` (
  `id`            bigint       NOT NULL AUTO_INCREMENT,
  `site_id`       int          DEFAULT NULL,
  `session_id`    varchar(64)  DEFAULT NULL,
  `ip`            varchar(45)  DEFAULT NULL,
  `country`       varchar(64)  DEFAULT NULL,
  `country_code`  char(2)      DEFAULT NULL,
  `city`          varchar(64)  DEFAULT NULL,
  `referrer`      text,
  `user_agent`    text,
  `screen`        varchar(20)  DEFAULT NULL,
  `language`      varchar(10)  DEFAULT NULL,
  `timezone`      varchar(64)  DEFAULT NULL,
  `started_at`    datetime     DEFAULT NULL,
  `last_activity` datetime     DEFAULT NULL,
  `is_valid`      tinyint      DEFAULT '0' COMMENT '1 = heartbeat confirmed real visit',
  `is_bot`        tinyint      DEFAULT '0' COMMENT '1 = flagged as bot/spam',
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_site_session` (`site_id`,`session_id`),
  KEY `session_id`  (`session_id`),
  KEY `site_id`     (`site_id`),
  KEY `started_at`  (`started_at`),
  KEY `idx_bot`     (`is_bot`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------
-- events
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `events` (
  `id`          bigint       NOT NULL AUTO_INCREMENT,
  `site_id`     int          DEFAULT NULL,
  `session_id`  varchar(64)  DEFAULT NULL,
  `event_type`  varchar(50)  DEFAULT NULL,
  `path`        varchar(255) DEFAULT NULL,
  `query`       text,
  `created_at`  datetime     DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `session_id`       (`session_id`),
  KEY `site_id`          (`site_id`),
  KEY `idx_session_site` (`session_id`,`site_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------
-- users
-- Roles:
--   admin       — full access + user management
--   viewer      — read-only: all sites
--   site_viewer — read-only: only assigned sites (via user_sites)
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `users` (
  `id`            int           NOT NULL AUTO_INCREMENT,
  `username`      varchar(64)   NOT NULL,
  `password_hash` varchar(255)  NOT NULL,
  `role`          enum('admin','viewer','site_viewer') NOT NULL DEFAULT 'viewer',
  `created_at`    datetime      DEFAULT CURRENT_TIMESTAMP,
  `last_login`    datetime      DEFAULT NULL,
  `locale`        varchar(8)    NOT NULL DEFAULT 'en',
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_username` (`username`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------
-- user_sites — site access for site_viewer role
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `user_sites` (
  `user_id` int NOT NULL,
  `site_id` int NOT NULL,
  PRIMARY KEY (`user_id`,`site_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------
-- Default admin: login=admin / password=changeme
-- !! CHANGE THIS PASSWORD IMMEDIATELY after first login !!
-- --------------------------------------------------------
INSERT IGNORE INTO `users` (`username`, `password_hash`, `role`)
VALUES (
  'admin',
  '$2y$12$dnL2G.rgRUgO/WHtYreUue4GuES14/FONXP8o.gpJoxjChd0jrZ1W',
  'admin'
);

COMMIT;
