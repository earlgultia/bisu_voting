-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: May 05, 2026 at 05:36 AM
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
(11, 'FTP', 'Frankie Galagar', 'FTP President', 'Kahoy2x', 'uploads/1777945566_708e74b232a8b32bc86d14314197b84f.jpg', 1, '2026-05-05 01:46:06'),
(12, 'SSG', 'Earl O. Gultia', 'SSG President', 'Kahoy', 'uploads/1777945604_0fdf235918d88d3cd8927aff3c6d5e93.jpg', 0, '2026-05-05 01:46:44'),
(13, 'SSG', 'KC Alicarte', 'SSG Vice President', 'Bato', 'uploads/1777945843_0fdf235918d88d3cd8927aff3c6d5e93.jpg', 1, '2026-05-05 01:50:43'),
(14, 'FTP', 'Charlyn Curan', 'FTP Vice - President', 'Bato', 'uploads/1777945907_708e74b232a8b32bc86d14314197b84f.jpg', 1, '2026-05-05 01:51:47'),
(15, 'SSG', 'Rodel Tuyor', 'SSG President', 'Mo dagan ko', 'uploads/1777950608_708e74b232a8b32bc86d14314197b84f.jpg', 1, '2026-05-05 03:10:08');

-- --------------------------------------------------------

--
-- Table structure for table `students`
--

CREATE TABLE `students` (
  `id` int(11) NOT NULL,
  `first_name` varchar(50) NOT NULL,
  `last_name` varchar(50) NOT NULL,
  `complete_address` text NOT NULL,
  `age` int(11) NOT NULL,
  `contact_number` varchar(15) NOT NULL,
  `email` varchar(100) NOT NULL,
  `ismis_id` varchar(20) NOT NULL,
  `password` varchar(255) NOT NULL,
  `has_voted` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `students`
--

INSERT INTO `students` (`id`, `first_name`, `last_name`, `complete_address`, `age`, `contact_number`, `email`, `ismis_id`, `password`, `has_voted`, `created_at`) VALUES
(1, 'Admin', 'SSG', 'BISU Main Campus', 0, '0000000000', 'admin.ssg@bisu.edu.ph', 'ADMIN001', '$2y$10$xQEhzUv7m83/zGNdZ6kQk.bnDPKncFcvyBIqvPt40Ts/phDlsznwO', 1, '2026-05-03 15:24:18'),
(6, 'Earl', 'Gultia', 'Purok 3, Alejawan, Duero, Bohol', 0, '', 'earl.gultia@bisu.edu.ph', '', '$2y$10$9hvHwvENMkIoBTUe.AiL9.MDujrVrrvoQwFRVroS6e6snssDZvSV6', 1, '2026-05-04 15:20:41');

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
-- Dumping data for table `votes`
--

INSERT INTO `votes` (`id`, `student_id`, `candidate_id`, `voted_at`) VALUES
(1, 6, 15, '2026-05-05 03:12:44'),
(2, 6, 13, '2026-05-05 03:12:44'),
(3, 6, 11, '2026-05-05 03:12:44'),
(4, 6, 14, '2026-05-05 03:12:44');

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
  ADD UNIQUE KEY `email` (`email`),
  ADD UNIQUE KEY `ismis_id` (`ismis_id`);

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
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `votes`
--
ALTER TABLE `votes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

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
