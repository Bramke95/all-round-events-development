-- phpMyAdmin SQL Dump
-- version 5.1.3
-- https://www.phpmyadmin.net/
--
-- Host: all-round-events.be.mysql.service.one.com:3306
-- Gegenereerd op: 07 apr 2022 om 15:23
-- Serverversie: 10.3.34-MariaDB-1:10.3.34+maria~focal
-- PHP-versie: 7.2.24-0ubuntu0.18.04.11

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `all_round_events_be_events`
--

-- --------------------------------------------------------

--
-- Tabelstructuur voor tabel `Activation_codes`
--

CREATE TABLE `Activation_codes` (
  `idActivation_codes` int(11) NOT NULL,
  `code` int(11) DEFAULT NULL,
  `amount` int(11) DEFAULT NULL,
  `closed` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Tabelstructuur voor tabel `educations`
--

CREATE TABLE `educations` (
  `ideducations_id` int(11) NOT NULL,
  `from_date` varchar(15) DEFAULT NULL,
  `to_date` varchar(15) DEFAULT NULL,
  `school` varchar(45) DEFAULT NULL,
  `education` varchar(45) DEFAULT NULL,
  `percentage` int(11) DEFAULT NULL,
  `users_Id_Users` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Tabelstructuur voor tabel `expierence`
--

CREATE TABLE `expierence` (
  `idexpierence` int(11) NOT NULL,
  `compamy` varchar(45) DEFAULT NULL,
  `jobtitle` varchar(45) DEFAULT NULL,
  `from_date` varchar(45) DEFAULT NULL,
  `to_date` varchar(45) DEFAULT NULL,
  `users_Id_Users` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Tabelstructuur voor tabel `external_appointment`
--

CREATE TABLE `external_appointment` (
  `external_appointment_id` int(11) NOT NULL,
  `location_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `present` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Tabelstructuur voor tabel `festivals`
--

CREATE TABLE `festivals` (
  `idfestival` int(11) NOT NULL,
  `date` datetime NOT NULL,
  `full_shifts` int(11) NOT NULL,
  `details` varchar(2000) DEFAULT NULL,
  `status` int(11) DEFAULT NULL,
  `name` varchar(300) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Tabelstructuur voor tabel `hashess`
--

CREATE TABLE `hashess` (
  `idhashess_id` int(11) NOT NULL,
  `HASH` varchar(150) DEFAULT NULL,
  `Type` int(11) DEFAULT NULL,
  `last_used` varchar(45) DEFAULT NULL,
  `begin_date` varchar(45) DEFAULT current_timestamp(),
  `end_date` varchar(45) DEFAULT NULL,
  `users_Id_Users` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Tabelstructuur voor tabel `Images`
--

CREATE TABLE `Images` (
  `idImages` int(11) NOT NULL,
  `picture_name` varchar(100) DEFAULT NULL,
  `is_primary` int(11) DEFAULT NULL,
  `users_Id_Users` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Tabelstructuur voor tabel `language`
--

CREATE TABLE `language` (
  `language_id` int(11) NOT NULL,
  `language` varchar(45) DEFAULT NULL,
  `speaking` varchar(45) DEFAULT NULL,
  `writing` varchar(45) DEFAULT NULL,
  `reading` varchar(45) DEFAULT NULL,
  `users_Id_Users` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Tabelstructuur voor tabel `locations`
--

CREATE TABLE `locations` (
  `location_id` int(11) NOT NULL,
  `appointment_time` datetime DEFAULT NULL,
  `location` varchar(1000) NOT NULL,
  `shift_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Tabelstructuur voor tabel `logs`
--

CREATE TABLE `logs` (
  `id` int(11) NOT NULL,
  `api` text DEFAULT NULL,
  `data` text DEFAULT NULL,
  `user_id` text DEFAULT NULL,
  `ip` text DEFAULT NULL,
  `timestamp` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Tabelstructuur voor tabel `mails`
--

CREATE TABLE `mails` (
  `mail_id` int(11) NOT NULL,
  `address` varchar(500) NOT NULL,
  `subject` varchar(500) NOT NULL,
  `mail_text` varchar(15000) NOT NULL,
  `mail_headers` varchar(500) NOT NULL,
  `send_request` timestamp NOT NULL DEFAULT current_timestamp(),
  `send_process` timestamp NULL DEFAULT NULL,
  `prio` int(11) DEFAULT 3
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Tabelstructuur voor tabel `notifications`
--

CREATE TABLE `notifications` (
  `id` int(11) NOT NULL,
  `notification` text NOT NULL,
  `global` tinyint(1) NOT NULL,
  `user_id` int(11) NOT NULL,
  `data` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Tabelstructuur voor tabel `settings`
--

CREATE TABLE `settings` (
  `mail_interval_time` int(11) NOT NULL,
  `mails_per_interval` int(11) NOT NULL,
  `max_mail_logs` int(11) NOT NULL,
  `max_api_logs` int(11) NOT NULL,
  `service_disabled` int(11) NOT NULL,
  `settings_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Tabelstructuur voor tabel `shifts`
--

CREATE TABLE `shifts` (
  `idshifts` int(11) NOT NULL,
  `name` varchar(400) DEFAULT NULL,
  `datails` varchar(1000) DEFAULT NULL,
  `length` int(11) DEFAULT NULL,
  `people_needed` int(11) DEFAULT NULL,
  `spare_needed` int(11) DEFAULT NULL,
  `festival_idfestival` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Tabelstructuur voor tabel `shift_days`
--

CREATE TABLE `shift_days` (
  `idshift_days` int(11) NOT NULL,
  `cost` int(11) DEFAULT NULL,
  `length` int(11) DEFAULT NULL,
  `shift_dayscol` varchar(45) DEFAULT NULL,
  `shifts_idshifts` int(11) NOT NULL,
  `start_date` datetime DEFAULT NULL,
  `shift_end` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Tabelstructuur voor tabel `users`
--

CREATE TABLE `users` (
  `Id_Users` int(11) NOT NULL,
  `email` varchar(50) DEFAULT NULL,
  `pass` varchar(100) DEFAULT NULL,
  `salt` varchar(100) DEFAULT NULL,
  `is_admin` int(11) DEFAULT NULL,
  `subscribed` int(11) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Tabelstructuur voor tabel `users_data`
--

CREATE TABLE `users_data` (
  `name` varchar(50) DEFAULT NULL,
  `size` varchar(15) DEFAULT NULL,
  `date_of_birth` datetime DEFAULT NULL,
  `Gender` int(11) DEFAULT NULL,
  `adres_line_one` varchar(100) DEFAULT NULL,
  `adres_line_two` varchar(100) DEFAULT NULL,
  `driver_license` varchar(100) DEFAULT NULL,
  `nationality` varchar(25) DEFAULT NULL,
  `telephone` varchar(45) DEFAULT NULL,
  `marital_state` int(11) DEFAULT NULL,
  `text` varchar(2000) DEFAULT NULL,
  `users_Id_Users` int(11) NOT NULL,
  `employment` int(11) DEFAULT NULL,
  `users_data_id` int(11) NOT NULL,
  `blocked` int(11) NOT NULL DEFAULT 0,
  `remarks` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Tabelstructuur voor tabel `work_day`
--

CREATE TABLE `work_day` (
  `idwork_day` int(11) NOT NULL,
  `present` int(11) DEFAULT NULL,
  `reservation_type` varchar(45) DEFAULT NULL,
  `shift_days_idshift_days` int(11) NOT NULL,
  `users_Id_Users` int(11) NOT NULL,
  `in` int(11) DEFAULT NULL,
  `out` int(11) DEFAULT NULL,
  `Payout` int(11) DEFAULT NULL,
  `friend` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

--
-- Indexen voor geëxporteerde tabellen
--

--
-- Indexen voor tabel `Activation_codes`
--
ALTER TABLE `Activation_codes`
  ADD PRIMARY KEY (`idActivation_codes`),
  ADD UNIQUE KEY `idActivation_codes_UNIQUE` (`idActivation_codes`),
  ADD UNIQUE KEY `code_UNIQUE` (`code`);

--
-- Indexen voor tabel `educations`
--
ALTER TABLE `educations`
  ADD PRIMARY KEY (`ideducations_id`),
  ADD UNIQUE KEY `ideducations_id_UNIQUE` (`ideducations_id`),
  ADD KEY `fk_educations_users_idx` (`users_Id_Users`);

--
-- Indexen voor tabel `expierence`
--
ALTER TABLE `expierence`
  ADD PRIMARY KEY (`idexpierence`),
  ADD UNIQUE KEY `idexpierence_UNIQUE` (`idexpierence`),
  ADD KEY `fk_expierence_users1_idx` (`users_Id_Users`);

--
-- Indexen voor tabel `external_appointment`
--
ALTER TABLE `external_appointment`
  ADD PRIMARY KEY (`external_appointment_id`);

--
-- Indexen voor tabel `festivals`
--
ALTER TABLE `festivals`
  ADD PRIMARY KEY (`idfestival`);

--
-- Indexen voor tabel `hashess`
--
ALTER TABLE `hashess`
  ADD PRIMARY KEY (`idhashess_id`),
  ADD UNIQUE KEY `idhashess_id_UNIQUE` (`idhashess_id`),
  ADD KEY `fk_hashess_users1_idx` (`users_Id_Users`);

--
-- Indexen voor tabel `Images`
--
ALTER TABLE `Images`
  ADD PRIMARY KEY (`idImages`),
  ADD UNIQUE KEY `idImages_UNIQUE` (`idImages`),
  ADD KEY `fk_Images_users1_idx` (`users_Id_Users`);

--
-- Indexen voor tabel `language`
--
ALTER TABLE `language`
  ADD PRIMARY KEY (`language_id`),
  ADD UNIQUE KEY `language_id_UNIQUE` (`language_id`),
  ADD KEY `fk_language_users1_idx` (`users_Id_Users`);

--
-- Indexen voor tabel `locations`
--
ALTER TABLE `locations`
  ADD PRIMARY KEY (`location_id`);

--
-- Indexen voor tabel `logs`
--
ALTER TABLE `logs`
  ADD PRIMARY KEY (`id`);

--
-- Indexen voor tabel `mails`
--
ALTER TABLE `mails`
  ADD PRIMARY KEY (`mail_id`);

--
-- Indexen voor tabel `notifications`
--
ALTER TABLE `notifications`
  ADD PRIMARY KEY (`id`);

--
-- Indexen voor tabel `settings`
--
ALTER TABLE `settings`
  ADD PRIMARY KEY (`settings_id`);

--
-- Indexen voor tabel `shifts`
--
ALTER TABLE `shifts`
  ADD PRIMARY KEY (`idshifts`),
  ADD KEY `fk_shifts_festival1_idx` (`festival_idfestival`);

--
-- Indexen voor tabel `shift_days`
--
ALTER TABLE `shift_days`
  ADD PRIMARY KEY (`idshift_days`),
  ADD KEY `fk_shift_days_shifts1_idx` (`shifts_idshifts`);

--
-- Indexen voor tabel `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`Id_Users`),
  ADD UNIQUE KEY `Id_Users_UNIQUE` (`Id_Users`),
  ADD UNIQUE KEY `E-mail_UNIQUE` (`email`);

--
-- Indexen voor tabel `users_data`
--
ALTER TABLE `users_data`
  ADD PRIMARY KEY (`users_data_id`),
  ADD KEY `fk_users_data_users1_idx` (`users_Id_Users`);

--
-- Indexen voor tabel `work_day`
--
ALTER TABLE `work_day`
  ADD PRIMARY KEY (`idwork_day`),
  ADD KEY `fk_work_day_shift_days1_idx` (`shift_days_idshift_days`),
  ADD KEY `fk_work_day_users1_idx` (`users_Id_Users`);

--
-- AUTO_INCREMENT voor geëxporteerde tabellen
--

--
-- AUTO_INCREMENT voor een tabel `Activation_codes`
--
ALTER TABLE `Activation_codes`
  MODIFY `idActivation_codes` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT voor een tabel `educations`
--
ALTER TABLE `educations`
  MODIFY `ideducations_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT voor een tabel `expierence`
--
ALTER TABLE `expierence`
  MODIFY `idexpierence` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT voor een tabel `external_appointment`
--
ALTER TABLE `external_appointment`
  MODIFY `external_appointment_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT voor een tabel `festivals`
--
ALTER TABLE `festivals`
  MODIFY `idfestival` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT voor een tabel `hashess`
--
ALTER TABLE `hashess`
  MODIFY `idhashess_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT voor een tabel `Images`
--
ALTER TABLE `Images`
  MODIFY `idImages` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT voor een tabel `language`
--
ALTER TABLE `language`
  MODIFY `language_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT voor een tabel `locations`
--
ALTER TABLE `locations`
  MODIFY `location_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT voor een tabel `logs`
--
ALTER TABLE `logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT voor een tabel `mails`
--
ALTER TABLE `mails`
  MODIFY `mail_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT voor een tabel `notifications`
--
ALTER TABLE `notifications`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT voor een tabel `settings`
--
ALTER TABLE `settings`
  MODIFY `settings_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT voor een tabel `shifts`
--
ALTER TABLE `shifts`
  MODIFY `idshifts` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT voor een tabel `shift_days`
--
ALTER TABLE `shift_days`
  MODIFY `idshift_days` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT voor een tabel `users`
--
ALTER TABLE `users`
  MODIFY `Id_Users` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT voor een tabel `users_data`
--
ALTER TABLE `users_data`
  MODIFY `users_data_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT voor een tabel `work_day`
--
ALTER TABLE `work_day`
  MODIFY `idwork_day` int(11) NOT NULL AUTO_INCREMENT;

--
-- Beperkingen voor geëxporteerde tabellen
--

--
-- Beperkingen voor tabel `educations`
--
ALTER TABLE `educations`
  ADD CONSTRAINT `fk_educations_users` FOREIGN KEY (`users_Id_Users`) REFERENCES `users` (`Id_Users`) ON DELETE NO ACTION ON UPDATE NO ACTION;

--
-- Beperkingen voor tabel `expierence`
--
ALTER TABLE `expierence`
  ADD CONSTRAINT `fk_expierence_users1` FOREIGN KEY (`users_Id_Users`) REFERENCES `users` (`Id_Users`) ON DELETE NO ACTION ON UPDATE NO ACTION;

--
-- Beperkingen voor tabel `hashess`
--
ALTER TABLE `hashess`
  ADD CONSTRAINT `fk_hashess_users1` FOREIGN KEY (`users_Id_Users`) REFERENCES `users` (`Id_Users`) ON DELETE NO ACTION ON UPDATE NO ACTION;

--
-- Beperkingen voor tabel `Images`
--
ALTER TABLE `Images`
  ADD CONSTRAINT `fk_Images_users1` FOREIGN KEY (`users_Id_Users`) REFERENCES `users` (`Id_Users`) ON DELETE NO ACTION ON UPDATE NO ACTION;

--
-- Beperkingen voor tabel `language`
--
ALTER TABLE `language`
  ADD CONSTRAINT `fk_language_users1` FOREIGN KEY (`users_Id_Users`) REFERENCES `users` (`Id_Users`) ON DELETE NO ACTION ON UPDATE NO ACTION;

--
-- Beperkingen voor tabel `shifts`
--
ALTER TABLE `shifts`
  ADD CONSTRAINT `fk_shifts_festival1` FOREIGN KEY (`festival_idfestival`) REFERENCES `festivals` (`idfestival`) ON DELETE NO ACTION ON UPDATE NO ACTION;

--
-- Beperkingen voor tabel `shift_days`
--
ALTER TABLE `shift_days`
  ADD CONSTRAINT `fk_shift_days_shifts1` FOREIGN KEY (`shifts_idshifts`) REFERENCES `shifts` (`idshifts`) ON DELETE NO ACTION ON UPDATE NO ACTION;

--
-- Beperkingen voor tabel `users_data`
--
ALTER TABLE `users_data`
  ADD CONSTRAINT `fk_users_data_users1` FOREIGN KEY (`users_Id_Users`) REFERENCES `users` (`Id_Users`) ON DELETE NO ACTION ON UPDATE NO ACTION;

--
-- Beperkingen voor tabel `work_day`
--
ALTER TABLE `work_day`
  ADD CONSTRAINT `fk_work_day_shift_days1` FOREIGN KEY (`shift_days_idshift_days`) REFERENCES `shift_days` (`idshift_days`) ON DELETE NO ACTION ON UPDATE NO ACTION,
  ADD CONSTRAINT `fk_work_day_users1` FOREIGN KEY (`users_Id_Users`) REFERENCES `users` (`Id_Users`) ON DELETE NO ACTION ON UPDATE NO ACTION;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
