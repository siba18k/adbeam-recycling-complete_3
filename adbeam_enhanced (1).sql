-- phpMyAdmin SQL Dump
-- AdBeam Recycling Project - Complete Database Schema (Fixed)
-- Version: 2.0

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `adbeam_recycling`
--
CREATE DATABASE IF NOT EXISTS `adbeam_recycling` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `adbeam_recycling`;

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

DROP TABLE IF EXISTS `users`;
CREATE TABLE `users` (
  `user_id` int(11) NOT NULL AUTO_INCREMENT,
  `college_email` varchar(255) NOT NULL,
  `email` varchar(255) DEFAULT NULL,
  `password_hash` varchar(255) NOT NULL,
  `email_verified` tinyint(1) DEFAULT 1,
  `email_verification_token` varchar(64) DEFAULT NULL,
  `password_reset_token` varchar(64) DEFAULT NULL,
  `password_reset_expires` timestamp NULL DEFAULT NULL,
  `points_balance` int(11) DEFAULT 0,
  `total_points_earned` int(11) DEFAULT 0,
  `account_status` enum('active','suspended','pending','deleted') DEFAULT 'active',
  `last_login` timestamp NULL DEFAULT NULL,
  `login_count` int(11) DEFAULT 0,
  `failed_login_attempts` int(11) DEFAULT 0,
  `last_failed_login` timestamp NULL DEFAULT NULL,
  `account_locked_until` timestamp NULL DEFAULT NULL,
  `registration_date` timestamp NOT NULL DEFAULT current_timestamp(),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`user_id`),
  UNIQUE KEY `college_email` (`college_email`),
  UNIQUE KEY `email` (`email`),
  KEY `account_status` (`account_status`),
  KEY `email_verification_token` (`email_verification_token`),
  KEY `password_reset_token` (`password_reset_token`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `user_profiles`
--

DROP TABLE IF EXISTS `user_profiles`;
CREATE TABLE `user_profiles` (
  `profile_id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `first_name` varchar(50) DEFAULT NULL,
  `last_name` varchar(50) DEFAULT NULL,
  `display_name` varchar(100) DEFAULT NULL,
  `student_id` varchar(50) DEFAULT NULL,
  `student_number` varchar(50) DEFAULT NULL,
  `department` varchar(100) DEFAULT NULL,
  `university` varchar(100) DEFAULT NULL,
  `university_code` varchar(20) DEFAULT NULL,
  `profile_image` varchar(255) DEFAULT NULL,
  `bio` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`profile_id`),
  UNIQUE KEY `user_id` (`user_id`),
  KEY `student_id` (`student_id`),
  KEY `student_number` (`student_number`),
  KEY `university_code` (`university_code`),
  CONSTRAINT `user_profiles_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `admin_users`
--

DROP TABLE IF EXISTS `admin_users`;
CREATE TABLE `admin_users` (
  `admin_id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `role` varchar(50) DEFAULT 'admin',
  `is_active` tinyint(1) DEFAULT 1,
  `granted_by` int(11) DEFAULT NULL,
  `granted_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `last_access` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`admin_id`),
  UNIQUE KEY `user_id` (`user_id`),
  KEY `granted_by` (`granted_by`),
  CONSTRAINT `admin_users_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE,
  CONSTRAINT `admin_users_ibfk_2` FOREIGN KEY (`granted_by`) REFERENCES `users` (`user_id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `material_types`
--

DROP TABLE IF EXISTS `material_types`;
CREATE TABLE `material_types` (
  `material_id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `points_value` int(11) NOT NULL DEFAULT 0,
  `co2_savings` decimal(10,2) DEFAULT 0.00,
  `icon_url` varchar(255) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`material_id`),
  UNIQUE KEY `name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `recycling_activities`
--

DROP TABLE IF EXISTS `recycling_activities`;
CREATE TABLE `recycling_activities` (
  `activity_id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `material_id` int(11) DEFAULT NULL,
  `barcode` varchar(255) DEFAULT NULL,
  `material_type` varchar(100) DEFAULT NULL,
  `quantity` int(11) DEFAULT 1,
  `points_awarded` int(11) DEFAULT 0,
  `co2_saved` decimal(10,2) DEFAULT 0.00,
  `status` enum('pending','verified','rejected') DEFAULT 'verified',
  `verification_method` varchar(50) DEFAULT 'automatic',
  `verified_by` int(11) DEFAULT NULL,
  `verified_at` timestamp NULL DEFAULT NULL,
  `location_lat` decimal(10,8) DEFAULT NULL,
  `location_lon` decimal(11,8) DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`activity_id`),
  KEY `user_id` (`user_id`),
  KEY `material_id` (`material_id`),
  KEY `barcode` (`barcode`),
  KEY `status` (`status`),
  KEY `verified_by` (`verified_by`),
  CONSTRAINT `recycling_activities_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE,
  CONSTRAINT `recycling_activities_ibfk_2` FOREIGN KEY (`material_id`) REFERENCES `material_types` (`material_id`) ON DELETE SET NULL,
  CONSTRAINT `recycling_activities_ibfk_3` FOREIGN KEY (`verified_by`) REFERENCES `users` (`user_id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `scanned_items` (Alternative name for recycling activities)
--

DROP TABLE IF EXISTS `scanned_items`;
CREATE TABLE `scanned_items` (
  `scan_id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `barcode` varchar(255) NOT NULL,
  `material_type` varchar(100) DEFAULT NULL,
  `points_awarded` int(11) DEFAULT 0,
  `scan_time` timestamp NOT NULL DEFAULT current_timestamp(),
  `ip_address` varchar(45) DEFAULT NULL,
  `status` enum('pending','verified','rejected') DEFAULT 'verified',
  PRIMARY KEY (`scan_id`),
  KEY `user_id` (`user_id`),
  KEY `barcode` (`barcode`),
  KEY `scan_time` (`scan_time`),
  CONSTRAINT `scanned_items_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `scan_logs`
--

DROP TABLE IF EXISTS `scan_logs`;
CREATE TABLE `scan_logs` (
  `log_id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `barcode` varchar(255) DEFAULT NULL,
  `points` int(11) DEFAULT 0,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `timestamp` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`log_id`),
  KEY `user_id` (`user_id`),
  KEY `timestamp` (`timestamp`),
  CONSTRAINT `scan_logs_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `reward_categories`
--

DROP TABLE IF EXISTS `reward_categories`;
CREATE TABLE `reward_categories` (
  `category_id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `icon_url` varchar(255) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `display_order` int(11) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`category_id`),
  UNIQUE KEY `name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `rewards`
--

DROP TABLE IF EXISTS `rewards`;
CREATE TABLE `rewards` (
  `reward_id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `points_cost` int(11) NOT NULL,
  `category_id` int(11) DEFAULT NULL,
  `category` varchar(100) DEFAULT NULL,
  `image_url` varchar(255) DEFAULT NULL,
  `available_inventory` int(11) DEFAULT NULL,
  `inventory` int(11) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `start_date` date DEFAULT NULL,
  `end_date` date DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`reward_id`),
  KEY `category_id` (`category_id`),
  KEY `created_by` (`created_by`),
  KEY `is_active` (`is_active`),
  CONSTRAINT `rewards_ibfk_1` FOREIGN KEY (`category_id`) REFERENCES `reward_categories` (`category_id`) ON DELETE SET NULL,
  CONSTRAINT `rewards_ibfk_2` FOREIGN KEY (`created_by`) REFERENCES `users` (`user_id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `user_reward_redemptions`
--

DROP TABLE IF EXISTS `user_reward_redemptions`;
CREATE TABLE `user_reward_redemptions` (
  `redemption_id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `reward_id` int(11) NOT NULL,
  `points_spent` int(11) NOT NULL,
  `redemption_code` varchar(50) DEFAULT NULL,
  `status` enum('pending','completed','cancelled') DEFAULT 'pending',
  `redeemed_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `completed_at` timestamp NULL DEFAULT NULL,
  `notes` text DEFAULT NULL,
  PRIMARY KEY (`redemption_id`),
  KEY `user_id` (`user_id`),
  KEY `reward_id` (`reward_id`),
  KEY `redemption_code` (`redemption_code`),
  CONSTRAINT `user_reward_redemptions_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE,
  CONSTRAINT `user_reward_redemptions_ibfk_2` FOREIGN KEY (`reward_id`) REFERENCES `rewards` (`reward_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `security_logs`
--

DROP TABLE IF EXISTS `security_logs`;
CREATE TABLE `security_logs` (
  `log_id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) DEFAULT NULL,
  `event_type` varchar(50) NOT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `details` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`details`)),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`log_id`),
  KEY `user_id` (`user_id`),
  KEY `event_type` (`event_type`),
  KEY `created_at` (`created_at`),
  CONSTRAINT `security_logs_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `scanned_barcodes` (Legacy table)
--

DROP TABLE IF EXISTS `scanned_barcodes`;
CREATE TABLE `scanned_barcodes` (
  `scan_id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `barcode` varchar(255) NOT NULL,
  `material_type` varchar(100) DEFAULT NULL,
  `points_awarded` int(11) DEFAULT 0,
  `scan_time` timestamp NOT NULL DEFAULT current_timestamp(),
  `ip_address` varchar(45) DEFAULT NULL,
  PRIMARY KEY (`scan_id`),
  KEY `user_id` (`user_id`),
  KEY `barcode` (`barcode`),
  KEY `scan_time` (`scan_time`),
  CONSTRAINT `scanned_barcodes_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `leaderboard_cache` (For performance)
--

DROP TABLE IF EXISTS `leaderboard_cache`;
CREATE TABLE `leaderboard_cache` (
  `cache_id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `total_points` int(11) DEFAULT 0,
  `total_scans` int(11) DEFAULT 0,
  `rank_position` int(11) DEFAULT NULL,
  `last_updated` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`cache_id`),
  UNIQUE KEY `user_id` (`user_id`),
  KEY `rank_position` (`rank_position`),
  KEY `total_points` (`total_points`),
  CONSTRAINT `leaderboard_cache_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Insert initial data
--

-- Insert material types
INSERT INTO `material_types` (`name`, `description`, `points_value`, `co2_savings`, `is_active`) VALUES
('Plastic Bottle', 'PET plastic bottles', 5, 0.15, 1),
('Aluminum Can', 'Aluminum beverage cans', 7, 0.35, 1),
('Glass Bottle', 'Glass bottles and jars', 10, 0.25, 1),
('Paper', 'Clean paper products', 3, 0.10, 1),
('Cardboard', 'Cardboard boxes and packaging', 4, 0.12, 1),
('Steel Can', 'Steel food cans', 6, 0.20, 1),
('Newspaper', 'Newspaper and magazines', 2, 0.08, 1),
('Plastic Container', 'Other plastic containers', 4, 0.12, 1);

-- Insert reward categories
INSERT INTO `reward_categories` (`name`, `description`, `is_active`, `display_order`) VALUES
('Campus Services', 'Rewards related to campus services', 1, 1),
('Food & Beverage', 'Food and drink rewards', 1, 2),
('Merchandise', 'University branded merchandise', 1, 3),
('Academic', 'Academic-related rewards', 1, 4),
('Experiences', 'Special experiences and events', 1, 5),
('Digital', 'Digital rewards and subscriptions', 1, 6);

-- Insert sample rewards
INSERT INTO `rewards` (`name`, `description`, `points_cost`, `category_id`, `category`, `available_inventory`, `is_active`) VALUES
('Campus Cafe Voucher', '10% discount at campus cafe', 100, 2, 'Food & Beverage', 50, 1),
('University T-Shirt', 'Branded university t-shirt', 250, 3, 'Merchandise', 25, 1),
('Printing Credits', '50 pages of free printing', 75, 4, 'Academic', 100, 1),
('Library Late Fee Waiver', 'One-time waiver for library late fees', 150, 4, 'Academic', NULL, 1),
('Campus Tour Guide', 'Be a tour guide for a day', 300, 5, 'Experiences', 5, 1),
('Coffee Shop Gift Card', '$5 gift card for campus coffee shop', 200, 2, 'Food & Beverage', 30, 1),
('University Mug', 'Branded ceramic mug', 150, 3, 'Merchandise', 40, 1),
('Study Room Booking', 'Priority booking for study rooms', 120, 4, 'Academic', NULL, 1);

-- Insert admin user
INSERT INTO `users` (`college_email`, `email`, `password_hash`, `email_verified`, `points_balance`, `account_status`, `registration_date`) VALUES
('admin@adbeam.com', 'admin@adbeam.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 1, 1000, 'active', NOW());

-- Get the admin user ID
SET @admin_id = LAST_INSERT_ID();

-- Insert admin profile
INSERT INTO `user_profiles` (`user_id`, `first_name`, `last_name`, `display_name`, `student_number`) VALUES
(@admin_id, 'Admin', 'User', 'Admin User', 'ADMIN001');

-- Insert admin privileges
INSERT INTO `admin_users` (`user_id`, `role`, `is_active`) VALUES
(@admin_id, 'super_admin', 1);

-- Insert regular user
INSERT INTO `users` (`college_email`, `email`, `password_hash`, `email_verified`, `points_balance`, `account_status`, `registration_date`) VALUES
('user@student.uj.ac.za', 'user@student.uj.ac.za', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 1, 250, 'active', NOW());

-- Get the regular user ID
SET @user_id = LAST_INSERT_ID();

-- Insert user profile
INSERT INTO `user_profiles` (`user_id`, `first_name`, `last_name`, `display_name`, `student_id`, `student_number`, `university`) VALUES
(@user_id, 'John', 'Doe', 'John Doe', 'STU001', 'STU001', 'University of Johannesburg');

-- Insert some recycling activities
INSERT INTO `recycling_activities` (`user_id`, `material_id`, `barcode`, `material_type`, `points_awarded`, `status`, `created_at`) VALUES
(@user_id, 1, '123456789', 'Plastic Bottle', 15, 'verified', DATE_SUB(NOW(), INTERVAL 3 DAY)),
(@user_id, 2, '987654321', 'Aluminum Can', 20, 'verified', DATE_SUB(NOW(), INTERVAL 2 DAY)),
(@user_id, 3, '456789123', 'Glass Bottle', 10, 'verified', DATE_SUB(NOW(), INTERVAL 1 DAY));

-- Insert scanned items (for compatibility)
INSERT INTO `scanned_items` (`user_id`, `barcode`, `material_type`, `points_awarded`, `scan_time`) VALUES
(@user_id, '123456789', 'plastic', 5, DATE_SUB(NOW(), INTERVAL 5 DAY)),
(@user_id, '987654321', 'aluminum', 7, DATE_SUB(NOW(), INTERVAL 4 DAY)),
(@user_id, '111222333', 'glass', 10, DATE_SUB(NOW(), INTERVAL 3 DAY));

-- Insert legacy scanned barcodes
INSERT INTO `scanned_barcodes` (`user_id`, `barcode`, `material_type`, `points_awarded`, `scan_time`) VALUES
(@user_id, '123456789', 'plastic', 5, DATE_SUB(NOW(), INTERVAL 5 DAY)),
(@user_id, '987654321', 'aluminum', 7, DATE_SUB(NOW(), INTERVAL 4 DAY));

-- Insert security logs
INSERT INTO `security_logs` (`user_id`, `event_type`, `ip_address`, `user_agent`, `created_at`) VALUES
(@admin_id, 'login_success', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36', DATE_SUB(NOW(), INTERVAL 1 DAY)),
(@user_id, 'registration', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36', DATE_SUB(NOW(), INTERVAL 7 DAY));

-- Initialize leaderboard cache
INSERT INTO `leaderboard_cache` (`user_id`, `total_points`, `total_scans`) VALUES
(@admin_id, 1000, 0),
(@user_id, 250, 3);

COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
