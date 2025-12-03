-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Dec 03, 2025 at 12:29 PM
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
-- Database: `admin_dashboard`
--

-- --------------------------------------------------------

--
-- Table structure for table `profile_images`
--

CREATE TABLE `profile_images` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `image_path` varchar(255) NOT NULL,
  `upload_date` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `profile_images`
--

INSERT INTO `profile_images` (`id`, `user_id`, `image_path`, `upload_date`) VALUES
(4, 13, 'uploads/profile_13_1764760693.jpg', '2025-12-03 04:18:13'),
(5, 5, 'uploads/profile_5_1764760709.jpg', '2025-12-03 04:18:29');

-- --------------------------------------------------------

--
-- Table structure for table `roles`
--

CREATE TABLE `roles` (
  `id` int(11) NOT NULL,
  `role_name` varchar(50) NOT NULL,
  `permissions` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `roles`
--

INSERT INTO `roles` (`id`, `role_name`, `permissions`, `created_at`) VALUES
(1, 'Administrator', 'Manage Users, Settings, Analytics', '2025-12-01 13:40:41'),
(2, 'Moderator', 'Manage Content', '2025-12-01 13:40:41'),
(3, 'User', 'View Content', '2025-12-01 13:40:41'),
(4, 'Guest', 'View Public Content', '2025-12-01 13:40:41'),
(5, 'Administrator', 'Manage Users, Settings, Analytics', '2025-12-01 13:42:06'),
(6, 'Moderator', 'Manage Content', '2025-12-01 13:42:06'),
(7, 'User', 'View Content', '2025-12-01 13:42:06'),
(8, 'Guest', 'View Public Content', '2025-12-01 13:42:06'),
(9, 'Admin', '[\"view_dashboard\", \"manage_users\", \"edit_roles\"]', '2025-12-01 13:50:04'),
(10, 'Editor', '[\"view_dashboard\", \"edit_content\"]', '2025-12-01 13:50:04'),
(11, 'Viewer', '[\"view_dashboard\"]', '2025-12-01 13:50:04'),
(12, 'Workingd', 'read,create', '2025-12-03 06:27:44'),
(13, 'Binghay', 'Carl, David', '2025-12-03 06:28:54'),
(14, 'Carl David', '', '2025-12-03 06:49:54'),
(15, 'janny', '', '2025-12-03 07:27:49'),
(16, 'barats', '', '2025-12-03 07:32:08'),
(17, 'keyses', 'Faculty', '2025-12-03 07:37:09'),
(18, 'sad', 'Admin,Student,Guest', '2025-12-03 07:41:39'),
(19, 'inanyo', 'Staff,Student,Faculty,Guest,Moderator,Editor,Viewer', '2025-12-03 07:42:00'),
(20, 'asdasd', 'Admin,Staff,Student,Guest,Editor,Viewer', '2025-12-03 07:43:02');

-- --------------------------------------------------------

--
-- Table structure for table `students`
--

CREATE TABLE `students` (
  `id` int(11) NOT NULL,
  `student_id` varchar(50) NOT NULL,
  `first_name` varchar(100) NOT NULL,
  `last_name` varchar(100) NOT NULL,
  `email` varchar(150) NOT NULL,
  `grade` varchar(20) DEFAULT NULL,
  `section` varchar(50) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `students`
--

INSERT INTO `students` (`id`, `student_id`, `first_name`, `last_name`, `email`, `grade`, `section`, `created_at`, `updated_at`) VALUES
(1, '12345678', 'Carl', 'lee', 'limcarldavid206@gmail.com', '', 'A', '2025-12-03 08:55:41', '2025-12-03 08:55:41'),
(3, 'uytre', 'ds', 'qwerty', '12@gmail.com', 'Grade 8', 'A', '2025-12-03 09:30:41', '2025-12-03 09:30:41'),
(4, 'qwerttt', 'ewew', 'ytyt', 'admi434n@bukidnon.com', 'Grade 11', 'A', '2025-12-03 09:31:01', '2025-12-03 09:31:01');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `username` varchar(255) NOT NULL,
  `email` varchar(255) NOT NULL,
  `password` varchar(255) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `role` enum('Administrator','User','Student','Staff') NOT NULL DEFAULT 'User'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `username`, `email`, `password`, `created_at`, `role`) VALUES
(5, 'Admin2', 'bukidnon1@admin.com', '$2y$10$kasRaBxhi2xZ4rvH0f9XAuo.u7wzdkkGM8KLNxf882bYx9Vf6rKZS', '2025-12-01 12:58:18', 'Administrator');

-- --------------------------------------------------------

--
-- Table structure for table `user_documents`
--

CREATE TABLE `user_documents` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `document_name` varchar(255) NOT NULL,
  `document_path` varchar(255) NOT NULL,
  `file_size` int(11) NOT NULL,
  `upload_date` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `profile_images`
--
ALTER TABLE `profile_images`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `roles`
--
ALTER TABLE `roles`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `students`
--
ALTER TABLE `students`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `student_id` (`student_id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD UNIQUE KEY `email` (`email`);

--
-- Indexes for table `user_documents`
--
ALTER TABLE `user_documents`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_user_id` (`user_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `profile_images`
--
ALTER TABLE `profile_images`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `roles`
--
ALTER TABLE `roles`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=25;

--
-- AUTO_INCREMENT for table `students`
--
ALTER TABLE `students`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `user_documents`
--
ALTER TABLE `user_documents`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `user_documents`
--
ALTER TABLE `user_documents`
  ADD CONSTRAINT `user_documents_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
