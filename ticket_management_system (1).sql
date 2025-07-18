-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Jul 18, 2025 at 01:31 AM
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
-- Database: `ticket_management_system`
--

-- --------------------------------------------------------

--
-- Table structure for table `agent_scans`
--

CREATE TABLE `agent_scans` (
  `id` int(11) NOT NULL,
  `agent_id` int(11) DEFAULT NULL,
  `ticket_id` int(11) DEFAULT NULL,
  `scan_time` timestamp NOT NULL DEFAULT current_timestamp(),
  `status` enum('valid','invalid','duplicate') DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `api_keys`
--

CREATE TABLE `api_keys` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `api_key` varchar(255) NOT NULL,
  `name` varchar(100) NOT NULL,
  `permissions` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`permissions`)),
  `is_active` tinyint(1) DEFAULT 1,
  `last_used_at` datetime DEFAULT NULL,
  `expires_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `audit_log`
--

CREATE TABLE `audit_log` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `action` varchar(100) NOT NULL,
  `table_name` varchar(50) DEFAULT NULL,
  `record_id` int(11) DEFAULT NULL,
  `old_values` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`old_values`)),
  `new_values` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`new_values`)),
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `bookings`
--

CREATE TABLE `bookings` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `event_id` int(11) NOT NULL,
  `quantity` int(11) NOT NULL DEFAULT 1,
  `status` enum('pending','confirmed','canceled') DEFAULT 'pending',
  `expiry_time` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `booking_items`
--

CREATE TABLE `booking_items` (
  `id` int(11) NOT NULL,
  `booking_id` int(11) NOT NULL,
  `recipient_name` varchar(100) DEFAULT NULL,
  `recipient_email` varchar(100) DEFAULT NULL,
  `recipient_phone` varchar(20) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `cart`
--

CREATE TABLE `cart` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `cart`
--

INSERT INTO `cart` (`id`, `user_id`, `created_at`, `updated_at`) VALUES
(1, 2, '2025-07-17 20:43:19', '2025-07-17 20:43:19');

-- --------------------------------------------------------

--
-- Table structure for table `cart_items`
--

CREATE TABLE `cart_items` (
  `id` int(11) NOT NULL,
  `cart_id` int(11) NOT NULL,
  `event_id` int(11) NOT NULL,
  `ticket_type_id` int(11) DEFAULT NULL,
  `quantity` int(11) NOT NULL DEFAULT 1,
  `recipient_name` varchar(100) DEFAULT NULL,
  `recipient_email` varchar(100) DEFAULT NULL,
  `recipient_phone` varchar(20) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `deposits`
--

CREATE TABLE `deposits` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `payment_method` varchar(50) NOT NULL,
  `reference_number` varchar(100) DEFAULT NULL,
  `status` enum('pending','completed','failed') DEFAULT 'pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `email_logs`
--

CREATE TABLE `email_logs` (
  `id` int(11) NOT NULL,
  `recipient_email` varchar(100) NOT NULL,
  `subject` varchar(255) NOT NULL,
  `message` text NOT NULL,
  `status` enum('sent','failed','pending') NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `events`
--

CREATE TABLE `events` (
  `id` int(11) NOT NULL,
  `planner_id` int(11) NOT NULL,
  `category_id` int(11) DEFAULT NULL,
  `title` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `category` varchar(50) DEFAULT NULL,
  `venue` varchar(100) DEFAULT NULL,
  `address` varchar(255) DEFAULT NULL,
  `city` varchar(50) DEFAULT NULL,
  `country` varchar(50) DEFAULT NULL,
  `start_date` date NOT NULL,
  `end_date` date NOT NULL,
  `start_time` time NOT NULL,
  `end_time` time NOT NULL,
  `total_tickets` int(11) NOT NULL,
  `available_tickets` int(11) NOT NULL,
  `ticket_price` decimal(10,2) NOT NULL,
  `image` varchar(255) DEFAULT NULL,
  `status` enum('active','completed','canceled','suspended') DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `events`
--

INSERT INTO `events` (`id`, `planner_id`, `category_id`, `title`, `description`, `category`, `venue`, `address`, `city`, `country`, `start_date`, `end_date`, `start_time`, `end_time`, `total_tickets`, `available_tickets`, `ticket_price`, `image`, `status`, `created_at`, `updated_at`) VALUES
(11, 3, NULL, 'Music Concert', 'Experience an enchanting night of jazz, soul, and fusion by Rwanda’s finest jazz musicians, including live saxophone and piano duets. Food and drinks are available.', 'Festival', 'Kigali Serena Hotel', 'KN 3 Ave', 'kigali', 'Rwanda', '2025-07-18', '2025-07-18', '19:00:00', '23:00:00', 150, 144, 0.00, '../../uploads/events/1752785746_download (4).jpeg', 'active', '2025-07-17 20:52:12', '2025-07-17 23:01:05'),
(12, 3, NULL, 'Technology', 'A three-day event that showcases the best of Rwanda’s tech startups, innovators, and digital transformation initiatives. Panels, exhibitions, networking, and product launches.', 'Festival', 'Kigali Convention Centre', 'KG 2 Roundabout', 'kigali', 'Rwanda', '2025-10-15', '2025-10-17', '21:00:00', '18:00:00', 500, 495, 0.00, '../../uploads/events/1752785724_images.jpeg', 'active', '2025-07-17 20:55:25', '2025-07-17 23:18:53'),
(13, 3, NULL, 'Women in Business Forum 2025', 'A networking event for female entrepreneurs and professionals focused on leadership, investment opportunities, and success stories in East Africa.', 'Conference', 'Marriott Hotel Kigali', 'KN 3 Ave', 'kigali', 'Rwanda', '2025-07-17', '2025-07-17', '08:00:00', '23:59:00', 150, 150, 0.00, '../../uploads/events/1752786167_download (7).jpeg', 'active', '2025-07-17 21:02:47', '2025-07-17 21:50:44');

-- --------------------------------------------------------

--
-- Table structure for table `event_analytics`
--

CREATE TABLE `event_analytics` (
  `id` int(11) NOT NULL,
  `event_id` int(11) NOT NULL,
  `date` date NOT NULL,
  `page_views` int(11) DEFAULT 0,
  `unique_visitors` int(11) DEFAULT 0,
  `tickets_viewed` int(11) DEFAULT 0,
  `tickets_sold` int(11) DEFAULT 0,
  `revenue` decimal(10,2) DEFAULT 0.00,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `event_categories`
--

CREATE TABLE `event_categories` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `color` varchar(7) DEFAULT '#3B82F6',
  `icon` varchar(50) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `event_categories`
--

INSERT INTO `event_categories` (`id`, `name`, `description`, `color`, `icon`, `is_active`, `created_at`, `updated_at`) VALUES
(1, 'Music', 'Concerts, festivals, and musical performances', '#EF4444', 'fas fa-music', 1, '2025-07-17 20:24:51', '2025-07-17 20:24:51'),
(2, 'Sports', 'Sports events and competitions', '#10B981', 'fas fa-futbol', 1, '2025-07-17 20:24:51', '2025-07-17 20:24:51'),
(3, 'Business', 'Conferences, seminars, and business events', '#3B82F6', 'fas fa-briefcase', 1, '2025-07-17 20:24:51', '2025-07-17 20:24:51'),
(4, 'Education', 'Workshops, training, and educational events', '#8B5CF6', 'fas fa-graduation-cap', 1, '2025-07-17 20:24:51', '2025-07-17 20:24:51'),
(5, 'Entertainment', 'Movies, shows, and entertainment events', '#F59E0B', 'fas fa-theater-masks', 1, '2025-07-17 20:24:51', '2025-07-17 20:24:51'),
(6, 'Technology', 'Tech conferences and IT events', '#06B6D4', 'fas fa-microchip', 1, '2025-07-17 20:24:51', '2025-07-17 20:24:51'),
(7, 'Food & Drink', 'Food festivals, wine tastings, and culinary events', '#84CC16', 'fas fa-utensils', 1, '2025-07-17 20:24:51', '2025-07-17 20:24:51'),
(8, 'Art & Culture', 'Art exhibitions, museums, and cultural events', '#EC4899', 'fas fa-palette', 1, '2025-07-17 20:24:51', '2025-07-17 20:24:51');

-- --------------------------------------------------------

--
-- Table structure for table `event_tags`
--

CREATE TABLE `event_tags` (
  `id` int(11) NOT NULL,
  `name` varchar(50) NOT NULL,
  `color` varchar(7) DEFAULT '#6B7280',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `event_tag_relations`
--

CREATE TABLE `event_tag_relations` (
  `id` int(11) NOT NULL,
  `event_id` int(11) NOT NULL,
  `tag_id` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `event_waitlist`
--

CREATE TABLE `event_waitlist` (
  `id` int(11) NOT NULL,
  `event_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `ticket_type_id` int(11) DEFAULT NULL,
  `quantity` int(11) DEFAULT 1,
  `status` enum('waiting','notified','expired') DEFAULT 'waiting',
  `notified_at` datetime DEFAULT NULL,
  `expires_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `feedback`
--

CREATE TABLE `feedback` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `name` varchar(255) NOT NULL,
  `email` varchar(255) NOT NULL,
  `subject` varchar(255) NOT NULL,
  `message` text NOT NULL,
  `rating` tinyint(4) DEFAULT NULL CHECK (`rating` >= 1 and `rating` <= 5),
  `status` enum('pending','reviewed','resolved') DEFAULT 'pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `notifications`
--

CREATE TABLE `notifications` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `title` varchar(100) NOT NULL,
  `message` text NOT NULL,
  `type` enum('event_reminder','payment','ticket','system') NOT NULL,
  `is_read` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `notifications`
--

INSERT INTO `notifications` (`id`, `user_id`, `title`, `message`, `type`, `is_read`, `created_at`) VALUES
(1, 2, 'Ticket Purchased: Regular', 'You have successfully purchased a Regular for Music Concert. Check your email for details.', 'ticket', 0, '2025-07-17 21:12:07'),
(2, 3, 'Ticket Sales Update', 'You have earned Rwf 9,500.00 from recent ticket sales. Check your financial dashboard for details.', 'payment', 0, '2025-07-17 21:12:07'),
(3, 2, 'Ticket Purchased: Regular', 'You have successfully purchased a Regular for Music Concert. Check your email for details.', 'ticket', 0, '2025-07-17 22:53:22'),
(4, 2, 'Ticket Purchased: Regular', 'You have successfully purchased a Regular for Music Concert. Check your email for details.', 'ticket', 0, '2025-07-17 22:53:25'),
(5, 2, 'Ticket Purchased: Regular', 'You have successfully purchased a Regular for Music Concert. Check your email for details.', 'ticket', 0, '2025-07-17 22:53:28'),
(6, 2, 'Ticket Purchased: Regular', 'You have successfully purchased a Regular for Music Concert. Check your email for details.', 'ticket', 0, '2025-07-17 22:53:30'),
(7, 2, 'Ticket Purchased: Regular', 'You have successfully purchased a Regular for Music Concert. Check your email for details.', 'ticket', 0, '2025-07-17 22:53:33'),
(8, 3, 'Ticket Sales Update', 'You have earned Rwf 47,500.00 from recent ticket sales. Check your financial dashboard for details.', 'payment', 0, '2025-07-17 22:53:33'),
(9, 2, 'Ticket Purchased: General Admission', 'You have successfully purchased a General Admission for Technology. Check your email for details.', 'ticket', 0, '2025-07-17 23:18:56'),
(10, 2, 'Ticket Purchased: General Admission', 'You have successfully purchased a General Admission for Technology. Check your email for details.', 'ticket', 0, '2025-07-17 23:18:59'),
(11, 2, 'Ticket Purchased: General Admission', 'You have successfully purchased a General Admission for Technology. Check your email for details.', 'ticket', 0, '2025-07-17 23:19:02'),
(12, 2, 'Ticket Purchased: General Admission', 'You have successfully purchased a General Admission for Technology. Check your email for details.', 'ticket', 0, '2025-07-17 23:19:06'),
(13, 2, 'Ticket Purchased: General Admission', 'You have successfully purchased a General Admission for Technology. Check your email for details.', 'ticket', 0, '2025-07-17 23:19:12'),
(14, 3, 'Ticket Sales Update', 'You have earned Rwf 23,750.00 from recent ticket sales. Check your financial dashboard for details.', 'payment', 0, '2025-07-17 23:19:12');

-- --------------------------------------------------------

--
-- Table structure for table `notification_settings`
--

CREATE TABLE `notification_settings` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `email_notifications` tinyint(1) DEFAULT 1,
  `sms_notifications` tinyint(1) DEFAULT 0,
  `push_notifications` tinyint(1) DEFAULT 1,
  `event_reminders` tinyint(1) DEFAULT 1,
  `payment_notifications` tinyint(1) DEFAULT 1,
  `marketing_emails` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `notification_settings`
--

INSERT INTO `notification_settings` (`id`, `user_id`, `email_notifications`, `sms_notifications`, `push_notifications`, `event_reminders`, `payment_notifications`, `marketing_emails`, `created_at`, `updated_at`) VALUES
(1, 1, 1, 0, 1, 1, 1, 0, '2025-07-17 20:24:50', '2025-07-17 20:24:50');

-- --------------------------------------------------------

--
-- Table structure for table `payment_transactions`
--

CREATE TABLE `payment_transactions` (
  `transaction_id` int(11) NOT NULL,
  `billing_id` int(11) DEFAULT NULL,
  `client_id` int(11) DEFAULT NULL,
  `payment_method` varchar(20) DEFAULT NULL,
  `amount` decimal(10,2) DEFAULT NULL,
  `currency` varchar(10) DEFAULT NULL,
  `status` varchar(20) DEFAULT NULL,
  `gateway_reference` varchar(100) DEFAULT NULL,
  `gateway_transaction_id` varchar(100) DEFAULT NULL,
  `gateway_response` text DEFAULT NULL,
  `failure_reason` text DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `settings_cache`
--

CREATE TABLE `settings_cache` (
  `cache_key` varchar(100) NOT NULL,
  `cache_value` text DEFAULT NULL,
  `expires_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `sms_logs`
