-- phpMyAdmin SQL Dump
-- version 5.2.2
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1:3306
-- Generation Time: May 14, 2026 at 11:46 AM
-- Server version: 11.8.6-MariaDB-log
-- PHP Version: 7.2.34

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `u537581483_backend_soft`
--

-- --------------------------------------------------------

--
-- Table structure for table `cache`
--

CREATE TABLE `cache` (
  `key` varchar(255) NOT NULL,
  `value` mediumtext NOT NULL,
  `expiration` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `cache_locks`
--

CREATE TABLE `cache_locks` (
  `key` varchar(255) NOT NULL,
  `owner` varchar(255) NOT NULL,
  `expiration` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `company_expenses`
--

CREATE TABLE `company_expenses` (
  `id` bigint(20) NOT NULL,
  `category_id` int(11) NOT NULL,
  `expense_date` date NOT NULL,
  `amount` decimal(15,2) NOT NULL,
  `description` text NOT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `company_expenses`
--

INSERT INTO `company_expenses` (`id`, `category_id`, `expense_date`, `amount`, `description`, `notes`, `created_at`, `updated_at`) VALUES
(6, 12, '2026-03-26', 5000.00, 'Monthly Exp', NULL, '2026-03-26 14:55:27', '2026-03-26 14:55:27'),
(7, 8, '2026-03-26', 8000.00, 'Electricity Expenses', NULL, '2026-03-26 14:55:46', '2026-03-26 14:55:46'),
(8, 8, '2026-03-26', 15000.00, 'Office Rent', NULL, '2026-03-26 14:56:02', '2026-03-26 14:56:02'),
(9, 11, '2026-03-26', 30000.00, 'Home Expenses', NULL, '2026-03-26 14:56:21', '2026-03-26 14:56:21'),
(10, 9, '2026-03-26', 4000.00, 'Misc Expenses', NULL, '2026-03-26 14:56:42', '2026-03-26 14:56:42');

-- --------------------------------------------------------

--
-- Table structure for table `expense_categories`
--

CREATE TABLE `expense_categories` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `expense_categories`
--

INSERT INTO `expense_categories` (`id`, `name`, `description`, `is_active`, `created_at`, `updated_at`) VALUES
(8, 'Office Expenses', NULL, 1, '2026-03-26 14:54:04', '2026-03-26 14:54:04'),
(9, 'Other Expenses', NULL, 1, '2026-03-26 14:54:10', '2026-03-26 14:54:10'),
(10, 'Kashif', NULL, 1, '2026-03-26 14:54:15', '2026-03-26 14:54:15'),
(11, 'Waqas', NULL, 1, '2026-03-26 14:54:21', '2026-03-26 14:54:21'),
(12, 'Danish', NULL, 1, '2026-03-26 14:54:27', '2026-03-26 14:54:27');

-- --------------------------------------------------------

--
-- Table structure for table `failed_jobs`
--

CREATE TABLE `failed_jobs` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `uuid` varchar(255) NOT NULL,
  `connection` text NOT NULL,
  `queue` text NOT NULL,
  `payload` longtext NOT NULL,
  `exception` longtext NOT NULL,
  `failed_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `invoices`
--

CREATE TABLE `invoices` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `invoice_number` varchar(50) NOT NULL,
  `invoice_date` date NOT NULL,
  `ride_id` bigint(20) UNSIGNED NOT NULL,
  `party_id` bigint(20) UNSIGNED NOT NULL,
  `invoice_type` enum('advance','balance') NOT NULL,
  `amount` decimal(10,2) NOT NULL DEFAULT 0.00,
  `payment_status` enum('paid','unpaid') NOT NULL DEFAULT 'unpaid',
  `payment_date` date DEFAULT NULL,
  `description` text DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `invoices`
--

INSERT INTO `invoices` (`id`, `invoice_number`, `invoice_date`, `ride_id`, `party_id`, `invoice_type`, `amount`, `payment_status`, `payment_date`, `description`, `notes`, `created_at`, `updated_at`) VALUES
(30, 'INV-20260325-00015', '2026-03-25', 15, 7, 'advance', 5000.00, 'paid', '2026-03-25', 'Advance payment for ride RDE-20260325-0001', NULL, '2026-03-25 16:03:36', '2026-03-25 16:03:36'),
(31, 'INV-20260326-00015-BAL', '2026-03-26', 15, 7, 'balance', 28500.00, 'paid', '2026-03-26', 'Balance payment for ride RDE-20260325-0001', NULL, '2026-03-26 14:34:21', '2026-03-26 14:34:21'),
(32, 'INV-20260326-00016-0080', '2026-03-26', 16, 8, 'balance', 15000.00, 'paid', '2026-03-26', 'Payment for ride RDE-20260326-0001', NULL, '2026-03-26 14:39:14', '2026-03-26 14:39:23'),
(33, 'INV-20260326-00016-BAL', '2026-03-26', 16, 8, 'balance', 2700.00, 'paid', '2026-03-26', 'Balance payment for ride RDE-20260326-0001', NULL, '2026-03-26 14:39:59', '2026-03-26 14:39:59'),
(34, 'INV-20260326-00017', '2026-03-26', 17, 6, 'advance', 15000.00, 'paid', '2026-03-26', 'Advance payment for ride RDE-20260326-0002', NULL, '2026-03-26 14:43:52', '2026-03-26 14:43:52'),
(35, 'INV-20260326-00017-1988', '2026-03-26', 17, 6, 'balance', 51000.00, 'paid', '2026-03-26', 'Payment for ride RDE-20260326-0002', NULL, '2026-03-26 14:47:53', '2026-03-26 14:49:25'),
(36, 'INV-20260326-00017-BAL', '2026-03-26', 17, 6, 'balance', 18000.00, 'unpaid', NULL, 'Balance payment for ride RDE-20260326-0002', NULL, '2026-03-26 14:49:38', '2026-03-26 14:49:38'),
(37, 'INV-20260328-00018-8860', '2026-03-28', 18, 7, 'balance', 31500.00, 'unpaid', NULL, 'Payment for ride RDE-20260328-0001', NULL, '2026-03-28 12:39:26', '2026-03-28 12:39:26'),
(38, 'INV-20260328-00019', '2026-03-28', 19, 9, 'advance', 5000.00, 'paid', '2026-03-28', 'Advance payment for ride RDE-20260328-0002', NULL, '2026-03-28 13:31:35', '2026-03-28 13:31:35'),
(39, 'INV-20260328-00019-6069', '2026-03-28', 19, 9, 'balance', 6999.99, 'paid', '2026-03-28', 'Payment for ride RDE-20260328-0002', NULL, '2026-03-28 13:33:29', '2026-03-28 13:33:39'),
(40, 'INV-20260328-00019-BAL', '2026-03-28', 19, 9, 'balance', 5000.01, 'paid', '2026-03-28', 'Balance payment for ride RDE-20260328-0002', NULL, '2026-03-28 13:33:51', '2026-03-28 13:33:51');

-- --------------------------------------------------------

--
-- Table structure for table `jobs`
--

CREATE TABLE `jobs` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `queue` varchar(255) NOT NULL,
  `payload` longtext NOT NULL,
  `attempts` tinyint(3) UNSIGNED NOT NULL,
  `reserved_at` int(10) UNSIGNED DEFAULT NULL,
  `available_at` int(10) UNSIGNED NOT NULL,
  `created_at` int(10) UNSIGNED NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `job_batches`
--

CREATE TABLE `job_batches` (
  `id` varchar(255) NOT NULL,
  `name` varchar(255) NOT NULL,
  `total_jobs` int(11) NOT NULL,
  `pending_jobs` int(11) NOT NULL,
  `failed_jobs` int(11) NOT NULL,
  `failed_job_ids` longtext NOT NULL,
  `options` mediumtext DEFAULT NULL,
  `cancelled_at` int(11) DEFAULT NULL,
  `created_at` int(11) NOT NULL,
  `finished_at` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `migrations`
--

CREATE TABLE `migrations` (
  `id` int(10) UNSIGNED NOT NULL,
  `migration` varchar(255) NOT NULL,
  `batch` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `migrations`
--

INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES
(1, '0001_01_01_000000_create_users_table', 1),
(2, '0001_01_01_000001_create_cache_table', 1),
(3, '0001_01_01_000002_create_jobs_table', 1),
(4, '2026_02_08_122958_create_personal_access_tokens_table', 2);

-- --------------------------------------------------------

--
-- Table structure for table `parties`
--

CREATE TABLE `parties` (
  `id` bigint(20) NOT NULL,
  `name` varchar(200) NOT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `parties`
--

INSERT INTO `parties` (`id`, `name`, `phone`, `email`, `is_active`, `created_at`, `updated_at`) VALUES
(6, 'Hasnain', '0300 1234 789', 'hasnain@gmail.com', 1, '2026-03-25 15:45:43', '2026-03-25 15:45:43'),
(7, 'Arsalan', '0317 7891 456', 'arsalan@gmail.com', 1, '2026-03-25 15:47:09', '2026-03-25 15:47:09'),
(8, 'Ayyan', '0412 1234 456', 'ayyan@gmail.com', 1, '2026-03-25 15:47:55', '2026-03-25 15:47:55'),
(9, 'Air Commercial Services', NULL, NULL, 1, '2026-03-28 13:30:16', '2026-03-28 13:30:16');

-- --------------------------------------------------------

--
-- Table structure for table `partners`
--

CREATE TABLE `partners` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `name` varchar(100) NOT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `current_balance` decimal(11,2) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `partners`
--

INSERT INTO `partners` (`id`, `name`, `phone`, `email`, `is_active`, `current_balance`, `created_at`, `updated_at`) VALUES
(3, 'Ubaid', '0312 1234 789', 'ubaid@gmail.com', 1, NULL, '2026-03-25 15:43:14', '2026-03-25 15:43:14'),
(4, 'Owais', '0336 1234 789', 'owais@gmail.com', 1, NULL, '2026-03-25 15:43:29', '2026-03-25 15:43:38'),
(5, 'Mudassir', '0345 1234 567', 'mudassir@gmail.com', 1, NULL, '2026-03-25 15:44:05', '2026-03-25 15:44:05');

-- --------------------------------------------------------

--
-- Table structure for table `partner_ledger_entries`
--

CREATE TABLE `partner_ledger_entries` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `partner_id` bigint(20) UNSIGNED NOT NULL,
  `entry_date` date NOT NULL,
  `description` varchar(255) NOT NULL,
  `credit` decimal(15,2) DEFAULT 0.00,
  `debit` decimal(15,2) DEFAULT 0.00,
  `balance` decimal(15,2) NOT NULL,
  `notes` text DEFAULT NULL,
  `created_by` bigint(20) UNSIGNED DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `password_reset_tokens`
--

CREATE TABLE `password_reset_tokens` (
  `email` varchar(255) NOT NULL,
  `token` varchar(255) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `personal_access_tokens`
--

CREATE TABLE `personal_access_tokens` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `tokenable_type` varchar(255) NOT NULL,
  `tokenable_id` bigint(20) UNSIGNED NOT NULL,
  `name` text NOT NULL,
  `token` varchar(64) NOT NULL,
  `abilities` text DEFAULT NULL,
  `last_used_at` timestamp NULL DEFAULT NULL,
  `expires_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `personal_access_tokens`
--

INSERT INTO `personal_access_tokens` (`id`, `tokenable_type`, `tokenable_id`, `name`, `token`, `abilities`, `last_used_at`, `expires_at`, `created_at`, `updated_at`) VALUES
(39, 'App\\Models\\User', 1, 'auth_token', 'f4fd95fbd58765712b6175ee1f8198fdea68183fcc07fde25e0f0f06e1a59759', '[\"*\"]', '2026-05-12 14:04:56', NULL, '2026-05-12 11:04:27', '2026-05-12 14:04:56');

-- --------------------------------------------------------

--
-- Table structure for table `rides`
--

CREATE TABLE `rides` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `ride_number` varchar(50) NOT NULL,
  `start_date` date NOT NULL,
  `vehicle_id` bigint(20) UNSIGNED NOT NULL,
  `party_id` bigint(20) UNSIGNED NOT NULL,
  `ride_type` enum('personal','partner') NOT NULL DEFAULT 'personal',
  `partner_id` bigint(20) UNSIGNED DEFAULT NULL,
  `booking_amount` decimal(10,2) NOT NULL DEFAULT 0.00,
  `advance_amount` decimal(10,2) NOT NULL DEFAULT 0.00,
  `is_completed` tinyint(1) NOT NULL DEFAULT 0,
  `completed_date` date DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `route` text DEFAULT NULL,
  `container_no` varchar(100) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `rides`
--

INSERT INTO `rides` (`id`, `ride_number`, `start_date`, `vehicle_id`, `party_id`, `ride_type`, `partner_id`, `booking_amount`, `advance_amount`, `is_completed`, `completed_date`, `notes`, `route`, `container_no`, `created_at`, `updated_at`) VALUES
(15, 'RDE-20260325-0001', '2026-03-25', 9, 7, 'personal', NULL, 25000.00, 5000.00, 1, '2026-03-26', NULL, 'PQ EPZ KORANGI', '1287913551', '2026-03-25 16:03:36', '2026-03-26 14:34:21'),
(16, 'RDE-20260326-0001', '2026-03-26', 12, 8, 'partner', 5, 15000.00, 0.00, 1, '2026-03-26', NULL, 'KEMARI EPZ KORANGI', 'AERC102357QWE', '2026-03-26 14:36:24', '2026-03-26 14:39:59'),
(17, 'RDE-20260326-0002', '2026-03-26', 12, 6, 'personal', NULL, 60000.00, 15000.00, 1, '2026-03-26', NULL, 'KEMARI EPZ PQ', '15648912354AUH', '2026-03-26 14:43:52', '2026-03-26 14:49:38'),
(18, 'RDE-20260328-0001', '2026-03-28', 11, 7, 'personal', NULL, 30000.00, 0.00, 0, NULL, NULL, 'Karachi Site PQ', '1287913551', '2026-03-28 12:37:49', '2026-03-28 12:37:49'),
(19, 'RDE-20260328-0002', '2026-03-28', 15, 9, 'personal', NULL, 10000.00, 5000.00, 1, '2026-03-28', NULL, 'Karachi Site PQ', 'abc123456', '2026-03-28 13:31:35', '2026-03-28 13:33:51');

-- --------------------------------------------------------

--
-- Table structure for table `ride_expenses`
--

CREATE TABLE `ride_expenses` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `ride_id` bigint(20) UNSIGNED NOT NULL,
  `expense_amount` decimal(10,2) NOT NULL DEFAULT 0.00,
  `expense_note` text DEFAULT NULL,
  `expense_type` enum('personal','party') NOT NULL DEFAULT 'personal',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `ride_expenses`
--

INSERT INTO `ride_expenses` (`id`, `ride_id`, `expense_amount`, `expense_note`, `expense_type`, `created_at`, `updated_at`) VALUES
(14, 14, 10000.00, 'Fuel', 'personal', '2026-03-07 02:18:28', '2026-03-07 02:18:28'),
(15, 14, 5000.00, 'Tool Pass', 'party', '2026-03-07 02:18:59', '2026-03-07 02:18:59'),
(16, 14, 10000.00, NULL, 'party', '2026-03-12 01:53:10', '2026-03-12 01:53:10'),
(17, 14, 15000.00, 'tool plaza fees', 'personal', '2026-03-12 01:53:27', '2026-03-12 01:53:27'),
(18, 15, 5000.00, 'Feul', 'personal', '2026-03-26 14:32:59', '2026-03-26 14:32:59'),
(20, 15, 2000.00, 'Additional Expenses', 'party', '2026-03-26 14:33:49', '2026-03-26 14:33:49'),
(21, 15, 1500.00, 'Additional', 'personal', '2026-03-26 14:34:02', '2026-03-26 14:34:02'),
(22, 16, 2700.00, 'Fuel & Other', 'personal', '2026-03-26 14:39:36', '2026-03-26 14:39:47'),
(23, 17, 12000.00, 'Fuel & Other', 'personal', '2026-03-26 14:46:15', '2026-03-26 14:46:15'),
(24, 17, 6000.00, 'Other Expense', 'party', '2026-03-26 14:46:46', '2026-03-26 14:46:46'),
(25, 17, 6000.00, 'Other', 'personal', '2026-03-26 14:47:35', '2026-03-26 14:47:35'),
(26, 18, 1500.00, 'Challan', 'party', '2026-03-28 12:38:22', '2026-03-28 12:38:22'),
(27, 18, 1500.00, 'Challan', 'personal', '2026-03-28 12:38:31', '2026-03-28 12:38:41'),
(28, 18, 11500.00, 'All Expenses', 'personal', '2026-03-28 12:39:07', '2026-03-28 12:39:07'),
(29, 18, 1500.00, 'Driver', 'personal', '2026-03-28 12:44:17', '2026-03-28 12:44:17'),
(30, 19, 2000.00, 'Detain', 'party', '2026-03-28 13:32:42', '2026-03-28 13:32:42'),
(31, 19, 5000.00, 'Feul and all expenses', 'personal', '2026-03-28 13:33:06', '2026-03-28 13:33:06');

-- --------------------------------------------------------

--
-- Table structure for table `sessions`
--

CREATE TABLE `sessions` (
  `id` varchar(255) NOT NULL,
  `user_id` bigint(20) UNSIGNED DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `payload` longtext NOT NULL,
  `last_activity` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `name` varchar(255) NOT NULL,
  `email` varchar(255) NOT NULL,
  `email_verified_at` timestamp NULL DEFAULT NULL,
  `password` varchar(255) NOT NULL,
  `phone` varchar(40) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `remember_token` varchar(100) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `name`, `email`, `email_verified_at`, `password`, `phone`, `is_active`, `remember_token`, `created_at`, `updated_at`) VALUES
(1, 'Admin', 'admin@rentals.com', NULL, '$2y$12$VgdUyrmwFRIi0mRceZae5.ZchL6Bxs1Xzf7HZatXf2NM73/7DLFlG', '1231231', 1, NULL, '2026-02-08 08:03:16', '2026-02-08 08:03:16');

-- --------------------------------------------------------

--
-- Table structure for table `vehicles`
--

CREATE TABLE `vehicles` (
  `id` bigint(20) NOT NULL,
  `vehicle_number` varchar(50) NOT NULL,
  `type` enum('personal','partners','partner') DEFAULT 'personal',
  `partner_id` bigint(20) UNSIGNED DEFAULT NULL,
  `status` enum('active','inactive') DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `vehicles`
--

INSERT INTO `vehicles` (`id`, `vehicle_number`, `type`, `partner_id`, `status`, `created_at`, `updated_at`) VALUES
(9, 'ABC-001', 'personal', NULL, 'active', '2026-03-25 15:40:29', '2026-03-25 15:40:29'),
(10, 'ABC-002', 'personal', NULL, 'active', '2026-03-25 15:40:43', '2026-03-25 15:40:43'),
(11, 'ABC-003', 'personal', NULL, 'active', '2026-03-25 15:40:51', '2026-03-25 15:40:51'),
(12, 'XYZ-101', 'partner', 5, 'active', '2026-03-25 15:44:22', '2026-03-28 14:05:32'),
(13, 'XYZ-102', 'partner', 4, 'active', '2026-03-25 15:44:40', '2026-03-25 15:44:40'),
(14, 'XYZ-103', 'partner', 3, 'active', '2026-03-25 15:45:02', '2026-03-25 15:45:02'),
(15, 'TRK-001', 'personal', NULL, 'active', '2026-03-28 13:30:41', '2026-03-28 13:30:41');

-- --------------------------------------------------------

--
-- Table structure for table `vehicle_expenses`
--

CREATE TABLE `vehicle_expenses` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `vehicle_id` bigint(20) UNSIGNED NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `description` varchar(255) NOT NULL,
  `expense_date` date NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `vehicle_expenses`
--

INSERT INTO `vehicle_expenses` (`id`, `vehicle_id`, `amount`, `description`, `expense_date`, `created_at`, `updated_at`) VALUES
(1, 9, 2200.00, 'Auto Part Expense Paid month of March - Gari No ABC-001', '2026-03-26', '2026-03-26 15:14:27', '2026-03-26 15:14:27'),
(2, 10, 5400.00, 'Auto Part Expense Paid month of March - Gari No ABC-002', '2026-03-26', '2026-03-26 15:15:29', '2026-03-26 15:15:29');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `cache`
--
ALTER TABLE `cache`
  ADD PRIMARY KEY (`key`),
  ADD KEY `cache_expiration_index` (`expiration`);

--
-- Indexes for table `cache_locks`
--
ALTER TABLE `cache_locks`
  ADD PRIMARY KEY (`key`),
  ADD KEY `cache_locks_expiration_index` (`expiration`);

--
-- Indexes for table `company_expenses`
--
ALTER TABLE `company_expenses`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_expense_date` (`expense_date`);

--
-- Indexes for table `expense_categories`
--
ALTER TABLE `expense_categories`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `expense_categories_name_unique` (`name`);

--
-- Indexes for table `failed_jobs`
--
ALTER TABLE `failed_jobs`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `failed_jobs_uuid_unique` (`uuid`);

--
-- Indexes for table `invoices`
--
ALTER TABLE `invoices`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `invoices_invoice_number_unique` (`invoice_number`),
  ADD KEY `idx_ride_id` (`ride_id`),
  ADD KEY `idx_party_id` (`party_id`),
  ADD KEY `idx_invoice_type` (`invoice_type`),
  ADD KEY `idx_payment_status` (`payment_status`),
  ADD KEY `idx_invoice_date` (`invoice_date`);

--
-- Indexes for table `jobs`
--
ALTER TABLE `jobs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `jobs_queue_index` (`queue`);

--
-- Indexes for table `job_batches`
--
ALTER TABLE `job_batches`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `migrations`
--
ALTER TABLE `migrations`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `parties`
--
ALTER TABLE `parties`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `partners`
--
ALTER TABLE `partners`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `partners_email_unique` (`email`),
  ADD KEY `idx_is_active` (`is_active`);

--
-- Indexes for table `partner_ledger_entries`
--
ALTER TABLE `partner_ledger_entries`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_partner_date` (`partner_id`,`entry_date`),
  ADD KEY `fk_ledger_creator` (`created_by`);

--
-- Indexes for table `password_reset_tokens`
--
ALTER TABLE `password_reset_tokens`
  ADD PRIMARY KEY (`email`);

--
-- Indexes for table `personal_access_tokens`
--
ALTER TABLE `personal_access_tokens`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `personal_access_tokens_token_unique` (`token`),
  ADD KEY `personal_access_tokens_tokenable_type_tokenable_id_index` (`tokenable_type`,`tokenable_id`),
  ADD KEY `personal_access_tokens_expires_at_index` (`expires_at`);

--
-- Indexes for table `rides`
--
ALTER TABLE `rides`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `rides_ride_number_unique` (`ride_number`),
  ADD KEY `idx_vehicle_id` (`vehicle_id`),
  ADD KEY `idx_party_id` (`party_id`),
  ADD KEY `idx_partner_id` (`partner_id`),
  ADD KEY `idx_ride_type` (`ride_type`),
  ADD KEY `idx_is_completed` (`is_completed`),
  ADD KEY `idx_start_date` (`start_date`),
  ADD KEY `idx_completed_date` (`completed_date`);

--
-- Indexes for table `ride_expenses`
--
ALTER TABLE `ride_expenses`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_ride_id` (`ride_id`);

--
-- Indexes for table `sessions`
--
ALTER TABLE `sessions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `sessions_user_id_index` (`user_id`),
  ADD KEY `sessions_last_activity_index` (`last_activity`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `users_email_unique` (`email`);

--
-- Indexes for table `vehicles`
--
ALTER TABLE `vehicles`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `vehicle_number` (`vehicle_number`),
  ADD KEY `fk_vehicles_partner_id` (`partner_id`);

--
-- Indexes for table `vehicle_expenses`
--
ALTER TABLE `vehicle_expenses`
  ADD PRIMARY KEY (`id`),
  ADD KEY `vehicle_expenses_vehicle_id_index` (`vehicle_id`),
  ADD KEY `vehicle_expenses_expense_date_index` (`expense_date`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `company_expenses`
--
ALTER TABLE `company_expenses`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `expense_categories`
--
ALTER TABLE `expense_categories`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT for table `failed_jobs`
--
ALTER TABLE `failed_jobs`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `invoices`
--
ALTER TABLE `invoices`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=41;

--
-- AUTO_INCREMENT for table `jobs`
--
ALTER TABLE `jobs`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `migrations`
--
ALTER TABLE `migrations`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `parties`
--
ALTER TABLE `parties`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `partners`
--
ALTER TABLE `partners`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `partner_ledger_entries`
--
ALTER TABLE `partner_ledger_entries`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `personal_access_tokens`
--
ALTER TABLE `personal_access_tokens`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=40;

--
-- AUTO_INCREMENT for table `rides`
--
ALTER TABLE `rides`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=20;

--
-- AUTO_INCREMENT for table `ride_expenses`
--
ALTER TABLE `ride_expenses`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=32;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `vehicles`
--
ALTER TABLE `vehicles`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=16;

--
-- AUTO_INCREMENT for table `vehicle_expenses`
--
ALTER TABLE `vehicle_expenses`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `partner_ledger_entries`
--
ALTER TABLE `partner_ledger_entries`
  ADD CONSTRAINT `fk_ledger_creator` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_ledger_partner` FOREIGN KEY (`partner_id`) REFERENCES `partners` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `vehicles`
--
ALTER TABLE `vehicles`
  ADD CONSTRAINT `fk_vehicles_partner_id` FOREIGN KEY (`partner_id`) REFERENCES `partners` (`id`) ON DELETE SET NULL;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
