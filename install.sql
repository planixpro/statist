-- phpMyAdmin SQL Dump
-- version 5.2.3
-- https://www.phpmyadmin.net/
--
-- Хост: localhost
-- Время создания: Апр 04 2026 г., 09:56
-- Версия сервера: 8.0.45-36
-- Версия PHP: 7.4.33

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- База данных: `stats`
--

-- --------------------------------------------------------

--
-- Структура таблицы `blocked_asns`
--

CREATE TABLE `blocked_asns` (
  `id` bigint NOT NULL,
  `asn` varchar(32) NOT NULL,
  `provider` varchar(128) DEFAULT NULL,
  `reason` varchar(255) DEFAULT NULL,
  `is_active` tinyint NOT NULL DEFAULT '1',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Структура таблицы `blocked_ips`
--

CREATE TABLE `blocked_ips` (
  `id` bigint NOT NULL,
  `ip` varchar(45) NOT NULL,
  `reason` varchar(255) DEFAULT NULL,
  `source` varchar(50) DEFAULT 'auto',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `expires_at` datetime DEFAULT NULL,
  `is_active` tinyint NOT NULL DEFAULT '1'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Структура таблицы `events`
--

CREATE TABLE `events` (
  `id` bigint NOT NULL,
  `site_id` int DEFAULT NULL,
  `session_id` varchar(64) DEFAULT NULL,
  `event_type` varchar(50) DEFAULT NULL,
  `path` varchar(255) DEFAULT NULL,
  `query` text,
  `created_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Структура таблицы `sessions`
--

CREATE TABLE `sessions` (
  `id` bigint NOT NULL,
  `site_id` int DEFAULT NULL,
  `session_id` varchar(64) DEFAULT NULL,
  `ip` varchar(45) DEFAULT NULL,
  `country` varchar(64) DEFAULT NULL,
  `country_code` char(2) DEFAULT NULL,
  `city` varchar(64) DEFAULT NULL,
  `referrer` text,
  `user_agent` text,
  `screen` varchar(20) DEFAULT NULL,
  `language` varchar(10) DEFAULT NULL,
  `timezone` varchar(64) DEFAULT NULL,
  `started_at` datetime DEFAULT NULL,
  `last_activity` datetime DEFAULT NULL,
  `is_valid` tinyint DEFAULT '0',
  `is_bot` tinyint DEFAULT '0' COMMENT '1 = flagged as bot/spam',
  `bot_score` tinyint NOT NULL DEFAULT '0',
  `is_suspicious` tinyint NOT NULL DEFAULT '0',
  `blocked_reason` varchar(255) DEFAULT NULL,
  `events_count` int DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Структура таблицы `sites`
--

CREATE TABLE `sites` (
  `id` int NOT NULL,
  `domain` varchar(255) DEFAULT NULL,
  `name` varchar(255) DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Структура таблицы `users`
--

CREATE TABLE `users` (
  `id` int NOT NULL,
  `username` varchar(64) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `role` enum('admin','viewer','site_viewer') NOT NULL DEFAULT 'viewer',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `last_login` datetime DEFAULT NULL,
  `locale` varchar(8) NOT NULL DEFAULT 'en'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Структура таблицы `user_sessions`
--

CREATE TABLE `user_sessions` (
  `id` bigint NOT NULL,
  `user_id` int NOT NULL,
  `token` char(64) NOT NULL,
  `user_agent` varchar(255) DEFAULT NULL,
  `ip` varchar(45) DEFAULT NULL,
  `expires_at` datetime NOT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Структура таблицы `user_sites`
--

CREATE TABLE `user_sites` (
  `user_id` int NOT NULL,
  `site_id` int NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Индексы сохранённых таблиц
--

--
-- Индексы таблицы `blocked_asns`
--
ALTER TABLE `blocked_asns`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `idx_asn` (`asn`);

--
-- Индексы таблицы `blocked_ips`
--
ALTER TABLE `blocked_ips`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_ip_active` (`ip`,`is_active`),
  ADD KEY `idx_expires` (`expires_at`);

--
-- Индексы таблицы `events`
--
ALTER TABLE `events`
  ADD PRIMARY KEY (`id`),
  ADD KEY `session_id` (`session_id`),
  ADD KEY `site_id` (`site_id`),
  ADD KEY `idx_session_site` (`session_id`,`site_id`);

--
-- Индексы таблицы `sessions`
--
ALTER TABLE `sessions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `idx_site_session` (`site_id`,`session_id`),
  ADD KEY `session_id` (`session_id`),
  ADD KEY `site_id` (`site_id`),
  ADD KEY `started_at` (`started_at`),
  ADD KEY `idx_bot` (`is_bot`);

--
-- Индексы таблицы `sites`
--
ALTER TABLE `sites`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `domain` (`domain`),
  ADD UNIQUE KEY `idx_domain` (`domain`);

--
-- Индексы таблицы `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `idx_username` (`username`);

--
-- Индексы таблицы `user_sessions`
--
ALTER TABLE `user_sessions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `token` (`token`);

--
-- Индексы таблицы `user_sites`
--
ALTER TABLE `user_sites`
  ADD PRIMARY KEY (`user_id`,`site_id`);

--
-- AUTO_INCREMENT для сохранённых таблиц
--

--
-- AUTO_INCREMENT для таблицы `blocked_asns`
--
ALTER TABLE `blocked_asns`
  MODIFY `id` bigint NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT для таблицы `blocked_ips`
--
ALTER TABLE `blocked_ips`
  MODIFY `id` bigint NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT для таблицы `events`
--
ALTER TABLE `events`
  MODIFY `id` bigint NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT для таблицы `sessions`
--
ALTER TABLE `sessions`
  MODIFY `id` bigint NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT для таблицы `sites`
--
ALTER TABLE `sites`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT для таблицы `users`
--
ALTER TABLE `users`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT для таблицы `user_sessions`
--
ALTER TABLE `user_sessions`
  MODIFY `id` bigint NOT NULL AUTO_INCREMENT;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