--

CREATE TABLE `sms_logs` (
  `id` int(11) NOT NULL,
  `recipient_phone` varchar(20) NOT NULL,
  `message` text NOT NULL,
  `status` enum('sent','failed','pending') NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `system_fees`
--

CREATE TABLE `system_fees` (
  `id` int(11) NOT NULL,
  `fee_type` enum('ticket_sale','withdrawal','resale') NOT NULL,
  `percentage` decimal(5,2) NOT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `system_fees`
--

INSERT INTO `system_fees` (`id`, `fee_type`, `percentage`, `updated_at`) VALUES
(1, 'ticket_sale', 5.00, '2025-07-17 20:22:48'),
(2, 'withdrawal', 2.50, '2025-07-17 20:22:48'),
(3, 'resale', 3.00, '2025-07-17 20:22:48');

-- --------------------------------------------------------

--
-- Table structure for table `system_settings`
--

CREATE TABLE `system_settings` (
  `id` int(11) NOT NULL,
  `setting_key` varchar(50) NOT NULL,
  `setting_value` text NOT NULL,
  `setting_type` enum('string','integer','boolean','json') DEFAULT 'string',
  `description` text DEFAULT NULL,
  `is_public` tinyint(1) DEFAULT 0,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `system_settings`
--

INSERT INTO `system_settings` (`id`, `setting_key`, `setting_value`, `setting_type`, `description`, `is_public`, `updated_at`) VALUES
(1, 'site_name', 'Smart Ticket System', 'string', 'Name of the website', 0, '2025-07-17 20:22:48'),
(2, 'site_email', 'contact@smartticket.com', 'string', 'Contact email for the website', 0, '2025-07-17 20:22:48'),
(3, 'ticket_expiry_hours', '2', 'string', 'Number of hours before a booking expires', 0, '2025-07-17 20:22:48'),
(4, 'max_login_attempts', '5', 'string', 'Maximum number of failed login attempts before account lockout', 0, '2025-07-17 20:22:49'),
(5, 'session_timeout', '60', 'string', 'Session timeout in minutes', 0, '2025-07-17 20:22:49'),
(6, 'password_min_length', '8', 'string', 'Minimum password length requirement', 0, '2025-07-17 20:22:49'),
(7, 'require_strong_password', '1', 'string', 'Require strong passwords with mixed case, numbers, and symbols', 0, '2025-07-17 20:22:49'),
(8, 'enable_two_factor', '0', 'string', 'Enable two-factor authentication system-wide', 0, '2025-07-17 20:22:49'),
(9, 'email_enabled', '1', 'string', 'Enable email notifications', 0, '2025-07-17 20:22:49'),
(10, 'sms_enabled', '0', 'string', 'Enable SMS notifications', 0, '2025-07-17 20:22:49'),
(11, 'smtp_host', '', 'string', 'SMTP server hostname', 0, '2025-07-17 20:22:49'),
(12, 'smtp_port', '587', 'string', 'SMTP server port', 0, '2025-07-17 20:22:49'),
(13, 'smtp_username', '', 'string', 'SMTP username', 0, '2025-07-17 20:22:49'),
(14, 'smtp_password', '', 'string', 'SMTP password', 0, '2025-07-17 20:22:49'),
(15, 'sms_api_key', '', 'string', 'SMS service API key', 0, '2025-07-17 20:22:49'),
(16, 'sms_api_secret', '', 'string', 'SMS service API secret', 0, '2025-07-17 20:22:49'),
(17, 'maintenance_mode', '0', 'string', 'Enable maintenance mode', 0, '2025-07-17 20:22:49'),
(18, 'registration_enabled', '1', 'string', 'Allow new user registrations', 0, '2025-07-17 20:22:49'),
(19, 'max_file_upload_size', '5', 'string', 'Maximum file upload size in MB', 0, '2025-07-17 20:22:49'),
(20, 'allowed_image_types', 'jpg,jpeg,png,gif', 'string', 'Allowed image file extensions', 0, '2025-07-17 20:22:49'),
(21, 'timezone', 'Africa/Kigali', 'string', 'System timezone', 0, '2025-07-17 20:22:49'),
(22, 'currency_symbol', 'Rwf', 'string', 'Currency symbol', 0, '2025-07-17 20:22:49'),
(23, 'date_format', 'Y-m-d', 'string', 'System date format', 0, '2025-07-17 20:22:49'),
(24, 'time_format', 'H:i', 'string', 'System time format', 0, '2025-07-17 20:22:49'),
(25, 'site_description', 'Advanced ticket sales and event management system', 'string', 'Website description', 1, '2025-07-17 20:24:52'),
(26, 'default_currency', 'USD', 'string', 'Default currency for transactions', 1, '2025-07-17 20:24:52'),
(27, 'ticket_transfer_fee', '5.00', 'string', 'Fee for transferring tickets between users', 1, '2025-07-17 20:24:52'),
(28, 'max_tickets_per_user', '10', 'integer', 'Maximum tickets a user can buy per event', 1, '2025-07-17 20:24:52'),
(29, 'waitlist_expiry_hours', '24', 'integer', 'Hours before waitlist notification expires', 0, '2025-07-17 20:24:52'),
(30, 'email_notifications_enabled', 'true', 'boolean', 'Enable email notifications', 0, '2025-07-17 20:24:52'),
(31, 'sms_notifications_enabled', 'false', 'boolean', 'Enable SMS notifications', 0, '2025-07-17 20:24:52');

-- --------------------------------------------------------

--
-- Table structure for table `tickets`
--

CREATE TABLE `tickets` (
  `id` int(11) NOT NULL,
  `event_id` int(11) NOT NULL,
  `ticket_type_id` int(11) DEFAULT NULL,
  `user_id` int(11) NOT NULL,
  `recipient_name` varchar(100) DEFAULT NULL,
  `recipient_email` varchar(100) DEFAULT NULL,
  `recipient_phone` varchar(20) DEFAULT NULL,
  `qr_code` varchar(255) DEFAULT NULL,
  `purchase_price` decimal(10,2) NOT NULL,
  `status` enum('available','sold','used','reselling','resold') DEFAULT 'sold',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `tickets`
--

INSERT INTO `tickets` (`id`, `event_id`, `ticket_type_id`, `user_id`, `recipient_name`, `recipient_email`, `recipient_phone`, `qr_code`, `purchase_price`, `status`, `created_at`, `updated_at`) VALUES
(1, 11, 1, 2, 'customer', 'fadhiliamani200@gmail.com', '0784424423', 'TICKET-QBkUsxIDXAJbhZXW', 10000.00, 'sold', '2025-07-17 21:12:04', '2025-07-17 21:12:04'),
(2, 11, 1, 2, 'customer', 'fadhiliamani200@gmail.com', '0784424423', 'TICKET-1twQTXoFFVLssF2n', 10000.00, 'sold', '2025-07-17 22:53:19', '2025-07-17 22:53:19'),
(3, 11, 1, 2, 'customer', 'fadhiliamani200@gmail.com', '0784424423', 'TICKET-b9IYceuIRKJCPXjw', 10000.00, 'sold', '2025-07-17 22:53:19', '2025-07-17 22:53:19'),
(4, 11, 1, 2, 'customer', 'fadhiliamani200@gmail.com', '0784424423', 'TICKET-t028drs0dy7gqXZJ', 10000.00, 'used', '2025-07-17 22:53:19', '2025-07-17 23:27:43'),
(5, 11, 1, 2, 'customer', 'fadhiliamani200@gmail.com', '0784424423', 'TICKET-n6SZX6ttX6ZGXENv', 10000.00, 'used', '2025-07-17 22:53:19', '2025-07-17 23:26:45'),
(6, 11, 1, 2, 'customer', 'fadhiliamani200@gmail.com', '0784424423', 'TICKET-eYMREW9yJxhIxB23', 10000.00, 'used', '2025-07-17 22:53:19', '2025-07-17 23:15:16'),
(7, 12, 2, 2, 'customer', 'fadhiliamani200@gmail.com', '0784424423', 'TICKET-rcGnEfKp2nGiswqz', 5000.00, 'sold', '2025-07-17 23:18:53', '2025-07-17 23:18:53'),
(8, 12, 2, 2, 'customer', 'fadhiliamani200@gmail.com', '0784424423', 'TICKET-MYkfniuwdJtzuRkE', 5000.00, 'sold', '2025-07-17 23:18:53', '2025-07-17 23:18:53'),
(9, 12, 2, 2, 'customer', 'fadhiliamani200@gmail.com', '0784424423', 'TICKET-ns4tqlzJr5AH4DVR', 5000.00, 'sold', '2025-07-17 23:18:53', '2025-07-17 23:18:53'),
(10, 12, 2, 2, 'customer', 'fadhiliamani200@gmail.com', '0784424423', 'TICKET-H4HEi7tkiz6DzvFB', 5000.00, 'sold', '2025-07-17 23:18:53', '2025-07-17 23:18:53'),
(11, 12, 2, 2, 'customer', 'fadhiliamani200@gmail.com', '0784424423', 'TICKET-Lxn3Ql9G75YH2dqD', 5000.00, 'sold', '2025-07-17 23:18:53', '2025-07-17 23:18:53');

-- --------------------------------------------------------

--
-- Table structure for table `ticket_resales`
--

CREATE TABLE `ticket_resales` (
  `id` int(11) NOT NULL,
  `ticket_id` int(11) NOT NULL,
  `seller_id` int(11) NOT NULL,
  `buyer_id` int(11) DEFAULT NULL,
  `resale_price` decimal(10,2) NOT NULL,
  `platform_fee` decimal(10,2) NOT NULL DEFAULT 0.00,
  `seller_earnings` decimal(10,2) NOT NULL DEFAULT 0.00,
  `description` text DEFAULT NULL,
  `status` enum('active','sold','canceled','expired') DEFAULT 'active',
  `listed_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `sold_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `ticket_transfers`
--

CREATE TABLE `ticket_transfers` (
  `id` int(11) NOT NULL,
  `ticket_id` int(11) NOT NULL,
  `from_user_id` int(11) NOT NULL,
  `to_user_id` int(11) NOT NULL,
  `transfer_reason` varchar(255) DEFAULT NULL,
  `transfer_fee` decimal(10,2) DEFAULT 0.00,
  `status` enum('pending','completed','cancelled') DEFAULT 'pending',
  `completed_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `ticket_types`
--

CREATE TABLE `ticket_types` (
  `id` int(11) NOT NULL,
  `event_id` int(11) NOT NULL,
  `name` varchar(50) NOT NULL,
  `description` text DEFAULT NULL,
  `price` decimal(10,2) NOT NULL,
  `total_tickets` int(11) NOT NULL,
  `available_tickets` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `ticket_types`
--

INSERT INTO `ticket_types` (`id`, `event_id`, `name`, `description`, `price`, `total_tickets`, `available_tickets`, `created_at`, `updated_at`) VALUES
(1, 11, 'Regular', 'Includes general access to the concert hall.', 10000.00, 150, 144, '2025-07-17 20:52:12', '2025-07-17 23:01:05'),
(2, 12, 'General Admission', 'Access to all open exhibition areas and demo zones.', 5000.00, 500, 495, '2025-07-17 20:55:25', '2025-07-17 23:18:53'),
(3, 13, 'Standard', 'Access to keynote speeches, panel discussions, and lunch.', 15000.00, 100, 100, '2025-07-17 21:02:47', '2025-07-17 21:50:44'),
(4, 13, 'Executive', 'Includes premium seating, personalized networking table, and exclusive cocktail event.', 30000.00, 50, 50, '2025-07-17 21:02:47', '2025-07-17 21:50:44');

-- --------------------------------------------------------

--
-- Table structure for table `ticket_verifications`
--

CREATE TABLE `ticket_verifications` (
  `id` int(11) NOT NULL,
  `ticket_id` int(11) NOT NULL,
  `agent_id` int(11) NOT NULL,
  `verification_time` timestamp NOT NULL DEFAULT current_timestamp(),
  `status` enum('verified','rejected','duplicate') NOT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `ticket_verifications`
--

INSERT INTO `ticket_verifications` (`id`, `ticket_id`, `agent_id`, `verification_time`, `status`, `notes`, `created_at`) VALUES
(1, 1, 4, '2025-07-17 21:46:28', 'rejected', '', '2025-07-17 21:46:28'),
(2, 1, 4, '2025-07-17 21:51:32', 'duplicate', '', '2025-07-17 21:51:32'),
(3, 6, 4, '2025-07-17 23:15:16', 'verified', '', '2025-07-17 23:15:16'),
(4, 9, 4, '2025-07-17 23:19:51', '', '', '2025-07-17 23:19:51'),
(5, 9, 4, '2025-07-17 23:20:36', '', '', '2025-07-17 23:20:36'),
(6, 9, 4, '2025-07-17 23:20:49', '', '', '2025-07-17 23:20:49'),
(7, 9, 4, '2025-07-17 23:20:59', '', '', '2025-07-17 23:20:59'),
(8, 5, 4, '2025-07-17 23:26:45', 'verified', '', '2025-07-17 23:26:45'),
(9, 4, 4, '2025-07-17 23:27:42', 'verified', '', '2025-07-17 23:27:42');

-- --------------------------------------------------------

--
-- Table structure for table `transactions`
--

CREATE TABLE `transactions` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `type` enum('deposit','withdrawal','purchase','sale','resale','system_fee') NOT NULL,
  `status` enum('pending','completed','failed') DEFAULT 'pending',
  `reference_id` varchar(100) DEFAULT NULL,
  `payment_method` enum('credit_card','mobile_money','airtel_money','balance') DEFAULT NULL,
  `payment_gateway` varchar(50) DEFAULT NULL,
  `gateway_transaction_id` varchar(100) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `transactions`
--

INSERT INTO `transactions` (`id`, `user_id`, `amount`, `type`, `status`, `reference_id`, `payment_method`, `payment_gateway`, `gateway_transaction_id`, `description`, `created_at`, `updated_at`) VALUES
(1, 2, 10000.00, 'purchase', 'completed', 'cs_test_a1zBsMzlkGyqTQFSa1KbsrbHNzAmOW1DzhRA9przPSYHnsSWGyLIif3LXU', 'credit_card', NULL, NULL, 'Ticket purchase via Stripe', '2025-07-17 21:12:04', '2025-07-17 21:12:04'),
(2, 3, 9500.00, 'sale', 'completed', 'cs_test_a1zBsMzlkGyqTQFSa1KbsrbHNzAmOW1DzhRA9przPSYHnsSWGyLIif3LXU', NULL, NULL, NULL, 'Ticket sale for event: Music Concert', '2025-07-17 21:12:04', '2025-07-17 21:12:04'),
(3, 3, 500.00, 'system_fee', 'completed', 'cs_test_a1zBsMzlkGyqTQFSa1KbsrbHNzAmOW1DzhRA9przPSYHnsSWGyLIif3LXU', NULL, NULL, NULL, 'Platform fee for ticket sale: Music Concert', '2025-07-17 21:12:04', '2025-07-17 21:12:04'),
(4, 2, 50000.00, 'purchase', 'completed', 'cs_test_a1hH54u9OOoWLJ2EM2pimYTB7AH5Xj4uPtKfbniTEUsgGvByxfmUKgqLli', 'credit_card', NULL, NULL, 'Ticket purchase via Stripe', '2025-07-17 22:53:19', '2025-07-17 22:53:19'),
(5, 3, 47500.00, 'sale', 'completed', 'cs_test_a1hH54u9OOoWLJ2EM2pimYTB7AH5Xj4uPtKfbniTEUsgGvByxfmUKgqLli', NULL, NULL, NULL, 'Ticket sale for event: Music Concert', '2025-07-17 22:53:19', '2025-07-17 22:53:19'),
(6, 3, 2500.00, 'system_fee', 'completed', 'cs_test_a1hH54u9OOoWLJ2EM2pimYTB7AH5Xj4uPtKfbniTEUsgGvByxfmUKgqLli', NULL, NULL, NULL, 'Platform fee for ticket sale: Music Concert', '2025-07-17 22:53:19', '2025-07-17 22:53:19'),
(7, 2, 25000.00, 'purchase', 'completed', 'cs_test_a1J5w15qdsptnF2oPAaK00J30cdAhwhfBt4KBgfylbongcjDczz5CagxgV', 'credit_card', NULL, NULL, 'Ticket purchase via Stripe', '2025-07-17 23:18:53', '2025-07-17 23:18:53'),
(8, 3, 23750.00, 'sale', 'completed', 'cs_test_a1J5w15qdsptnF2oPAaK00J30cdAhwhfBt4KBgfylbongcjDczz5CagxgV', NULL, NULL, NULL, 'Ticket sale for event: Technology', '2025-07-17 23:18:53', '2025-07-17 23:18:53'),
(9, 3, 1250.00, 'system_fee', 'completed', 'cs_test_a1J5w15qdsptnF2oPAaK00J30cdAhwhfBt4KBgfylbongcjDczz5CagxgV', NULL, NULL, NULL, 'Platform fee for ticket sale: Technology', '2025-07-17 23:18:53', '2025-07-17 23:18:53');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `phone_number` varchar(20) NOT NULL,
  `role` enum('admin','event_planner','customer','agent') NOT NULL,
  `profile_image` varchar(255) DEFAULT NULL,
  `balance` decimal(10,2) DEFAULT 0.00,
  `status` enum('active','suspended','inactive') DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `username`, `email`, `password_hash`, `phone_number`, `role`, `profile_image`, `balance`, `status`, `created_at`, `updated_at`) VALUES
(1, 'admin', 'admin@system.com', '$2y$10$BFB0UyPXz34.yq5nxJDfJuQRpGRGx/CNEBQedYzHKy6lYrVPX.Gxu', '1234567890', 'admin', NULL, 0.00, 'active', '2025-07-17 20:22:48', '2025-07-17 20:41:08'),
(2, 'customer', 'fadhiliamani200@gmail.com', '$2y$10$tEyk6k4K0vWUPms/epHBnO14lXEENJ8m5hM1x4E4N.me6EAu84g4G', '0784424423', 'customer', NULL, 0.00, 'active', '2025-07-17 20:43:19', '2025-07-17 20:43:19'),
(3, 'event planner', 'a.fadhiliprojects@gmail.com', '$2y$10$493.89LYLft3aDInBrpdwes8EjjJBh4pQ9mmk7R/CX4UkLIWvtCjG', '0784424423', 'event_planner', NULL, 80750.00, 'active', '2025-07-17 20:45:17', '2025-07-17 23:18:53'),
(4, 'agent', 'agent@gmail.com', '$2y$10$cJUyp8sXcX/NZQOWsKxj7uwMVk5LdftK5evznEGOPC4ve5S.2/Tl.', '0784424423', 'agent', NULL, 0.00, 'active', '2025-07-17 21:19:14', '2025-07-17 21:19:14'),
(5, 'jkkjkj', 'fadhiliamaikni200@gmail.com', '$2y$10$xy/E8lNRZQe2l1v7BcYzEOaFVuuSVnMCNMpRLWm3fKZEf80olM5Qy', '0784424423', 'agent', NULL, 0.00, 'active', '2025-07-17 21:39:15', '2025-07-17 21:39:15');

-- --------------------------------------------------------

--
-- Table structure for table `user_preferences`
--

CREATE TABLE `user_preferences` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `timezone` varchar(50) DEFAULT 'UTC',
  `currency` varchar(3) DEFAULT 'USD',
  `language` varchar(5) DEFAULT 'en',
  `theme` enum('light','dark','auto') DEFAULT 'light',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `user_preferences`
--

INSERT INTO `user_preferences` (`id`, `user_id`, `timezone`, `currency`, `language`, `theme`, `created_at`, `updated_at`) VALUES
(1, 1, 'UTC', 'USD', 'en', 'light', '2025-07-17 20:24:51', '2025-07-17 20:24:51');

-- --------------------------------------------------------

--
-- Table structure for table `user_sessions`
--

CREATE TABLE `user_sessions` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `session_id` varchar(255) NOT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `last_activity` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `withdrawals`
--

CREATE TABLE `withdrawals` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `fee` decimal(10,2) NOT NULL,
  `net_amount` decimal(10,2) NOT NULL,
  `payment_method` varchar(50) NOT NULL,
  `payment_details` text DEFAULT NULL,
  `admin_notes` text DEFAULT NULL,
  `status` enum('pending','approved','rejected','completed') DEFAULT 'pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `agent_scans`
--
ALTER TABLE `agent_scans`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `api_keys`
--
ALTER TABLE `api_keys`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_api_key` (`api_key`),
  ADD KEY `idx_user_id` (`user_id`),
  ADD KEY `idx_is_active` (`is_active`);

--
-- Indexes for table `audit_log`
--
ALTER TABLE `audit_log`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_user_id` (`user_id`),
  ADD KEY `idx_action` (`action`),
  ADD KEY `idx_created_at` (`created_at`);

--
-- Indexes for table `bookings`
--
ALTER TABLE `bookings`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_user` (`user_id`),
  ADD KEY `idx_event` (`event_id`),
  ADD KEY `idx_status` (`status`);

--
-- Indexes for table `booking_items`
--
ALTER TABLE `booking_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_booking` (`booking_id`);

--
-- Indexes for table `cart`
--
ALTER TABLE `cart`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_user` (`user_id`);

--
-- Indexes for table `cart_items`
--
ALTER TABLE `cart_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `event_id` (`event_id`),
  ADD KEY `idx_cart` (`cart_id`),
  ADD KEY `ticket_type_id` (`ticket_type_id`);

--
-- Indexes for table `deposits`
--
ALTER TABLE `deposits`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_user` (`user_id`),
  ADD KEY `idx_status` (`status`);

--
-- Indexes for table `email_logs`
--
ALTER TABLE `email_logs`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `events`
--
ALTER TABLE `events`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_planner` (`planner_id`),
  ADD KEY `idx_start_date` (`start_date`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_events_planner_status` (`planner_id`,`status`),
  ADD KEY `idx_events_start_date` (`start_date`),
  ADD KEY `category_id` (`category_id`);

--
-- Indexes for table `event_analytics`
--
ALTER TABLE `event_analytics`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_event_date` (`event_id`,`date`),
  ADD KEY `idx_event_id` (`event_id`),
  ADD KEY `idx_date` (`date`);

--
-- Indexes for table `event_categories`
--
ALTER TABLE `event_categories`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `event_tags`
--
ALTER TABLE `event_tags`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_tag_name` (`name`);

--
-- Indexes for table `event_tag_relations`
--
ALTER TABLE `event_tag_relations`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_event_tag` (`event_id`,`tag_id`),
  ADD KEY `tag_id` (`tag_id`);

--
-- Indexes for table `event_waitlist`
--
ALTER TABLE `event_waitlist`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_user_event_ticket` (`user_id`,`event_id`,`ticket_type_id`),
  ADD KEY `ticket_type_id` (`ticket_type_id`),
  ADD KEY `idx_event_id` (`event_id`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_expires_at` (`expires_at`);

--
-- Indexes for table `feedback`
--
ALTER TABLE `feedback`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_user` (`user_id`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_created` (`created_at`);

--
-- Indexes for table `notifications`
--
ALTER TABLE `notifications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_user` (`user_id`),
  ADD KEY `idx_is_read` (`is_read`);

--
-- Indexes for table `notification_settings`
--
ALTER TABLE `notification_settings`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_user_settings` (`user_id`);

--
-- Indexes for table `payment_transactions`
--
ALTER TABLE `payment_transactions`
  ADD PRIMARY KEY (`transaction_id`);

--
-- Indexes for table `settings_cache`
--
ALTER TABLE `settings_cache`
  ADD PRIMARY KEY (`cache_key`);

--
-- Indexes for table `sms_logs`
--
ALTER TABLE `sms_logs`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `system_fees`
--
ALTER TABLE `system_fees`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `fee_type` (`fee_type`);

--
-- Indexes for table `system_settings`
--
ALTER TABLE `system_settings`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `setting_key` (`setting_key`);

--
-- Indexes for table `tickets`
--
ALTER TABLE `tickets`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_event` (`event_id`),
  ADD KEY `idx_user` (`user_id`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `ticket_type_id` (`ticket_type_id`),
  ADD KEY `idx_tickets_user_status` (`user_id`,`status`),
  ADD KEY `idx_tickets_event_status` (`event_id`,`status`);

--
-- Indexes for table `ticket_resales`
--
ALTER TABLE `ticket_resales`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_ticket` (`ticket_id`),
  ADD KEY `idx_seller` (`seller_id`),
  ADD KEY `idx_buyer` (`buyer_id`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_listed_at` (`listed_at`);

--
-- Indexes for table `ticket_transfers`
--
ALTER TABLE `ticket_transfers`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_ticket_id` (`ticket_id`),
  ADD KEY `idx_from_user_id` (`from_user_id`),
  ADD KEY `idx_to_user_id` (`to_user_id`),
  ADD KEY `idx_status` (`status`);

--
-- Indexes for table `ticket_types`
--
ALTER TABLE `ticket_types`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_event` (`event_id`);

--
-- Indexes for table `ticket_verifications`
--
ALTER TABLE `ticket_verifications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_ticket` (`ticket_id`),
  ADD KEY `idx_agent` (`agent_id`);

--
-- Indexes for table `transactions`
--
ALTER TABLE `transactions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_user` (`user_id`),
  ADD KEY `idx_type` (`type`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_transactions_user_type` (`user_id`,`type`),
  ADD KEY `idx_transactions_status` (`status`),
  ADD KEY `idx_transactions_created_at` (`created_at`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`),
  ADD KEY `idx_email` (`email`),
  ADD KEY `idx_phone` (`phone_number`),
  ADD KEY `idx_role` (`role`);

--
-- Indexes for table `user_preferences`
--
ALTER TABLE `user_preferences`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_user_preferences` (`user_id`);

--
-- Indexes for table `user_sessions`
--
ALTER TABLE `user_sessions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_session_id` (`session_id`),
  ADD KEY `idx_user_id` (`user_id`),
  ADD KEY `idx_last_activity` (`last_activity`);

--
-- Indexes for table `withdrawals`
--
ALTER TABLE `withdrawals`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_user` (`user_id`),
  ADD KEY `idx_status` (`status`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `agent_scans`
--
ALTER TABLE `agent_scans`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `api_keys`
--
ALTER TABLE `api_keys`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `audit_log`
--
ALTER TABLE `audit_log`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `bookings`
--
ALTER TABLE `bookings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `booking_items`
--
ALTER TABLE `booking_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `cart`
--
ALTER TABLE `cart`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `cart_items`
--
ALTER TABLE `cart_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `deposits`
--
ALTER TABLE `deposits`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `email_logs`
--
ALTER TABLE `email_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `events`
--
ALTER TABLE `events`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- AUTO_INCREMENT for table `event_analytics`
--
ALTER TABLE `event_analytics`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `event_categories`
--
ALTER TABLE `event_categories`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `event_tags`
--
ALTER TABLE `event_tags`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `event_tag_relations`
--
ALTER TABLE `event_tag_relations`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `event_waitlist`
--
ALTER TABLE `event_waitlist`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `feedback`
--
ALTER TABLE `feedback`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `notifications`
--
ALTER TABLE `notifications`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=15;

--
-- AUTO_INCREMENT for table `notification_settings`
--
ALTER TABLE `notification_settings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `payment_transactions`
--
ALTER TABLE `payment_transactions`
  MODIFY `transaction_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `sms_logs`
--
ALTER TABLE `sms_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `system_fees`
--
ALTER TABLE `system_fees`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `system_settings`
--
ALTER TABLE `system_settings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=33;

--
-- AUTO_INCREMENT for table `tickets`
--
ALTER TABLE `tickets`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT for table `ticket_resales`
--
ALTER TABLE `ticket_resales`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `ticket_transfers`
--
ALTER TABLE `ticket_transfers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `ticket_types`
--
ALTER TABLE `ticket_types`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `ticket_verifications`
--
ALTER TABLE `ticket_verifications`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `transactions`
--
ALTER TABLE `transactions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `user_preferences`
--
ALTER TABLE `user_preferences`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `user_sessions`
--
ALTER TABLE `user_sessions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `withdrawals`
--
ALTER TABLE `withdrawals`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `api_keys`
--
ALTER TABLE `api_keys`
  ADD CONSTRAINT `api_keys_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `audit_log`
--
ALTER TABLE `audit_log`
  ADD CONSTRAINT `audit_log_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `bookings`
--
ALTER TABLE `bookings`
  ADD CONSTRAINT `bookings_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `bookings_ibfk_2` FOREIGN KEY (`event_id`) REFERENCES `events` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `booking_items`
--
ALTER TABLE `booking_items`
  ADD CONSTRAINT `booking_items_ibfk_1` FOREIGN KEY (`booking_id`) REFERENCES `bookings` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `cart`
--
ALTER TABLE `cart`
  ADD CONSTRAINT `cart_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `cart_items`
--
ALTER TABLE `cart_items`
  ADD CONSTRAINT `cart_items_ibfk_1` FOREIGN KEY (`cart_id`) REFERENCES `cart` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `cart_items_ibfk_2` FOREIGN KEY (`event_id`) REFERENCES `events` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `cart_items_ibfk_3` FOREIGN KEY (`ticket_type_id`) REFERENCES `ticket_types` (`id`);

--
-- Constraints for table `deposits`
--
ALTER TABLE `deposits`
  ADD CONSTRAINT `deposits_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `events`
--
ALTER TABLE `events`
  ADD CONSTRAINT `events_ibfk_1` FOREIGN KEY (`planner_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `events_ibfk_2` FOREIGN KEY (`category_id`) REFERENCES `event_categories` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `event_analytics`
--
ALTER TABLE `event_analytics`
  ADD CONSTRAINT `event_analytics_ibfk_1` FOREIGN KEY (`event_id`) REFERENCES `events` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `event_tag_relations`
--
ALTER TABLE `event_tag_relations`
  ADD CONSTRAINT `event_tag_relations_ibfk_1` FOREIGN KEY (`event_id`) REFERENCES `events` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `event_tag_relations_ibfk_2` FOREIGN KEY (`tag_id`) REFERENCES `event_tags` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `event_waitlist`
--
ALTER TABLE `event_waitlist`
  ADD CONSTRAINT `event_waitlist_ibfk_1` FOREIGN KEY (`event_id`) REFERENCES `events` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `event_waitlist_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `event_waitlist_ibfk_3` FOREIGN KEY (`ticket_type_id`) REFERENCES `ticket_types` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `feedback`
--
ALTER TABLE `feedback`
  ADD CONSTRAINT `feedback_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `notifications`
--
ALTER TABLE `notifications`
  ADD CONSTRAINT `notifications_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `notification_settings`
--
ALTER TABLE `notification_settings`
  ADD CONSTRAINT `notification_settings_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `tickets`
--
ALTER TABLE `tickets`
  ADD CONSTRAINT `tickets_ibfk_1` FOREIGN KEY (`event_id`) REFERENCES `events` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `tickets_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `tickets_ibfk_3` FOREIGN KEY (`ticket_type_id`) REFERENCES `ticket_types` (`id`);

--
-- Constraints for table `ticket_resales`
--
ALTER TABLE `ticket_resales`
  ADD CONSTRAINT `ticket_resales_ibfk_1` FOREIGN KEY (`ticket_id`) REFERENCES `tickets` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `ticket_resales_ibfk_2` FOREIGN KEY (`seller_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `ticket_resales_ibfk_3` FOREIGN KEY (`buyer_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `ticket_transfers`
--
ALTER TABLE `ticket_transfers`
  ADD CONSTRAINT `ticket_transfers_ibfk_1` FOREIGN KEY (`ticket_id`) REFERENCES `tickets` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `ticket_transfers_ibfk_2` FOREIGN KEY (`from_user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `ticket_transfers_ibfk_3` FOREIGN KEY (`to_user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `ticket_types`
--
ALTER TABLE `ticket_types`
  ADD CONSTRAINT `ticket_types_ibfk_1` FOREIGN KEY (`event_id`) REFERENCES `events` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `ticket_verifications`
--
ALTER TABLE `ticket_verifications`
  ADD CONSTRAINT `ticket_verifications_ibfk_1` FOREIGN KEY (`ticket_id`) REFERENCES `tickets` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `ticket_verifications_ibfk_2` FOREIGN KEY (`agent_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `transactions`
--
ALTER TABLE `transactions`
  ADD CONSTRAINT `transactions_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `user_preferences`
--
ALTER TABLE `user_preferences`
  ADD CONSTRAINT `user_preferences_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `user_sessions`
--
ALTER TABLE `user_sessions`
  ADD CONSTRAINT `user_sessions_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `withdrawals`
--
ALTER TABLE `withdrawals`
  ADD CONSTRAINT `withdrawals_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
