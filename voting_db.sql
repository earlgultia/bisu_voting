-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: May 05, 2026 at 10:05 AM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `voting_db`
--

-- --------------------------------------------------------

--
-- Table structure for table `candidates`
--

CREATE TABLE `candidates` (
  `id` int(11) NOT NULL,
  `election_type` varchar(20) NOT NULL DEFAULT 'SSG',
  `name` varchar(100) NOT NULL,
  `position` varchar(50) NOT NULL,
  `details` text DEFAULT NULL,
  `picture` varchar(255) DEFAULT NULL,
  `votes_count` int(11) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `candidates`
--

INSERT INTO `candidates` (`id`, `election_type`, `name`, `position`, `details`, `picture`, `votes_count`, `created_at`) VALUES
(11, 'FTP', 'Frankie Galagar', 'FTP President', 'Kahoy2x', 'uploads/1777945566_708e74b232a8b32bc86d14314197b84f.jpg', 0, '2026-05-05 01:46:06'),
(12, 'SSG', 'Earl O. Gultia', 'SSG President', 'Kahoy', 'uploads/1777945604_0fdf235918d88d3cd8927aff3c6d5e93.jpg', 0, '2026-05-05 01:46:44'),
(13, 'SSG', 'KC Alicarte', 'SSG Vice President', 'Bato', 'uploads/1777945843_0fdf235918d88d3cd8927aff3c6d5e93.jpg', 0, '2026-05-05 01:50:43'),
(14, 'FTP', 'Charlyn Curan', 'FTP Vice - President', 'Bato', 'uploads/1777945907_708e74b232a8b32bc86d14314197b84f.jpg', 0, '2026-05-05 01:51:47'),
(15, 'SSG', 'Rodel Tuyor', 'SSG President', 'Mo dagan ko', 'uploads/1777950608_708e74b232a8b32bc86d14314197b84f.jpg', 0, '2026-05-05 03:10:08');

-- --------------------------------------------------------

--
-- Table structure for table `students`
--

CREATE TABLE `students` (
  `id` int(11) NOT NULL,
  `first_name` varchar(50) NOT NULL,
  `last_name` varchar(50) NOT NULL,
  `complete_address` text NOT NULL,
  `email_hash` char(64) NOT NULL,
  `password` varchar(255) NOT NULL,
  `has_voted` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `college` enum('College of Science','College of Business Management','College of Fisheries and Marine Sciences','College of Teachers Education') NOT NULL,
  `course` varchar(100) NOT NULL,
  `is_admin` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `students`
--

INSERT INTO `students` (`id`, `first_name`, `last_name`, `complete_address`, `email_hash`, `password`, `has_voted`, `created_at`, `college`, `course`, `is_admin`) VALUES
(1, 'Admin', 'SSG', 'BISU Main Campus', 'fa729de9cb164d05a5e906c220b1db521e71dd0faf5aa5bbfe0fdecc7f2adc1f', '$2y$10$xQEhzUv7m83/zGNdZ6kQk.bnDPKncFcvyBIqvPt40Ts/phDlsznwO', 0, '2026-05-03 15:24:18', 'College of Science', '', 1);

-- --------------------------------------------------------

--
-- Table structure for table `votes`
--

CREATE TABLE `votes` (
  `id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `candidate_id` int(11) NOT NULL,
  `voted_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `candidates`
--
ALTER TABLE `candidates`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `students`
--
ALTER TABLE `students`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email_hash`);

--
-- Indexes for table `votes`
--
ALTER TABLE `votes`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_vote` (`student_id`,`candidate_id`),
  ADD KEY `candidate_id` (`candidate_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `candidates`
--
ALTER TABLE `candidates`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=16;

--
-- AUTO_INCREMENT for table `students`
--
ALTER TABLE `students`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `votes`
--
ALTER TABLE `votes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `votes`
--
ALTER TABLE `votes`
  ADD CONSTRAINT `votes_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`),
  ADD CONSTRAINT `votes_ibfk_2` FOREIGN KEY (`candidate_id`) REFERENCES `candidates` (`id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
