-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Gép: 127.0.0.1
-- Létrehozás ideje: 2026. Feb 03. 18:47
-- Kiszolgáló verziója: 10.4.28-MariaDB
-- PHP verzió: 8.2.4

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Adatbázis: `idopont_foglalas`
--

-- --------------------------------------------------------

--
-- Tábla szerkezet ehhez a táblához `appointments`
--

CREATE TABLE `appointments` (
  `id` int(11) NOT NULL,
  `provider_id` int(11) NOT NULL,
  `date_time` datetime NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_hungarian_ci;

--
-- A tábla adatainak kiíratása `appointments`
--

INSERT INTO `appointments` (`id`, `provider_id`, `date_time`, `created_at`) VALUES
(1, 1, '2027-09-16 21:27:00', '2026-02-02 18:27:37'),
(2, 2, '2029-03-23 21:52:00', '2026-02-03 15:52:33'),
(3, 1, '2026-10-03 20:51:00', '2026-02-03 16:51:20');

-- --------------------------------------------------------

--
-- Tábla szerkezet ehhez a táblához `bookings`
--

CREATE TABLE `bookings` (
  `id` int(11) NOT NULL,
  `appointment_id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `user_name` varchar(255) NOT NULL,
  `user_email` varchar(255) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `status` enum('booked','cancelled') NOT NULL DEFAULT 'booked',
  `cancelled_at` datetime DEFAULT NULL,
  `provider_seen` tinyint(1) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_hungarian_ci;

--
-- A tábla adatainak kiíratása `bookings`
--

INSERT INTO `bookings` (`id`, `appointment_id`, `user_id`, `user_name`, `user_email`, `created_at`, `status`, `cancelled_at`, `provider_seen`) VALUES
(1, 1, NULL, 'Gazsó Norbert', 'gazsonorbi@gmail.com', '2026-02-02 18:31:50', 'cancelled', '2026-02-02 19:49:59', 1),
(2, 1, NULL, 'Gazsó Norbert', 'gazsonorbi@gmail.com', '2026-02-03 05:44:59', 'booked', NULL, 0),
(3, 3, NULL, 'Gazsó Norbert', 'gazsonorbi@gmail.com', '2026-02-03 17:27:53', 'cancelled', '2026-02-03 18:28:22', 1);

-- --------------------------------------------------------

--
-- Tábla szerkezet ehhez a táblához `conversations`
--

CREATE TABLE `conversations` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `provider_id` int(11) NOT NULL,
  `appointment_id` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `last_message_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_hungarian_ci;

--
-- A tábla adatainak kiíratása `conversations`
--

INSERT INTO `conversations` (`id`, `user_id`, `provider_id`, `appointment_id`, `created_at`, `last_message_at`) VALUES
(1, 2, 1, 1, '2026-02-03 05:44:59', '2026-02-03 05:44:59'),
(2, 2, 1, NULL, '2026-02-03 17:27:53', NULL),
(3, 2, 1, 3, '2026-02-03 17:27:54', '2026-02-03 17:27:54');

-- --------------------------------------------------------

--
-- Tábla szerkezet ehhez a táblához `industries`
--

CREATE TABLE `industries` (
  `id` int(11) NOT NULL,
  `slug` varchar(64) NOT NULL,
  `name` varchar(120) NOT NULL,
  `description` text DEFAULT NULL,
  `hero_image` varchar(255) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_hungarian_ci;

--
-- A tábla adatainak kiíratása `industries`
--

INSERT INTO `industries` (`id`, `slug`, `name`, `description`, `hero_image`, `is_active`) VALUES
(1, 'kozmetika', 'Kozmetika', 'Bőrápolás, arckezelések, szempilla és kozmetikai szolgáltatások.', NULL, 1),
(2, 'fodraszat', 'Fodrászat', 'Női, férfi és gyermek hajvágás, festés, styling.', NULL, 1),
(3, 'mukorom', 'Műköröm', 'Géllakk, épített köröm, manikűr és körömápolás.', NULL, 1),
(4, 'masszazs', 'Masszázs', 'Frissítő, relax, sport és gyógymasszázs szolgáltatások.', NULL, 1),
(5, 'egeszseg', 'Egészség', 'Egészségügyi jellegű szolgáltatások és tanácsadások.', NULL, 1);

-- --------------------------------------------------------

--
-- Tábla szerkezet ehhez a táblához `messages`
--

CREATE TABLE `messages` (
  `id` int(11) NOT NULL,
  `conversation_id` int(11) NOT NULL,
  `sender_role` enum('user','provider') NOT NULL,
  `sender_user_id` int(11) NOT NULL,
  `body` text NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `seen_by_user` tinyint(1) NOT NULL DEFAULT 0,
  `seen_by_provider` tinyint(1) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_hungarian_ci;

--
-- A tábla adatainak kiíratása `messages`
--

INSERT INTO `messages` (`id`, `conversation_id`, `sender_role`, `sender_user_id`, `body`, `created_at`, `seen_by_user`, `seen_by_provider`) VALUES
(1, 1, 'user', 2, 'Szia! Lefoglaltam az időpontot, lenne egy kérdésem.', '2026-02-03 05:44:59', 1, 1),
(2, 1, 'user', 0, 'sziaaaaaaaaaaaaaaaaa', '2026-02-03 06:55:33', 1, 1),
(3, 1, 'provider', 0, 'mi a kéréds', '2026-02-03 06:56:28', 1, 1),
(4, 1, 'user', 0, 'nemtom', '2026-02-03 06:56:53', 1, 1),
(5, 1, 'user', 0, 'szia', '2026-02-03 07:14:29', 1, 1),
(6, 1, 'user', 2, 'hi', '2026-02-03 07:17:36', 1, 1),
(7, 1, 'user', 2, 'hiiiiiiiiiii', '2026-02-03 07:17:42', 1, 1),
(8, 1, 'user', 2, 'jojo', '2026-02-03 12:28:58', 1, 1),
(9, 1, 'provider', 1, 'yeeeeeeee', '2026-02-03 15:04:34', 1, 1),
(10, 3, 'user', 2, 'Szia! Lefoglaltam az időpontot, lenne egy kérdésem.', '2026-02-03 17:27:54', 1, 1);

-- --------------------------------------------------------

--
-- Tábla szerkezet ehhez a táblához `providers`
--

CREATE TABLE `providers` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `service_id` int(11) NOT NULL,
  `business_name` varchar(150) DEFAULT NULL,
  `phone` varchar(50) DEFAULT NULL,
  `city` varchar(100) DEFAULT NULL,
  `free_trial_start` datetime DEFAULT NULL,
  `basic_start` datetime DEFAULT NULL,
  `pro_start` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `industry_id` int(11) DEFAULT NULL,
  `avatar` varchar(255) DEFAULT NULL,
  `bio` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_hungarian_ci;

--
-- A tábla adatainak kiíratása `providers`
--

INSERT INTO `providers` (`id`, `user_id`, `service_id`, `business_name`, `phone`, `city`, `free_trial_start`, `basic_start`, `pro_start`, `created_at`, `industry_id`, `avatar`, `bio`) VALUES
(1, 3, 1, 'Koplányi Bítia Anna', NULL, NULL, NULL, NULL, NULL, '2026-02-02 18:14:23', 1, '/Smartbookers/public/img/providers/p_1_1770140663.png', ''),
(2, 4, 3, 'MasszazswithM', NULL, NULL, NULL, NULL, NULL, '2026-02-03 15:51:41', 2, NULL, NULL);

-- --------------------------------------------------------

--
-- Tábla szerkezet ehhez a táblához `roles`
--

CREATE TABLE `roles` (
  `id` int(11) NOT NULL,
  `name` varchar(50) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_hungarian_ci;

--
-- A tábla adatainak kiíratása `roles`
--

INSERT INTO `roles` (`id`, `name`) VALUES
(3, 'admin'),
(2, 'provider'),
(1, 'user');

-- --------------------------------------------------------

--
-- Tábla szerkezet ehhez a táblához `services`
--

CREATE TABLE `services` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_hungarian_ci;

--
-- A tábla adatainak kiíratása `services`
--

INSERT INTO `services` (`id`, `name`) VALUES
(2, 'Fodrászat'),
(1, 'Kozmetika'),
(3, 'Masszázs'),
(5, 'Mentálhigiénia'),
(4, 'Műköröm építő');

-- --------------------------------------------------------

--
-- Tábla szerkezet ehhez a táblához `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `email` varchar(150) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role_id` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `avatar` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_hungarian_ci;

--
-- A tábla adatainak kiíratása `users`
--

INSERT INTO `users` (`id`, `name`, `email`, `password`, `role_id`, `created_at`, `avatar`) VALUES
(2, 'Gazsó Norbert', 'gazsonorbi@gmail.com', '$2y$10$WDl2zS2HxneQ7FbVVNfe.uvIZQ6xPCqBEWKMWpdRvDsulQSoWj46.', 1, '2026-02-02 17:55:51', '/Smartbookers/public/images/avatars/a10.png'),
(3, 'Koplányi Bítia Anna', 'bituss@icloud.com', '$2y$10$xdEldv/83sxdZL0y9KqN4.Q95zvz4UuNTq0ILFTO3gINcP7tAQweu', 2, '2026-02-02 18:14:23', NULL),
(4, 'Nagy Martina', 'nagymartina@gmail.com', '$2y$10$8jmw9LxEFS4q8XbI5oKhsegEcsi4bkJLqPbe0699tFMp2xW0BjP9y', 2, '2026-02-03 15:51:41', NULL);

--
-- Indexek a kiírt táblákhoz
--

--
-- A tábla indexei `appointments`
--
ALTER TABLE `appointments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `provider_id` (`provider_id`);

--
-- A tábla indexei `bookings`
--
ALTER TABLE `bookings`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_bookings_user` (`user_id`),
  ADD KEY `idx_bookings_appt` (`appointment_id`),
  ADD KEY `idx_bookings_status_seen` (`status`,`provider_seen`);

--
-- A tábla indexei `conversations`
--
ALTER TABLE `conversations`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_conv` (`user_id`,`provider_id`,`appointment_id`),
  ADD KEY `idx_user` (`user_id`),
  ADD KEY `idx_provider` (`provider_id`),
  ADD KEY `fk_conv_appt` (`appointment_id`);

--
-- A tábla indexei `industries`
--
ALTER TABLE `industries`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `slug` (`slug`);

--
-- A tábla indexei `messages`
--
ALTER TABLE `messages`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_conv_created` (`conversation_id`,`created_at`);

--
-- A tábla indexei `providers`
--
ALTER TABLE `providers`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `service_id` (`service_id`),
  ADD KEY `fk_providers_industry` (`industry_id`);

--
-- A tábla indexei `roles`
--
ALTER TABLE `roles`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `name` (`name`);

--
-- A tábla indexei `services`
--
ALTER TABLE `services`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `name` (`name`);

--
-- A tábla indexei `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`),
  ADD KEY `role_id` (`role_id`);

--
-- A kiírt táblák AUTO_INCREMENT értéke
--

--
-- AUTO_INCREMENT a táblához `appointments`
--
ALTER TABLE `appointments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT a táblához `bookings`
--
ALTER TABLE `bookings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT a táblához `conversations`
--
ALTER TABLE `conversations`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT a táblához `industries`
--
ALTER TABLE `industries`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT a táblához `messages`
--
ALTER TABLE `messages`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT a táblához `providers`
--
ALTER TABLE `providers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT a táblához `roles`
--
ALTER TABLE `roles`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT a táblához `services`
--
ALTER TABLE `services`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT a táblához `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- Megkötések a kiírt táblákhoz
--

--
-- Megkötések a táblához `appointments`
--
ALTER TABLE `appointments`
  ADD CONSTRAINT `appointments_ibfk_1` FOREIGN KEY (`provider_id`) REFERENCES `providers` (`id`) ON DELETE CASCADE;

--
-- Megkötések a táblához `bookings`
--
ALTER TABLE `bookings`
  ADD CONSTRAINT `bookings_ibfk_1` FOREIGN KEY (`appointment_id`) REFERENCES `appointments` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_bookings_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Megkötések a táblához `conversations`
--
ALTER TABLE `conversations`
  ADD CONSTRAINT `fk_conv_appt` FOREIGN KEY (`appointment_id`) REFERENCES `appointments` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_conv_provider` FOREIGN KEY (`provider_id`) REFERENCES `providers` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_conv_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Megkötések a táblához `messages`
--
ALTER TABLE `messages`
  ADD CONSTRAINT `fk_msg_conv` FOREIGN KEY (`conversation_id`) REFERENCES `conversations` (`id`) ON DELETE CASCADE;

--
-- Megkötések a táblához `providers`
--
ALTER TABLE `providers`
  ADD CONSTRAINT `fk_providers_industry` FOREIGN KEY (`industry_id`) REFERENCES `industries` (`id`),
  ADD CONSTRAINT `providers_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `providers_ibfk_2` FOREIGN KEY (`service_id`) REFERENCES `services` (`id`);

--
-- Megkötések a táblához `users`
--
ALTER TABLE `users`
  ADD CONSTRAINT `users_ibfk_1` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
