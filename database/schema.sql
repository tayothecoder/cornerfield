-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: localhost
-- Generation Time: Feb 10, 2026 at 01:40 PM
-- Server version: 10.4.28-MariaDB
-- PHP Version: 8.2.4

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `cornerfield_db`
--

-- --------------------------------------------------------

--
-- Table structure for table `admins`
--

CREATE TABLE `admins` (
  `id` int(10) UNSIGNED NOT NULL,
  `username` varchar(100) NOT NULL,
  `email` varchar(255) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `full_name` varchar(255) DEFAULT NULL,
  `role` enum('super_admin','admin','moderator') DEFAULT 'admin',
  `status` tinyint(1) DEFAULT 1,
  `last_login` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `admins`
--

INSERT INTO `admins` (`id`, `username`, `email`, `password_hash`, `full_name`, `role`, `status`, `last_login`, `created_at`, `updated_at`) VALUES
(1, 'cornerfield_admin', 'admin@cornerfield.local', '$2y$12$O4f8rzG8GG6gWcGbSwnSbuPWJe3I/1u0Hmq75bNcVUREVU7b4JAaS', 'Cornerfield Admin', 'super_admin', 1, '2025-09-10 22:46:24', '2025-06-01 00:55:30', '2025-09-10 21:46:24');

-- --------------------------------------------------------

--
-- Table structure for table `admin_sessions`
--

CREATE TABLE `admin_sessions` (
  `id` varchar(128) NOT NULL,
  `admin_id` int(10) UNSIGNED NOT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `last_activity` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `admin_sessions`
--

INSERT INTO `admin_sessions` (`id`, `admin_id`, `ip_address`, `user_agent`, `last_activity`, `created_at`) VALUES
('1f2debe098b806b77b77cbc5767e3740b7ec320e85923923faf09c3c3ee06a13', 1, '::1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '2025-09-10 21:46:24', '2025-09-10 21:46:24');

-- --------------------------------------------------------

--
-- Table structure for table `admin_settings`
--

CREATE TABLE `admin_settings` (
  `id` int(11) NOT NULL,
  `setting_key` varchar(100) NOT NULL,
  `setting_value` text NOT NULL,
  `setting_type` enum('string','integer','boolean','json') DEFAULT 'string',
  `description` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `admin_settings`
--

INSERT INTO `admin_settings` (`id`, `setting_key`, `setting_value`, `setting_type`, `description`, `created_at`, `updated_at`) VALUES
(1, 'profit_distribution_mode', 'immediate', 'string', 'Profit distribution mode: immediate or locked', '2025-06-01 23:47:54', '2025-06-01 23:47:54'),
(2, 'early_withdrawal_penalty', '10', 'integer', 'Early withdrawal penalty percentage', '2025-06-01 23:47:54', '2025-06-01 23:47:54'),
(3, 'show_daily_calculations', '1', 'boolean', 'Show daily profit calculations to users', '2025-06-01 23:47:54', '2025-06-01 23:47:54'),
(4, 'site_name', 'Cornerfield Investment Platform', 'string', 'Platform name displayed to users', '2025-06-02 23:32:58', '2025-06-02 23:32:58'),
(5, 'site_email', 'info@cornerfieldwealth.com', 'string', 'Platform contact email', '2025-06-02 23:32:58', '2025-09-01 22:41:51'),
(6, 'currency_symbol', '$', 'string', 'Currency symbol for display', '2025-06-02 23:32:58', '2025-06-02 23:32:58'),
(7, 'signup_bonus', '50', 'integer', 'Welcome bonus amount for new users', '2025-06-02 23:32:58', '2025-06-03 04:18:10'),
(8, 'referral_bonus_rate', '5', 'integer', 'Referral commission percentage', '2025-06-02 23:32:58', '2025-06-02 23:32:58'),
(9, 'withdrawal_fee_rate', '5', 'integer', 'Withdrawal fee percentage', '2025-06-02 23:32:58', '2025-06-02 23:32:58'),
(10, 'min_withdrawal_amount', '10', 'integer', 'Minimum withdrawal amount', '2025-06-02 23:32:58', '2025-06-02 23:32:58'),
(11, 'max_withdrawal_amount', '50000', 'integer', 'Maximum withdrawal amount per transaction', '2025-06-02 23:32:58', '2025-06-02 23:32:58'),
(12, 'deposit_auto_approval', '0', 'boolean', 'Auto-approve deposits (0=manual, 1=auto)', '2025-06-02 23:32:58', '2025-06-03 18:20:26'),
(13, 'withdrawal_auto_approval', '0', 'boolean', 'Auto-approve withdrawals (0=manual, 1=auto)', '2025-06-02 23:32:58', '2025-06-03 13:35:37'),
(14, 'maintenance_mode', '0', 'boolean', 'Put platform in maintenance mode', '2025-06-02 23:32:58', '2025-06-03 04:24:23'),
(15, 'email_notifications', '0', 'boolean', 'Enable email notifications', '2025-06-02 23:32:58', '2025-06-03 04:18:10'),
(16, 'profit_distribution_locked', '0', 'boolean', 'Lock profits until investment completion', '2025-06-02 23:32:58', '2025-06-02 23:32:58'),
(17, 'show_profit_calculations', '0', 'boolean', 'Show daily profit calculations to users', '2025-06-02 23:32:58', '2025-06-03 04:18:10'),
(18, 'support_email', 'info@cornerfieldwealth.com', 'string', 'Support contact email', '2025-06-02 23:32:58', '2025-09-01 22:41:51'),
(19, 'platform_fee_rate', '2', 'integer', 'Platform management fee percentage', '2025-06-02 23:32:58', '2025-06-02 23:32:58'),
(20, 'email_smtp_host', 'mail.cornerfieldwealth.com', 'string', 'SMTP server host', '2025-09-01 18:17:42', '2025-09-01 22:42:19'),
(21, 'email_smtp_port', '465', 'integer', 'SMTP server port', '2025-09-01 18:17:48', '2025-09-01 22:42:19'),
(22, 'email_smtp_username', 'info@cornerfieldwealth.com', 'string', 'SMTP username', '2025-09-01 18:17:55', '2025-09-01 22:42:19'),
(23, 'email_smtp_password', 'Superadmin1000$', 'string', 'SMTP password', '2025-09-01 18:17:59', '2025-09-01 22:42:19'),
(24, 'email_smtp_encryption', 'ssl', 'string', 'SMTP encryption type', '2025-09-01 18:18:06', '2025-09-01 22:42:19'),
(25, 'email_from_email', 'info@cornerfieldwealth.com', 'string', 'Default from email', '2025-09-01 18:18:11', '2025-09-01 22:42:19'),
(26, 'email_from_name', 'CornerField Wealth', 'string', 'Default from name', '2025-09-01 18:18:19', '2025-09-01 22:42:19'),
(38, 'payment_nowpayments_enabled', '1', 'string', 'Payment gateway setting', '2025-09-02 09:39:19', '2025-09-02 09:39:19'),
(39, 'csrf_token', '5dc7470e6554d6706fa4753d92118217b926b756b0fe1200bdd6c7ea3e133ccc', 'string', 'Payment gateway setting', '2025-09-02 09:39:38', '2025-09-02 09:39:38'),
(40, 'payment_nowpayments_api_key', '3GQSRJC-0TT48D2-QK9F6CG-JHMVWAW', 'string', 'Payment gateway setting', '2025-09-02 09:39:38', '2025-09-02 09:39:38'),
(41, 'payment_nowpayments_ipn_secret', 'JAXaeBx4tGnQCKYtBrAZoOPNqLJZi80R', 'string', 'Payment gateway setting', '2025-09-02 09:39:38', '2025-09-02 09:39:38'),
(42, 'payment_nowpayments_success_url', '', 'string', 'Payment gateway setting', '2025-09-02 09:39:38', '2025-09-02 09:39:38'),
(43, 'payment_nowpayments_cancel_url', '', 'string', 'Payment gateway setting', '2025-09-02 09:39:38', '2025-09-02 09:39:38'),
(44, 'payment_nowpayments_callback_url', '', 'string', 'Payment gateway setting', '2025-09-02 09:39:38', '2025-09-02 09:39:38');

-- --------------------------------------------------------

--
-- Table structure for table `admin_users`
--

CREATE TABLE `admin_users` (
  `id` int(11) NOT NULL,
  `username` varchar(100) NOT NULL,
  `email` varchar(255) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `role` enum('super_admin','admin','moderator') DEFAULT 'admin',
  `permissions` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`permissions`)),
  `last_login` timestamp NULL DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `deposits`
--

CREATE TABLE `deposits` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `transaction_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `deposit_method_id` bigint(20) UNSIGNED NOT NULL,
  `requested_amount` decimal(15,8) NOT NULL,
  `fee_amount` decimal(15,8) NOT NULL DEFAULT 0.00000000,
  `currency` varchar(10) DEFAULT 'USD',
  `crypto_currency` varchar(10) DEFAULT NULL COMMENT 'BTC, ETH, USDT, etc.',
  `network` varchar(50) DEFAULT NULL COMMENT 'TRC20, ERC20, BTC, etc.',
  `deposit_address` varchar(255) DEFAULT NULL COMMENT 'Generated or static wallet address',
  `transaction_hash` varchar(255) DEFAULT NULL COMMENT 'Blockchain TX hash',
  `gateway_transaction_id` varchar(255) DEFAULT NULL COMMENT 'Payment gateway reference',
  `gateway_response` longtext DEFAULT NULL COMMENT 'Full gateway response JSON',
  `proof_of_payment` varchar(255) DEFAULT NULL COMMENT 'Screenshot upload path for manual deposits',
  `admin_notes` text DEFAULT NULL COMMENT 'Admin verification notes',
  `status` enum('pending','processing','completed','failed','cancelled','expired') DEFAULT 'pending',
  `verification_status` enum('pending','verified','rejected') DEFAULT 'pending' COMMENT 'For manual deposits',
  `admin_processed_by` int(10) UNSIGNED DEFAULT NULL,
  `processed_at` timestamp NULL DEFAULT NULL,
  `expires_at` timestamp NULL DEFAULT NULL COMMENT 'For crypto payments with time limits',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `deposits`
--

INSERT INTO `deposits` (`id`, `transaction_id`, `user_id`, `deposit_method_id`, `requested_amount`, `fee_amount`, `currency`, `crypto_currency`, `network`, `deposit_address`, `transaction_hash`, `gateway_transaction_id`, `gateway_response`, `proof_of_payment`, `admin_notes`, `status`, `verification_status`, `admin_processed_by`, `processed_at`, `expires_at`, `created_at`, `updated_at`) VALUES
(1, 18, 3, 2, 10000.00000000, 0.00000000, 'USD', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'Manual deposit by admin: cornerfield_admin', 'completed', 'verified', 1, '2025-06-02 03:06:59', NULL, '2025-06-02 02:06:59', '2025-06-02 02:06:59'),
(2, 21, 4, 2, 10000.00000000, 0.00000000, 'USD', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'Balance addition via user management: No description provided', 'completed', 'verified', 1, '2025-06-02 03:22:37', NULL, '2025-06-02 02:22:37', '2025-06-02 02:22:37'),
(3, 22, 3, 2, 5000.00000000, 0.00000000, 'USD', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'Balance addition via user management: No description provided', 'completed', 'verified', 1, '2025-06-02 03:22:47', NULL, '2025-06-02 02:22:47', '2025-06-02 02:22:47'),
(4, 17, 4, 2, 20000.00000000, 0.00000000, 'USD', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'completed', 'verified', 1, '2025-06-02 01:20:36', NULL, '2025-06-02 00:20:36', '2025-06-02 00:20:36'),
(5, 36, 4, 1, 1000.00000000, 25.00000000, 'USD', NULL, 'ERC20', NULL, NULL, NULL, NULL, '', 'Auto-approved by system', 'completed', 'verified', 1, '2025-06-03 13:54:35', NULL, '2025-06-03 06:10:43', '2025-06-03 12:54:35'),
(6, 39, 5, 1, 1000.00000000, 25.00000000, 'USD', NULL, 'ERC20', NULL, NULL, NULL, NULL, '', 'Auto-approved by system', 'completed', 'verified', 1, '2025-06-03 14:45:14', NULL, '2025-06-03 13:42:53', '2025-06-03 13:45:14');

-- --------------------------------------------------------

--
-- Table structure for table `deposit_methods`
--

CREATE TABLE `deposit_methods` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `gateway_id` int(10) UNSIGNED DEFAULT NULL,
  `logo` varchar(255) DEFAULT NULL,
  `name` varchar(255) NOT NULL,
  `type` enum('auto','manual') NOT NULL DEFAULT 'manual',
  `gateway_code` varchar(255) NOT NULL,
  `charge` double NOT NULL DEFAULT 0,
  `charge_type` enum('percentage','fixed') NOT NULL DEFAULT 'percentage',
  `minimum_deposit` double NOT NULL DEFAULT 0,
  `maximum_deposit` double NOT NULL DEFAULT 999999,
  `rate` double NOT NULL DEFAULT 1,
  `currency` varchar(255) NOT NULL DEFAULT 'USD',
  `currency_symbol` varchar(255) NOT NULL DEFAULT '$',
  `field_options` longtext DEFAULT NULL,
  `payment_details` longtext DEFAULT NULL,
  `status` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `deposit_methods`
--

INSERT INTO `deposit_methods` (`id`, `gateway_id`, `logo`, `name`, `type`, `gateway_code`, `charge`, `charge_type`, `minimum_deposit`, `maximum_deposit`, `rate`, `currency`, `currency_symbol`, `field_options`, `payment_details`, `status`, `created_at`, `updated_at`) VALUES
(1, NULL, NULL, 'Bitcoin (Auto)', 'auto', 'cryptomus', 2.5, 'percentage', 10, 10000, 1, 'USD', '$', NULL, NULL, 1, '2025-05-31 23:49:29', '2025-05-31 23:49:29'),
(2, NULL, NULL, 'Bitcoin (BTC)', 'manual', 'btc_manual', 0, 'fixed', 50, 50000, 1, 'USD', '$', NULL, NULL, 1, '2025-05-31 23:49:29', '2025-06-02 23:32:58'),
(3, NULL, NULL, 'Ethereum (ETH)', 'manual', 'eth_manual', 0, 'fixed', 25, 25000, 1, 'USD', '$', NULL, NULL, 1, '2025-05-31 23:49:29', '2025-06-02 23:32:58'),
(4, NULL, NULL, 'Tether USDT (TRC20)', 'manual', 'usdt_trc20', 0, 'fixed', 10, 100000, 1, 'USD', '$', NULL, NULL, 1, '2025-06-02 23:32:58', '2025-06-02 23:32:58'),
(5, NULL, NULL, 'Binance Smart Chain (BSC)', 'manual', 'bsc_manual', 0.5, 'fixed', 100, 50000000, 1, 'USD', '$', NULL, NULL, 1, '2025-06-02 23:32:58', '2025-08-19 12:59:08');

-- --------------------------------------------------------

--
-- Table structure for table `email_logs`
--

CREATE TABLE `email_logs` (
  `id` int(11) NOT NULL,
  `to_email` varchar(255) NOT NULL,
  `subject` varchar(500) NOT NULL,
  `status` enum('sent','failed','pending') DEFAULT 'pending',
  `error_message` text DEFAULT NULL,
  `sent_at` datetime NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `email_logs`
--

INSERT INTO `email_logs` (`id`, `to_email`, `subject`, `status`, `error_message`, `sent_at`, `created_at`) VALUES
(1, 'test@example.com', 'Test Email', 'sent', NULL, '2025-08-25 06:25:39', '2025-08-25 06:25:39'),
(2, 'tayothecoder@gmail.com', 'Test Email from CornerField', 'failed', 'SMTP Error: Could not connect to SMTP host. Failed to connect to server', '2025-09-01 19:56:14', '2025-09-01 17:56:14'),
(3, 'tayothecoder@gmail.com', 'Test Email from CornerField', 'failed', 'Email service not properly configured. Please set up SMTP credentials first.', '2025-09-02 00:42:28', '2025-09-01 22:42:28'),
(4, 'tayothecoder@gmail.com', 'Test Email from CornerField', 'failed', 'Email service not properly configured. Please set up SMTP credentials first.', '2025-09-02 00:42:33', '2025-09-01 22:42:33'),
(5, 'tayothecoder@gmail.com', 'Welcome to CornerField!', 'failed', 'Email service not properly configured. Please set up SMTP credentials first.', '2025-09-02 00:42:45', '2025-09-01 22:42:45'),
(6, 'test@example.com', 'Welcome to CornerField!', 'sent', '', '2025-09-01 22:48:45', '2025-09-01 22:48:45'),
(7, 'tayothecoder@gmail.com', 'Test Email from CornerField', 'sent', '', '2025-09-02 00:49:54', '2025-09-01 22:49:54'),
(8, 'tayothecoder@gmail.com', 'Welcome to CornerField!', 'sent', '', '2025-09-02 00:50:35', '2025-09-01 22:50:35');

-- --------------------------------------------------------

--
-- Table structure for table `investments`
--

CREATE TABLE `investments` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `user_id` int(11) NOT NULL,
  `schema_id` bigint(20) UNSIGNED NOT NULL,
  `invest_amount` double NOT NULL,
  `total_profit_amount` double NOT NULL DEFAULT 0,
  `last_profit_time` datetime DEFAULT NULL,
  `next_profit_time` datetime DEFAULT NULL,
  `number_of_period` int(11) NOT NULL DEFAULT 30,
  `status` varchar(255) NOT NULL DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `investments`
--

INSERT INTO `investments` (`id`, `user_id`, `schema_id`, `invest_amount`, `total_profit_amount`, `last_profit_time`, `next_profit_time`, `number_of_period`, `status`, `created_at`, `updated_at`) VALUES
(1, 2, 3, 10000, 18000, '2025-08-19 04:07:09', NULL, 60, 'completed', '2025-06-01 06:29:45', '2025-08-19 03:07:09'),
(2, 2, 2, 4500, 5062.5, '2025-08-19 04:07:09', NULL, 45, 'completed', '2025-06-01 07:06:38', '2025-08-19 03:07:09'),
(3, 2, 2, 4500, 5062.5, '2025-08-19 04:07:09', NULL, 45, 'completed', '2025-06-01 07:06:42', '2025-08-19 03:07:09'),
(4, 2, 3, 15000, 27000, '2025-08-19 04:07:09', NULL, 60, 'completed', '2025-06-01 07:14:58', '2025-08-19 03:07:09'),
(5, 2, 1, 500, 300, '2025-08-19 04:07:09', NULL, 30, 'completed', '2025-06-01 07:18:59', '2025-08-19 03:07:09'),
(6, 2, 1, 500, 300, '2025-08-19 04:07:09', NULL, 30, 'completed', '2025-06-01 07:19:10', '2025-08-19 03:07:09'),
(9, 2, 3, 15000, 27000, '2025-08-19 04:07:09', NULL, 60, 'completed', '2025-06-01 16:37:50', '2025-08-19 03:07:09'),
(10, 2, 2, 4000, 4500, '2025-08-19 04:07:09', NULL, 45, 'completed', '2025-06-01 17:39:01', '2025-08-19 03:07:09'),
(11, 3, 3, 8000, 14400, '2025-08-19 04:07:10', NULL, 60, 'completed', '2025-06-02 03:07:51', '2025-08-19 03:07:10'),
(12, 4, 4, 20000, 63000, '2025-09-01 17:44:30', NULL, 90, 'completed', '2025-06-02 03:08:26', '2025-09-01 16:44:30');

-- --------------------------------------------------------

--
-- Table structure for table `investment_schemas`
--

CREATE TABLE `investment_schemas` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `name` varchar(255) NOT NULL,
  `min_amount` double NOT NULL DEFAULT 0,
  `max_amount` double NOT NULL DEFAULT 0,
  `daily_rate` decimal(5,2) NOT NULL,
  `duration_days` int(11) NOT NULL,
  `total_return` decimal(5,2) NOT NULL,
  `featured` tinyint(1) NOT NULL DEFAULT 0,
  `status` tinyint(1) NOT NULL DEFAULT 1,
  `description` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `investment_schemas`
--

INSERT INTO `investment_schemas` (`id`, `name`, `min_amount`, `max_amount`, `daily_rate`, `duration_days`, `total_return`, `featured`, `status`, `description`, `created_at`, `updated_at`) VALUES
(1, 'Basic Plan', 50, 999.99, 2.00, 30, 60.00, 1, 1, 'Perfect for beginners entering the crypto investment world', '2025-05-31 23:49:29', '2025-09-02 23:31:05'),
(2, 'Standard Plan', 1000, 4999.99, 2.50, 45, 112.50, 1, 1, 'Intermediate investment plan for growing your crypto portfolio', '2025-05-31 23:49:29', '2025-06-02 04:03:22'),
(3, 'Premium Plan', 5000, 19999.99, 3.00, 60, 180.00, 1, 1, 'Premium investment tier with enhanced returns', '2025-05-31 23:49:29', '2025-06-02 04:03:17'),
(4, 'Pro Plan', 20000, 999999.99, 3.50, 90, 315.00, 1, 1, 'Elite tier for serious investors seeking maximum returns', '2025-05-31 23:49:29', '2025-06-02 04:03:03');

-- --------------------------------------------------------

--
-- Table structure for table `payment_gateways`
--

CREATE TABLE `payment_gateways` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `code` varchar(50) NOT NULL,
  `type` enum('crypto','bank','wallet') DEFAULT 'crypto',
  `api_endpoint` varchar(255) DEFAULT NULL,
  `api_key_encrypted` text DEFAULT NULL,
  `webhook_secret_encrypted` text DEFAULT NULL,
  `supported_currencies` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`supported_currencies`)),
  `is_active` tinyint(1) DEFAULT 0,
  `settings` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`settings`)),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `profits`
--

CREATE TABLE `profits` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `transaction_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `investment_id` bigint(20) UNSIGNED NOT NULL,
  `schema_id` bigint(20) UNSIGNED NOT NULL,
  `profit_amount` decimal(15,8) NOT NULL,
  `profit_rate` decimal(5,2) NOT NULL COMMENT 'Daily rate used for calculation',
  `investment_amount` decimal(15,8) NOT NULL COMMENT 'Base investment amount',
  `profit_day` int(11) NOT NULL COMMENT 'Day number in investment cycle',
  `profit_type` enum('daily','bonus','completion','manual') DEFAULT 'daily',
  `calculation_date` date NOT NULL,
  `distribution_method` enum('automatic','manual') DEFAULT 'automatic',
  `status` enum('pending','distributed','failed','cancelled') DEFAULT 'distributed',
  `admin_processed_by` int(10) UNSIGNED DEFAULT NULL,
  `processed_at` timestamp NULL DEFAULT NULL,
  `processing_notes` text DEFAULT NULL,
  `next_profit_date` date DEFAULT NULL,
  `is_final_profit` tinyint(1) DEFAULT 0 COMMENT 'Last profit of investment cycle',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `profits`
--

INSERT INTO `profits` (`id`, `transaction_id`, `user_id`, `investment_id`, `schema_id`, `profit_amount`, `profit_rate`, `investment_amount`, `profit_day`, `profit_type`, `calculation_date`, `distribution_method`, `status`, `admin_processed_by`, `processed_at`, `processing_notes`, `next_profit_date`, `is_final_profit`, `created_at`, `updated_at`) VALUES
(1, 11, 2, 2, 2, 112.50000000, 2.50, 4500.00000000, 1, 'daily', '2025-06-01', 'automatic', 'distributed', NULL, NULL, NULL, NULL, 0, '2025-06-01 18:01:33', '2025-06-01 18:01:33'),
(2, 12, 2, 3, 2, 112.50000000, 2.50, 4500.00000000, 1, 'daily', '2025-06-01', 'automatic', 'distributed', NULL, NULL, NULL, NULL, 0, '2025-06-01 18:01:33', '2025-06-01 18:01:33'),
(3, 13, 2, 1, 3, 300.00000000, 3.00, 10000.00000000, 1, 'daily', '2025-06-01', 'automatic', 'distributed', NULL, NULL, NULL, NULL, 0, '2025-06-01 18:01:33', '2025-06-01 18:01:33'),
(4, 23, 2, 5, 1, 10.00000000, 2.00, 500.00000000, 2, 'daily', '2025-06-02', 'automatic', 'distributed', NULL, '2025-06-02 21:25:59', NULL, '2025-06-03', 0, '2025-06-02 22:25:59', '2025-06-02 22:25:59'),
(5, 24, 2, 6, 1, 10.00000000, 2.00, 500.00000000, 2, 'daily', '2025-06-02', 'automatic', 'distributed', NULL, '2025-06-02 21:25:59', NULL, '2025-06-03', 0, '2025-06-02 22:25:59', '2025-06-02 22:25:59'),
(6, 25, 2, 2, 2, 112.50000000, 2.50, 4500.00000000, 2, 'daily', '2025-06-02', 'automatic', 'distributed', NULL, '2025-06-02 21:26:00', NULL, '2025-06-03', 0, '2025-06-02 22:26:00', '2025-06-02 22:26:00'),
(7, 26, 2, 3, 2, 112.50000000, 2.50, 4500.00000000, 2, 'daily', '2025-06-02', 'automatic', 'distributed', NULL, '2025-06-02 21:26:00', NULL, '2025-06-03', 0, '2025-06-02 22:26:00', '2025-06-02 22:26:00'),
(8, 27, 2, 10, 2, 100.00000000, 2.50, 4000.00000000, 2, 'daily', '2025-06-02', 'automatic', 'distributed', NULL, '2025-06-02 21:26:00', NULL, '2025-06-03', 0, '2025-06-02 22:26:00', '2025-06-02 22:26:00'),
(9, 28, 2, 1, 3, 300.00000000, 3.00, 10000.00000000, 2, 'daily', '2025-06-02', 'automatic', 'distributed', NULL, '2025-06-02 21:26:00', NULL, '2025-06-03', 0, '2025-06-02 22:26:00', '2025-06-02 22:26:00'),
(10, 29, 2, 4, 3, 450.00000000, 3.00, 15000.00000000, 2, 'daily', '2025-06-02', 'automatic', 'distributed', NULL, '2025-06-02 21:26:00', NULL, '2025-06-03', 0, '2025-06-02 22:26:00', '2025-06-02 22:26:00'),
(11, 30, 2, 9, 3, 450.00000000, 3.00, 15000.00000000, 2, 'daily', '2025-06-02', 'automatic', 'distributed', NULL, '2025-06-02 21:26:00', NULL, '2025-06-03', 0, '2025-06-02 22:26:00', '2025-06-02 22:26:00'),
(12, 40, 2, 5, 1, 10.00000000, 2.00, 500.00000000, 3, 'daily', '2025-06-04', 'automatic', 'distributed', NULL, '2025-06-04 00:23:00', NULL, '2025-06-05', 0, '2025-06-04 01:23:00', '2025-06-04 01:23:00'),
(13, 41, 2, 6, 1, 10.00000000, 2.00, 500.00000000, 3, 'daily', '2025-06-04', 'automatic', 'distributed', NULL, '2025-06-04 00:23:00', NULL, '2025-06-05', 0, '2025-06-04 01:23:00', '2025-06-04 01:23:00'),
(14, 42, 2, 2, 2, 112.50000000, 2.50, 4500.00000000, 3, 'daily', '2025-06-04', 'automatic', 'distributed', NULL, '2025-06-04 00:23:00', NULL, '2025-06-05', 0, '2025-06-04 01:23:00', '2025-06-04 01:23:00'),
(15, 43, 2, 3, 2, 112.50000000, 2.50, 4500.00000000, 3, 'daily', '2025-06-04', 'automatic', 'distributed', NULL, '2025-06-04 00:23:00', NULL, '2025-06-05', 0, '2025-06-04 01:23:00', '2025-06-04 01:23:00'),
(16, 44, 2, 10, 2, 100.00000000, 2.50, 4000.00000000, 3, 'daily', '2025-06-04', 'automatic', 'distributed', NULL, '2025-06-04 00:23:00', NULL, '2025-06-05', 0, '2025-06-04 01:23:00', '2025-06-04 01:23:00'),
(17, 45, 2, 1, 3, 300.00000000, 3.00, 10000.00000000, 3, 'daily', '2025-06-04', 'automatic', 'distributed', NULL, '2025-06-04 00:23:01', NULL, '2025-06-05', 0, '2025-06-04 01:23:01', '2025-06-04 01:23:01'),
(18, 46, 2, 4, 3, 450.00000000, 3.00, 15000.00000000, 3, 'daily', '2025-06-04', 'automatic', 'distributed', NULL, '2025-06-04 00:23:01', NULL, '2025-06-05', 0, '2025-06-04 01:23:01', '2025-06-04 01:23:01'),
(19, 47, 2, 9, 3, 450.00000000, 3.00, 15000.00000000, 3, 'daily', '2025-06-04', 'automatic', 'distributed', NULL, '2025-06-04 00:23:01', NULL, '2025-06-05', 0, '2025-06-04 01:23:01', '2025-06-04 01:23:01'),
(20, 48, 3, 11, 3, 240.00000000, 3.00, 8000.00000000, 2, 'daily', '2025-06-04', 'automatic', 'distributed', NULL, '2025-06-04 00:23:01', NULL, '2025-06-05', 0, '2025-06-04 01:23:01', '2025-06-04 01:23:01'),
(21, 49, 4, 12, 4, 700.00000000, 3.50, 20000.00000000, 2, 'daily', '2025-06-04', 'automatic', 'distributed', NULL, '2025-06-04 00:23:01', NULL, '2025-06-05', 0, '2025-06-04 01:23:01', '2025-06-04 01:23:01'),
(22, 50, 2, 5, 1, 10.00000000, 2.00, 500.00000000, 6, 'daily', '2025-06-06', 'automatic', 'distributed', NULL, '2025-06-06 14:03:52', NULL, '2025-06-07', 0, '2025-06-06 15:03:52', '2025-06-06 15:03:52'),
(23, 51, 2, 6, 1, 10.00000000, 2.00, 500.00000000, 6, 'daily', '2025-06-06', 'automatic', 'distributed', NULL, '2025-06-06 14:03:52', NULL, '2025-06-07', 0, '2025-06-06 15:03:52', '2025-06-06 15:03:52'),
(24, 52, 2, 2, 2, 112.50000000, 2.50, 4500.00000000, 6, 'daily', '2025-06-06', 'automatic', 'distributed', NULL, '2025-06-06 14:03:52', NULL, '2025-06-07', 0, '2025-06-06 15:03:52', '2025-06-06 15:03:52'),
(25, 53, 2, 3, 2, 112.50000000, 2.50, 4500.00000000, 6, 'daily', '2025-06-06', 'automatic', 'distributed', NULL, '2025-06-06 14:03:52', NULL, '2025-06-07', 0, '2025-06-06 15:03:52', '2025-06-06 15:03:52'),
(26, 54, 2, 10, 2, 100.00000000, 2.50, 4000.00000000, 5, 'daily', '2025-06-06', 'automatic', 'distributed', NULL, '2025-06-06 14:03:52', NULL, '2025-06-07', 0, '2025-06-06 15:03:52', '2025-06-06 15:03:52'),
(27, 55, 2, 1, 3, 300.00000000, 3.00, 10000.00000000, 6, 'daily', '2025-06-06', 'automatic', 'distributed', NULL, '2025-06-06 14:03:52', NULL, '2025-06-07', 0, '2025-06-06 15:03:52', '2025-06-06 15:03:52'),
(28, 56, 2, 4, 3, 450.00000000, 3.00, 15000.00000000, 6, 'daily', '2025-06-06', 'automatic', 'distributed', NULL, '2025-06-06 14:03:53', NULL, '2025-06-07', 0, '2025-06-06 15:03:53', '2025-06-06 15:03:53'),
(29, 57, 2, 9, 3, 450.00000000, 3.00, 15000.00000000, 5, 'daily', '2025-06-06', 'automatic', 'distributed', NULL, '2025-06-06 14:03:53', NULL, '2025-06-07', 0, '2025-06-06 15:03:53', '2025-06-06 15:03:53'),
(30, 58, 3, 11, 3, 240.00000000, 3.00, 8000.00000000, 5, 'daily', '2025-06-06', 'automatic', 'distributed', NULL, '2025-06-06 14:03:53', NULL, '2025-06-07', 0, '2025-06-06 15:03:53', '2025-06-06 15:03:53'),
(31, 59, 4, 12, 4, 700.00000000, 3.50, 20000.00000000, 5, 'daily', '2025-06-06', 'automatic', 'distributed', NULL, '2025-06-06 14:03:53', NULL, '2025-06-07', 0, '2025-06-06 15:03:53', '2025-06-06 15:03:53'),
(32, 78, 4, 12, 4, 700.00000000, 3.50, 20000.00000000, 78, 'daily', '2025-08-19', 'automatic', 'distributed', NULL, '2025-08-19 03:07:10', NULL, '2025-08-20', 0, '2025-08-19 04:07:10', '2025-08-19 04:07:10');

-- --------------------------------------------------------

--
-- Table structure for table `referrals`
--

CREATE TABLE `referrals` (
  `id` int(11) NOT NULL,
  `referrer_id` int(11) NOT NULL,
  `referred_id` int(11) NOT NULL,
  `level` int(11) NOT NULL DEFAULT 1,
  `commission_rate` decimal(5,2) NOT NULL,
  `total_earned` decimal(15,8) DEFAULT 0.00000000,
  `status` enum('active','inactive') DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `referrals`
--

INSERT INTO `referrals` (`id`, `referrer_id`, `referred_id`, `level`, `commission_rate`, `total_earned`, `status`, `created_at`) VALUES
(1, 2, 4, 1, 5.00, 0.00000000, 'active', '2025-06-01 16:58:38');

-- --------------------------------------------------------

--
-- Table structure for table `security_logs`
--

CREATE TABLE `security_logs` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `event_type` varchar(100) NOT NULL,
  `ip_address` varchar(45) NOT NULL,
  `user_agent` text DEFAULT NULL,
  `user_id` int(11) DEFAULT NULL,
  `data` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`data`)),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `security_logs`
--

INSERT INTO `security_logs` (`id`, `event_type`, `ip_address`, `user_agent`, `user_id`, `data`, `created_at`) VALUES
(1, 'admin_impersonation', '::1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/136.0.0.0 Safari/537.36', 2, '{\"admin_id\":1,\"target_user_id\":2,\"action\":\"started\",\"timestamp\":\"2025-06-02 02:33:39\"}', '2025-06-02 00:33:39'),
(2, 'admin_impersonation', '::1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/136.0.0.0 Safari/537.36', 2, '{\"admin_id\":1,\"target_user_id\":2,\"action\":\"stopped\",\"timestamp\":\"2025-06-02 02:33:48\"}', '2025-06-02 00:33:48'),
(3, 'admin_impersonation', '::1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/136.0.0.0 Safari/537.36', 3, '{\"admin_id\":1,\"target_user_id\":3,\"action\":\"started\",\"timestamp\":\"2025-06-02 04:07:22\"}', '2025-06-02 02:07:22'),
(4, 'admin_impersonation', '::1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/136.0.0.0 Safari/537.36', 3, '{\"admin_id\":1,\"target_user_id\":3,\"action\":\"stopped\",\"timestamp\":\"2025-06-02 04:07:59\"}', '2025-06-02 02:07:59'),
(5, 'admin_impersonation', '::1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/136.0.0.0 Safari/537.36', 4, '{\"admin_id\":1,\"target_user_id\":4,\"action\":\"started\",\"timestamp\":\"2025-06-02 04:08:10\"}', '2025-06-02 02:08:10'),
(6, 'admin_impersonation', '::1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/136.0.0.0 Safari/537.36', 4, '{\"admin_id\":1,\"target_user_id\":4,\"action\":\"stopped\",\"timestamp\":\"2025-06-02 04:08:34\"}', '2025-06-02 02:08:34'),
(7, 'admin_impersonation', '::1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/136.0.0.0 Safari/537.36', 4, '{\"admin_id\":1,\"target_user_id\":4,\"action\":\"started\",\"timestamp\":\"2025-06-02 04:09:01\"}', '2025-06-02 02:09:01'),
(8, 'admin_impersonation', '::1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/136.0.0.0 Safari/537.36', 4, '{\"admin_id\":1,\"target_user_id\":4,\"action\":\"stopped\",\"timestamp\":\"2025-06-02 04:09:10\"}', '2025-06-02 02:09:10'),
(9, 'admin_impersonation', '::1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/136.0.0.0 Safari/537.36', 4, '{\"admin_id\":1,\"target_user_id\":4,\"action\":\"started\",\"timestamp\":\"2025-06-03 08:10:16\"}', '2025-06-03 06:10:16'),
(10, 'admin_impersonation', '::1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/136.0.0.0 Safari/537.36', 4, '{\"admin_id\":1,\"target_user_id\":4,\"action\":\"stopped\",\"timestamp\":\"2025-06-03 08:11:03\"}', '2025-06-03 06:11:03'),
(11, 'admin_impersonation', '::1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', 5, '{\"admin_id\":1,\"target_user_id\":5,\"action\":\"started\",\"timestamp\":\"2025-08-28 13:48:43\"}', '2025-08-28 11:48:43'),
(12, 'admin_impersonation', '::1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', 5, '{\"admin_id\":1,\"target_user_id\":5,\"action\":\"started\",\"timestamp\":\"2025-08-28 13:53:18\"}', '2025-08-28 11:53:18'),
(13, 'admin_impersonation', 'unknown', 'unknown', 3, '{\"admin_id\":2,\"target_user_id\":3,\"action\":\"started\",\"timestamp\":\"2025-08-28 11:57:32\"}', '2025-08-28 11:57:32'),
(14, 'admin_impersonation', 'unknown', 'unknown', 3, '{\"admin_id\":2,\"target_user_id\":3,\"action\":\"stopped\",\"timestamp\":\"2025-08-28 11:57:32\"}', '2025-08-28 11:57:32'),
(15, 'admin_impersonation', 'unknown', 'unknown', 3, '{\"admin_id\":1,\"target_user_id\":3,\"action\":\"started\",\"timestamp\":\"2025-08-28 12:01:03\"}', '2025-08-28 12:01:03'),
(16, 'admin_impersonation', 'unknown', 'unknown', 3, '{\"admin_id\":1,\"target_user_id\":3,\"action\":\"stopped\",\"timestamp\":\"2025-08-28 12:01:03\"}', '2025-08-28 12:01:03'),
(17, 'admin_impersonation', '::1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', 5, '{\"admin_id\":1,\"target_user_id\":5,\"action\":\"started\",\"timestamp\":\"2025-08-28 14:05:40\"}', '2025-08-28 12:05:40'),
(18, 'admin_impersonation', '::1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', 5, '{\"admin_id\":1,\"target_user_id\":5,\"action\":\"stopped\",\"timestamp\":\"2025-08-28 14:05:45\"}', '2025-08-28 12:05:45'),
(19, 'admin_impersonation', '::1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', 4, '{\"admin_id\":1,\"target_user_id\":4,\"action\":\"started\",\"timestamp\":\"2025-08-28 14:05:49\"}', '2025-08-28 12:05:49'),
(20, 'admin_impersonation', '::1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', 4, '{\"admin_id\":1,\"target_user_id\":4,\"action\":\"stopped\",\"timestamp\":\"2025-08-28 14:05:52\"}', '2025-08-28 12:05:52'),
(21, 'admin_impersonation', '::1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', 4, '{\"admin_id\":1,\"target_user_id\":4,\"action\":\"started\",\"timestamp\":\"2025-09-10 23:46:39\"}', '2025-09-10 21:46:39');

-- --------------------------------------------------------

--
-- Table structure for table `site_settings`
--

CREATE TABLE `site_settings` (
  `id` int(11) NOT NULL,
  `setting_key` varchar(100) NOT NULL,
  `setting_value` text DEFAULT NULL,
  `setting_type` enum('text','textarea','image','color','number','boolean','json') DEFAULT 'text',
  `category` varchar(50) DEFAULT 'general',
  `description` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `site_settings`
--

INSERT INTO `site_settings` (`id`, `setting_key`, `setting_value`, `setting_type`, `category`, `description`, `created_at`, `updated_at`) VALUES
(1, 'site_name', 'CornerField', 'text', 'general', 'Website name', '2025-09-06 07:29:42', '2025-09-06 07:29:42'),
(2, 'site_tagline', 'Your Gateway to Financial Freedom', 'text', 'general', 'Main tagline displayed on homepage', '2025-09-06 07:29:42', '2025-09-06 07:29:42'),
(3, 'site_description', 'Premier cryptocurrency investment platform for smart, automated investments', 'textarea', 'general', 'Meta description for SEO', '2025-09-06 07:29:42', '2025-09-06 07:29:42'),
(4, 'site_logo', '', 'image', 'branding', 'Main site logo', '2025-09-06 07:29:42', '2025-09-06 07:29:42'),
(5, 'site_favicon', '', 'image', 'branding', 'Site favicon', '2025-09-06 07:29:42', '2025-09-06 07:29:42'),
(6, 'company_name', 'CornerField Investments Ltd', 'text', 'company', 'Legal company name', '2025-09-06 07:29:42', '2025-09-06 07:29:42'),
(7, 'company_address', '123 Investment Street, Financial District, NY 10001', 'textarea', 'company', 'Company address', '2025-09-06 07:29:42', '2025-09-06 07:29:42'),
(8, 'company_phone', '+1 (555) 123-4567', 'text', 'company', 'Contact phone number', '2025-09-06 07:29:42', '2025-09-06 07:29:42'),
(9, 'company_email', 'support@cornerfield.com', 'text', 'company', 'Contact email', '2025-09-06 07:29:42', '2025-09-06 07:29:42'),
(10, 'company_website', 'https://cornerfield.com', 'text', 'company', 'Company website', '2025-09-06 07:29:42', '2025-09-06 07:29:42'),
(11, 'primary_color', '#667eea', 'color', 'theme', 'Primary brand color', '2025-09-06 07:29:42', '2025-09-06 07:29:42'),
(12, 'secondary_color', '#764ba2', 'color', 'theme', 'Secondary brand color', '2025-09-06 07:29:42', '2025-09-06 07:29:42'),
(13, 'success_color', '#10b981', 'color', 'theme', 'Success/positive color', '2025-09-06 07:29:42', '2025-09-06 07:29:42'),
(14, 'warning_color', '#f59e0b', 'color', 'theme', 'Warning color', '2025-09-06 07:29:42', '2025-09-06 07:29:42'),
(15, 'danger_color', '#ef4444', 'color', 'theme', 'Danger/error color', '2025-09-06 07:29:42', '2025-09-06 07:29:42'),
(16, 'homepage_title', 'Welcome to CornerField', 'text', 'content', 'Homepage main title', '2025-09-06 07:29:42', '2025-09-06 07:29:42'),
(17, 'homepage_subtitle', 'Start your investment journey today', 'text', 'content', 'Homepage subtitle', '2025-09-06 07:29:42', '2025-09-06 07:29:42'),
(18, 'about_us', 'CornerField is a leading cryptocurrency investment platform...', 'textarea', 'content', 'About us content', '2025-09-06 07:29:42', '2025-09-06 07:29:42'),
(19, 'maintenance_mode', '0', 'boolean', 'system', 'Enable maintenance mode', '2025-09-06 07:29:42', '2025-09-06 07:29:42'),
(20, 'maintenance_message', 'We are currently performing maintenance. Please check back later.', 'textarea', 'system', 'Maintenance mode message', '2025-09-06 07:29:42', '2025-09-06 07:29:42'),
(21, 'footer_text', '© 2024 CornerField. All rights reserved.', 'text', 'footer', 'Footer copyright text', '2025-09-06 07:29:42', '2025-09-06 07:29:42');

-- --------------------------------------------------------

--
-- Table structure for table `transactions`
--

CREATE TABLE `transactions` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `type` enum('deposit','withdrawal','investment','profit','bonus','referral','principal_return') NOT NULL,
  `amount` decimal(15,8) NOT NULL,
  `fee` decimal(15,8) DEFAULT 0.00000000,
  `net_amount` decimal(15,8) NOT NULL,
  `status` enum('pending','processing','completed','failed','cancelled') DEFAULT 'pending',
  `payment_method` enum('crypto','bank','manual','balance','system','auto') DEFAULT NULL,
  `payment_gateway` varchar(50) DEFAULT NULL,
  `gateway_transaction_id` varchar(255) DEFAULT NULL,
  `wallet_address` varchar(255) DEFAULT NULL,
  `currency` varchar(10) DEFAULT 'USD',
  `description` text DEFAULT NULL,
  `reference_id` bigint(20) DEFAULT NULL,
  `admin_note` text DEFAULT NULL,
  `processed_by` int(11) DEFAULT NULL,
  `admin_processed_by` int(10) UNSIGNED DEFAULT NULL,
  `processed_by_type` enum('user','admin') DEFAULT NULL,
  `processed_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `transactions`
--

INSERT INTO `transactions` (`id`, `user_id`, `type`, `amount`, `fee`, `net_amount`, `status`, `payment_method`, `payment_gateway`, `gateway_transaction_id`, `wallet_address`, `currency`, `description`, `reference_id`, `admin_note`, `processed_by`, `admin_processed_by`, `processed_by_type`, `processed_at`, `created_at`, `updated_at`) VALUES
(1, 2, 'investment', 10000.00000000, 0.00000000, 10000.00000000, 'completed', 'crypto', NULL, 'INV20250601072945B56C40', NULL, 'USD', 'Investment in Digital Gold plan', 1, NULL, NULL, NULL, NULL, NULL, '2025-06-01 06:29:45', '2025-06-02 02:38:07'),
(2, 2, 'investment', 4500.00000000, 0.00000000, 4500.00000000, 'completed', 'crypto', NULL, 'INV202506010DF118', NULL, 'USD', 'Investment in Crypto Silver plan', 2, NULL, NULL, NULL, NULL, NULL, '2025-06-01 07:06:38', '2025-06-01 07:06:38'),
(3, 2, 'investment', 4500.00000000, 0.00000000, 4500.00000000, 'completed', 'crypto', NULL, 'INV20250601117798', NULL, 'USD', 'Investment in Crypto Silver plan', 3, NULL, NULL, NULL, NULL, NULL, '2025-06-01 07:06:42', '2025-06-01 07:06:42'),
(4, 2, 'investment', 15000.00000000, 0.00000000, 15000.00000000, 'completed', 'crypto', NULL, 'INV20250601D4DBDF', NULL, 'USD', 'Investment in Digital Gold plan', 4, NULL, NULL, NULL, NULL, NULL, '2025-06-01 07:14:58', '2025-06-01 07:14:58'),
(5, 2, 'investment', 500.00000000, 0.00000000, 500.00000000, 'completed', 'crypto', NULL, 'INV202506012CCA18', NULL, 'USD', 'Investment in Bitcoin Starter plan', 5, NULL, NULL, NULL, NULL, NULL, '2025-06-01 07:18:59', '2025-06-01 07:18:59'),
(6, 2, 'investment', 500.00000000, 0.00000000, 500.00000000, 'completed', 'crypto', NULL, 'INV20250601B1F973', NULL, 'USD', 'Investment in Bitcoin Starter plan', 6, NULL, NULL, NULL, NULL, NULL, '2025-06-01 07:19:10', '2025-06-01 07:19:10'),
(7, 2, 'withdrawal', 20000.00000000, 1000.00000000, 20000.00000000, 'completed', 'crypto', NULL, 'WTH20250601174413479EBB', 'This1smyWall3tAdress33Str1nG', 'USDT', 'Withdrawal to This1smyWall3tAdress33Str1nG', NULL, NULL, NULL, 1, 'admin', '2025-06-01 22:21:03', '2025-06-01 16:44:13', '2025-06-02 02:38:07'),
(8, 2, 'investment', 15000.00000000, 0.00000000, 15000.00000000, 'completed', 'balance', NULL, 'INV202506011737508EE8DB', NULL, 'USD', 'Investment in Digital Gold', 9, NULL, NULL, NULL, NULL, NULL, '2025-06-01 16:37:50', '2025-06-02 02:38:07'),
(9, 2, 'investment', 4000.00000000, 0.00000000, 4000.00000000, 'completed', 'balance', NULL, 'INV2025060118390159D93D', NULL, 'USD', 'Investment in Crypto Silver', 10, NULL, NULL, NULL, NULL, NULL, '2025-06-01 17:39:01', '2025-06-02 02:38:07'),
(11, 2, 'profit', 112.50000000, 0.00000000, 112.50000000, 'completed', 'system', NULL, 'PRF202506011901339CBCB6', NULL, 'USD', 'Daily profit from investment', 2, NULL, NULL, NULL, NULL, '2025-06-01 18:01:33', '2025-06-01 18:01:33', '2025-06-02 02:38:07'),
(12, 2, 'profit', 112.50000000, 0.00000000, 112.50000000, 'completed', 'system', NULL, 'PRF20250601190133B5880E', NULL, 'USD', 'Daily profit from investment', 3, NULL, NULL, NULL, NULL, '2025-06-01 18:01:33', '2025-06-01 18:01:33', '2025-06-02 02:38:07'),
(13, 2, 'profit', 300.00000000, 0.00000000, 300.00000000, 'completed', 'system', NULL, 'PRF2025060119013316BD26', NULL, 'USD', 'Daily profit from investment', 1, NULL, NULL, NULL, NULL, '2025-06-01 18:01:33', '2025-06-01 18:01:33', '2025-06-02 02:38:07'),
(14, 2, 'withdrawal', 5000.00000000, 250.00000000, 5000.00000000, 'failed', 'crypto', NULL, 'WTH20250602002106F7DEB1', 'This1smyWall3tAdress33Str1nG', 'BTC', 'Withdrawal to This1smyWall3tAdress33Str1nG', NULL, NULL, NULL, 1, 'admin', '2025-06-01 23:41:58', '2025-06-01 23:21:06', '2025-06-02 02:38:07'),
(15, 2, 'withdrawal', 5000.00000000, 250.00000000, 5000.00000000, 'completed', 'crypto', NULL, 'WTH20250602004212BE0554', 'This1smyWall3tAdress33Str1nG', 'BTC', 'Withdrawal to This1smyWall3tAdress33Str1nG', NULL, NULL, NULL, 1, 'admin', '2025-06-02 00:03:20', '2025-06-01 23:42:12', '2025-06-02 02:38:07'),
(16, 2, 'withdrawal', 6214.00000000, 310.70000000, 6214.00000000, 'completed', 'crypto', NULL, 'WTH202506020103035F496F', 'This1smyWall3tAdress33Str1nG', 'BTC', 'Withdrawal to This1smyWall3tAdress33Str1nG', NULL, NULL, NULL, 1, 'admin', '2025-06-02 00:03:26', '2025-06-02 00:03:03', '2025-06-02 02:38:07'),
(17, 4, 'deposit', 20000.00000000, 0.00000000, 20000.00000000, 'completed', 'manual', NULL, 'DEP202506020120365F1C24', NULL, 'USD', 'Manual balance add', NULL, NULL, NULL, 1, 'admin', '2025-06-02 01:20:36', '2025-06-02 00:20:36', '2025-06-02 02:38:07'),
(18, 3, 'deposit', 10000.00000000, 0.00000000, 10000.00000000, 'completed', 'manual', NULL, 'MANUAL_1748830019_3', NULL, 'USD', '', NULL, NULL, NULL, 1, 'admin', '2025-06-02 03:06:59', '2025-06-02 02:06:59', '2025-06-02 02:06:59'),
(19, 3, 'investment', 8000.00000000, 0.00000000, 8000.00000000, 'completed', 'balance', NULL, 'INV202506020407515A4E93', NULL, 'USD', 'Investment in Digital Gold', 11, NULL, NULL, NULL, NULL, NULL, '2025-06-02 03:07:51', '2025-06-02 02:38:07'),
(20, 4, 'investment', 20000.00000000, 0.00000000, 20000.00000000, 'completed', 'balance', NULL, 'INV2025060204082697222C', NULL, 'USD', 'Investment in Cornerfield Elite', 12, NULL, NULL, NULL, NULL, NULL, '2025-06-02 03:08:26', '2025-06-02 02:38:07'),
(21, 4, 'deposit', 10000.00000000, 0.00000000, 10000.00000000, 'completed', 'manual', NULL, 'ADMIN_1748830957_4', NULL, 'USD', 'Manual balance addition by admin', NULL, NULL, NULL, 1, 'admin', '2025-06-02 03:22:37', '2025-06-02 02:22:37', '2025-06-02 02:22:37'),
(22, 3, 'deposit', 5000.00000000, 0.00000000, 5000.00000000, 'completed', 'manual', NULL, 'ADMIN_1748830967_3', NULL, 'USD', 'Manual balance addition by admin', NULL, NULL, NULL, 1, 'admin', '2025-06-02 03:22:47', '2025-06-02 02:22:47', '2025-06-02 02:22:47'),
(23, 2, 'profit', 10.00000000, 0.00000000, 10.00000000, 'completed', 'system', NULL, NULL, NULL, 'USD', 'Daily profit from Basic Plan', 5, NULL, NULL, NULL, NULL, '2025-06-02 21:25:59', '2025-06-02 22:25:59', '2025-06-02 22:25:59'),
(24, 2, 'profit', 10.00000000, 0.00000000, 10.00000000, 'completed', 'system', NULL, NULL, NULL, 'USD', 'Daily profit from Basic Plan', 6, NULL, NULL, NULL, NULL, '2025-06-02 21:25:59', '2025-06-02 22:25:59', '2025-06-02 22:25:59'),
(25, 2, 'profit', 112.50000000, 0.00000000, 112.50000000, 'completed', 'system', NULL, NULL, NULL, 'USD', 'Daily profit from Standard Plan', 2, NULL, NULL, NULL, NULL, '2025-06-02 21:26:00', '2025-06-02 22:26:00', '2025-06-02 22:26:00'),
(26, 2, 'profit', 112.50000000, 0.00000000, 112.50000000, 'completed', 'system', NULL, NULL, NULL, 'USD', 'Daily profit from Standard Plan', 3, NULL, NULL, NULL, NULL, '2025-06-02 21:26:00', '2025-06-02 22:26:00', '2025-06-02 22:26:00'),
(27, 2, 'profit', 100.00000000, 0.00000000, 100.00000000, 'completed', 'system', NULL, NULL, NULL, 'USD', 'Daily profit from Standard Plan', 10, NULL, NULL, NULL, NULL, '2025-06-02 21:26:00', '2025-06-02 22:26:00', '2025-06-02 22:26:00'),
(28, 2, 'profit', 300.00000000, 0.00000000, 300.00000000, 'completed', 'system', NULL, NULL, NULL, 'USD', 'Daily profit from Premium Plan', 1, NULL, NULL, NULL, NULL, '2025-06-02 21:26:00', '2025-06-02 22:26:00', '2025-06-02 22:26:00'),
(29, 2, 'profit', 450.00000000, 0.00000000, 450.00000000, 'completed', 'system', NULL, NULL, NULL, 'USD', 'Daily profit from Premium Plan', 4, NULL, NULL, NULL, NULL, '2025-06-02 21:26:00', '2025-06-02 22:26:00', '2025-06-02 22:26:00'),
(30, 2, 'profit', 450.00000000, 0.00000000, 450.00000000, 'completed', 'system', NULL, NULL, NULL, 'USD', 'Daily profit from Premium Plan', 9, NULL, NULL, NULL, NULL, '2025-06-02 21:26:00', '2025-06-02 22:26:00', '2025-06-02 22:26:00'),
(32, 5, 'bonus', 50.00000000, 0.00000000, 50.00000000, 'completed', 'system', NULL, NULL, NULL, 'USD', 'Welcome signup bonus', NULL, NULL, NULL, NULL, NULL, NULL, '2025-06-03 05:35:08', '2025-06-03 05:35:08'),
(33, 5, 'withdrawal', 20.00000000, 1.00000000, 20.00000000, 'failed', 'crypto', NULL, NULL, 'This1smyWall3tAdress33Str1nG', 'USDT', 'Withdrawal to This1smyWall3tAdress33Str1nG', NULL, NULL, NULL, 1, 'admin', '2025-06-03 06:27:27', '2025-06-03 05:36:53', '2025-06-03 06:27:27'),
(34, 5, 'withdrawal', 20.00000000, 1.00000000, 20.00000000, 'failed', 'crypto', NULL, NULL, 'This1smyWall3tAdress33Str1nG', 'BTC', 'Withdrawal to This1smyWall3tAdress33Str1nG', NULL, '', NULL, 1, 'admin', '2025-06-03 06:39:55', '2025-06-03 06:27:57', '2025-06-03 06:39:55'),
(35, 5, 'withdrawal', 20.00000000, 1.00000000, 20.00000000, 'failed', 'crypto', NULL, NULL, 'This1smyWall3tAdress33Str1nG', 'BTC', 'Withdrawal to This1smyWall3tAdress33Str1nG', NULL, '', NULL, 1, 'admin', '2025-06-03 06:41:11', '2025-06-03 06:38:42', '2025-06-03 06:41:11'),
(36, 4, 'deposit', 1000.00000000, 25.00000000, 975.00000000, 'completed', 'crypto', NULL, 'DEP20250603081043C109E9', NULL, 'USD', 'Deposit via Bitcoin (Auto)', NULL, NULL, NULL, 1, 'admin', '2025-06-03 13:54:35', '2025-06-03 06:10:43', '2025-06-03 12:54:35'),
(37, 5, 'withdrawal', 20.00000000, 1.00000000, 20.00000000, 'completed', 'crypto', NULL, NULL, 'This1smyWall3tAdress33Str1nG', 'BTC', 'Withdrawal to This1smyWall3tAdress33Str1nG', NULL, '', NULL, 1, 'admin', '2025-06-03 14:34:14', '2025-06-03 13:58:21', '2025-06-03 14:34:14'),
(38, 5, 'withdrawal', 20.00000000, 1.00000000, 20.00000000, 'completed', 'crypto', NULL, NULL, 'This1smyWall3tAdress33Str1nG', 'BTC', 'Withdrawal to This1smyWall3tAdress33Str1nG', NULL, NULL, NULL, 1, 'admin', '2025-06-03 14:34:14', '2025-06-03 14:33:29', '2025-06-03 14:34:14'),
(39, 5, 'deposit', 1000.00000000, 25.00000000, 975.00000000, 'completed', 'crypto', NULL, 'DEP20250603154253FA254E', NULL, 'USD', 'Deposit via Bitcoin (Auto)', NULL, NULL, NULL, 1, 'admin', '2025-06-03 14:45:14', '2025-06-03 13:42:53', '2025-06-03 13:45:14'),
(40, 2, 'profit', 10.00000000, 0.00000000, 10.00000000, 'completed', 'system', NULL, NULL, NULL, 'USD', 'Daily profit from Basic Plan', 5, NULL, NULL, NULL, NULL, '2025-06-04 00:23:00', '2025-06-04 01:23:00', '2025-06-04 01:23:00'),
(41, 2, 'profit', 10.00000000, 0.00000000, 10.00000000, 'completed', 'system', NULL, NULL, NULL, 'USD', 'Daily profit from Basic Plan', 6, NULL, NULL, NULL, NULL, '2025-06-04 00:23:00', '2025-06-04 01:23:00', '2025-06-04 01:23:00'),
(42, 2, 'profit', 112.50000000, 0.00000000, 112.50000000, 'completed', 'system', NULL, NULL, NULL, 'USD', 'Daily profit from Standard Plan', 2, NULL, NULL, NULL, NULL, '2025-06-04 00:23:00', '2025-06-04 01:23:00', '2025-06-04 01:23:00'),
(43, 2, 'profit', 112.50000000, 0.00000000, 112.50000000, 'completed', 'system', NULL, NULL, NULL, 'USD', 'Daily profit from Standard Plan', 3, NULL, NULL, NULL, NULL, '2025-06-04 00:23:00', '2025-06-04 01:23:00', '2025-06-04 01:23:00'),
(44, 2, 'profit', 100.00000000, 0.00000000, 100.00000000, 'completed', 'system', NULL, NULL, NULL, 'USD', 'Daily profit from Standard Plan', 10, NULL, NULL, NULL, NULL, '2025-06-04 00:23:00', '2025-06-04 01:23:00', '2025-06-04 01:23:00'),
(45, 2, 'profit', 300.00000000, 0.00000000, 300.00000000, 'completed', 'system', NULL, NULL, NULL, 'USD', 'Daily profit from Premium Plan', 1, NULL, NULL, NULL, NULL, '2025-06-04 00:23:01', '2025-06-04 01:23:01', '2025-06-04 01:23:01'),
(46, 2, 'profit', 450.00000000, 0.00000000, 450.00000000, 'completed', 'system', NULL, NULL, NULL, 'USD', 'Daily profit from Premium Plan', 4, NULL, NULL, NULL, NULL, '2025-06-04 00:23:01', '2025-06-04 01:23:01', '2025-06-04 01:23:01'),
(47, 2, 'profit', 450.00000000, 0.00000000, 450.00000000, 'completed', 'system', NULL, NULL, NULL, 'USD', 'Daily profit from Premium Plan', 9, NULL, NULL, NULL, NULL, '2025-06-04 00:23:01', '2025-06-04 01:23:01', '2025-06-04 01:23:01'),
(48, 3, 'profit', 240.00000000, 0.00000000, 240.00000000, 'completed', 'system', NULL, NULL, NULL, 'USD', 'Daily profit from Premium Plan', 11, NULL, NULL, NULL, NULL, '2025-06-04 00:23:01', '2025-06-04 01:23:01', '2025-06-04 01:23:01'),
(49, 4, 'profit', 700.00000000, 0.00000000, 700.00000000, 'completed', 'system', NULL, NULL, NULL, 'USD', 'Daily profit from Pro Plan', 12, NULL, NULL, NULL, NULL, '2025-06-04 00:23:01', '2025-06-04 01:23:01', '2025-06-04 01:23:01'),
(50, 2, 'profit', 10.00000000, 0.00000000, 10.00000000, 'completed', 'system', NULL, NULL, NULL, 'USD', 'Daily profit from Basic Plan', 5, NULL, NULL, NULL, NULL, '2025-06-06 14:03:52', '2025-06-06 15:03:52', '2025-06-06 15:03:52'),
(51, 2, 'profit', 10.00000000, 0.00000000, 10.00000000, 'completed', 'system', NULL, NULL, NULL, 'USD', 'Daily profit from Basic Plan', 6, NULL, NULL, NULL, NULL, '2025-06-06 14:03:52', '2025-06-06 15:03:52', '2025-06-06 15:03:52'),
(52, 2, 'profit', 112.50000000, 0.00000000, 112.50000000, 'completed', 'system', NULL, NULL, NULL, 'USD', 'Daily profit from Standard Plan', 2, NULL, NULL, NULL, NULL, '2025-06-06 14:03:52', '2025-06-06 15:03:52', '2025-06-06 15:03:52'),
(53, 2, 'profit', 112.50000000, 0.00000000, 112.50000000, 'completed', 'system', NULL, NULL, NULL, 'USD', 'Daily profit from Standard Plan', 3, NULL, NULL, NULL, NULL, '2025-06-06 14:03:52', '2025-06-06 15:03:52', '2025-06-06 15:03:52'),
(54, 2, 'profit', 100.00000000, 0.00000000, 100.00000000, 'completed', 'system', NULL, NULL, NULL, 'USD', 'Daily profit from Standard Plan', 10, NULL, NULL, NULL, NULL, '2025-06-06 14:03:52', '2025-06-06 15:03:52', '2025-06-06 15:03:52'),
(55, 2, 'profit', 300.00000000, 0.00000000, 300.00000000, 'completed', 'system', NULL, NULL, NULL, 'USD', 'Daily profit from Premium Plan', 1, NULL, NULL, NULL, NULL, '2025-06-06 14:03:52', '2025-06-06 15:03:52', '2025-06-06 15:03:52'),
(56, 2, 'profit', 450.00000000, 0.00000000, 450.00000000, 'completed', 'system', NULL, NULL, NULL, 'USD', 'Daily profit from Premium Plan', 4, NULL, NULL, NULL, NULL, '2025-06-06 14:03:52', '2025-06-06 15:03:52', '2025-06-06 15:03:52'),
(57, 2, 'profit', 450.00000000, 0.00000000, 450.00000000, 'completed', 'system', NULL, NULL, NULL, 'USD', 'Daily profit from Premium Plan', 9, NULL, NULL, NULL, NULL, '2025-06-06 14:03:53', '2025-06-06 15:03:53', '2025-06-06 15:03:53'),
(58, 3, 'profit', 240.00000000, 0.00000000, 240.00000000, 'completed', 'system', NULL, NULL, NULL, 'USD', 'Daily profit from Premium Plan', 11, NULL, NULL, NULL, NULL, '2025-06-06 14:03:53', '2025-06-06 15:03:53', '2025-06-06 15:03:53'),
(59, 4, 'profit', 700.00000000, 0.00000000, 700.00000000, 'completed', 'system', NULL, NULL, NULL, 'USD', 'Daily profit from Pro Plan', 12, NULL, NULL, NULL, NULL, '2025-06-06 14:03:53', '2025-06-06 15:03:53', '2025-06-06 15:03:53'),
(60, 2, 'profit', 300.00000000, 0.00000000, 300.00000000, 'completed', 'system', NULL, NULL, NULL, 'USD', 'Daily profit from investment', 1, NULL, NULL, NULL, NULL, '2025-08-19 03:07:09', '2025-08-19 03:07:09', '2025-08-19 03:07:09'),
(61, 2, 'principal_return', 10000.00000000, 0.00000000, 10000.00000000, 'completed', 'system', NULL, NULL, NULL, 'USD', 'Principal return from completed Premium Plan investment', 1, NULL, NULL, NULL, NULL, '2025-08-19 03:07:09', '2025-08-19 03:07:09', '2025-08-19 03:07:09'),
(62, 2, 'profit', 112.50000000, 0.00000000, 112.50000000, 'completed', 'system', NULL, NULL, NULL, 'USD', 'Daily profit from investment', 2, NULL, NULL, NULL, NULL, '2025-08-19 03:07:09', '2025-08-19 03:07:09', '2025-08-19 03:07:09'),
(63, 2, 'principal_return', 4500.00000000, 0.00000000, 4500.00000000, 'completed', 'system', NULL, NULL, NULL, 'USD', 'Principal return from completed Standard Plan investment', 2, NULL, NULL, NULL, NULL, '2025-08-19 03:07:09', '2025-08-19 03:07:09', '2025-08-19 03:07:09'),
(64, 2, 'profit', 112.50000000, 0.00000000, 112.50000000, 'completed', 'system', NULL, NULL, NULL, 'USD', 'Daily profit from investment', 3, NULL, NULL, NULL, NULL, '2025-08-19 03:07:09', '2025-08-19 03:07:09', '2025-08-19 03:07:09'),
(65, 2, 'principal_return', 4500.00000000, 0.00000000, 4500.00000000, 'completed', 'system', NULL, NULL, NULL, 'USD', 'Principal return from completed Standard Plan investment', 3, NULL, NULL, NULL, NULL, '2025-08-19 03:07:09', '2025-08-19 03:07:09', '2025-08-19 03:07:09'),
(66, 2, 'profit', 450.00000000, 0.00000000, 450.00000000, 'completed', 'system', NULL, NULL, NULL, 'USD', 'Daily profit from investment', 4, NULL, NULL, NULL, NULL, '2025-08-19 03:07:09', '2025-08-19 03:07:09', '2025-08-19 03:07:09'),
(67, 2, 'principal_return', 15000.00000000, 0.00000000, 15000.00000000, 'completed', 'system', NULL, NULL, NULL, 'USD', 'Principal return from completed Premium Plan investment', 4, NULL, NULL, NULL, NULL, '2025-08-19 03:07:09', '2025-08-19 03:07:09', '2025-08-19 03:07:09'),
(68, 2, 'profit', 10.00000000, 0.00000000, 10.00000000, 'completed', 'system', NULL, NULL, NULL, 'USD', 'Daily profit from investment', 5, NULL, NULL, NULL, NULL, '2025-08-19 03:07:09', '2025-08-19 03:07:09', '2025-08-19 03:07:09'),
(69, 2, 'principal_return', 500.00000000, 0.00000000, 500.00000000, 'completed', 'system', NULL, NULL, NULL, 'USD', 'Principal return from completed Basic Plan investment', 5, NULL, NULL, NULL, NULL, '2025-08-19 03:07:09', '2025-08-19 03:07:09', '2025-08-19 03:07:09'),
(70, 2, 'profit', 10.00000000, 0.00000000, 10.00000000, 'completed', 'system', NULL, NULL, NULL, 'USD', 'Daily profit from investment', 6, NULL, NULL, NULL, NULL, '2025-08-19 03:07:09', '2025-08-19 03:07:09', '2025-08-19 03:07:09'),
(71, 2, 'principal_return', 500.00000000, 0.00000000, 500.00000000, 'completed', 'system', NULL, NULL, NULL, 'USD', 'Principal return from completed Basic Plan investment', 6, NULL, NULL, NULL, NULL, '2025-08-19 03:07:09', '2025-08-19 03:07:09', '2025-08-19 03:07:09'),
(72, 2, 'profit', 450.00000000, 0.00000000, 450.00000000, 'completed', 'system', NULL, NULL, NULL, 'USD', 'Daily profit from investment', 9, NULL, NULL, NULL, NULL, '2025-08-19 03:07:09', '2025-08-19 03:07:09', '2025-08-19 03:07:09'),
(73, 2, 'principal_return', 15000.00000000, 0.00000000, 15000.00000000, 'completed', 'system', NULL, NULL, NULL, 'USD', 'Principal return from completed Premium Plan investment', 9, NULL, NULL, NULL, NULL, '2025-08-19 03:07:09', '2025-08-19 03:07:09', '2025-08-19 03:07:09'),
(74, 2, 'profit', 100.00000000, 0.00000000, 100.00000000, 'completed', 'system', NULL, NULL, NULL, 'USD', 'Daily profit from investment', 10, NULL, NULL, NULL, NULL, '2025-08-19 03:07:09', '2025-08-19 03:07:09', '2025-08-19 03:07:09'),
(75, 2, 'principal_return', 4000.00000000, 0.00000000, 4000.00000000, 'completed', 'system', NULL, NULL, NULL, 'USD', 'Principal return from completed Standard Plan investment', 10, NULL, NULL, NULL, NULL, '2025-08-19 03:07:09', '2025-08-19 03:07:09', '2025-08-19 03:07:09'),
(76, 3, 'profit', 240.00000000, 0.00000000, 240.00000000, 'completed', 'system', NULL, NULL, NULL, 'USD', 'Daily profit from investment', 11, NULL, NULL, NULL, NULL, '2025-08-19 03:07:10', '2025-08-19 03:07:10', '2025-08-19 03:07:10'),
(77, 3, 'principal_return', 8000.00000000, 0.00000000, 8000.00000000, 'completed', 'system', NULL, NULL, NULL, 'USD', 'Principal return from completed Premium Plan investment', 11, NULL, NULL, NULL, NULL, '2025-08-19 03:07:10', '2025-08-19 03:07:10', '2025-08-19 03:07:10'),
(78, 4, 'profit', 700.00000000, 0.00000000, 700.00000000, 'completed', 'system', NULL, NULL, NULL, 'USD', 'Daily profit from Pro Plan', 12, NULL, NULL, NULL, NULL, '2025-08-19 03:07:10', '2025-08-19 04:07:10', '2025-08-19 04:07:10'),
(79, 4, 'profit', 700.00000000, 0.00000000, 700.00000000, 'completed', 'system', NULL, NULL, NULL, 'USD', 'Daily profit from investment', 12, NULL, NULL, NULL, NULL, '2025-09-01 16:44:30', '2025-09-01 16:44:30', '2025-09-01 16:44:30'),
(80, 4, 'principal_return', 20000.00000000, 0.00000000, 20000.00000000, 'completed', 'system', NULL, NULL, NULL, 'USD', 'Principal return from completed Pro Plan investment', 12, NULL, NULL, NULL, NULL, '2025-09-01 16:44:30', '2025-09-01 16:44:30', '2025-09-01 16:44:30');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `email` varchar(255) NOT NULL,
  `username` varchar(100) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `first_name` varchar(100) DEFAULT NULL,
  `last_name` varchar(100) DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `country` varchar(100) DEFAULT NULL,
  `balance` decimal(15,8) DEFAULT 0.00000000,
  `locked_balance` decimal(15,8) DEFAULT 0.00000000,
  `bonus_balance` decimal(15,8) DEFAULT 0.00000000,
  `total_invested` decimal(15,8) DEFAULT 0.00000000,
  `total_withdrawn` decimal(15,8) DEFAULT 0.00000000,
  `total_earned` decimal(15,8) DEFAULT 0.00000000,
  `referral_code` varchar(20) DEFAULT NULL,
  `referred_by` int(11) DEFAULT NULL,
  `kyc_status` enum('pending','approved','rejected') DEFAULT 'pending',
  `kyc_document_path` varchar(255) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `is_admin` tinyint(1) DEFAULT 0,
  `email_verified` tinyint(1) DEFAULT 0,
  `email_verification_token` varchar(255) DEFAULT NULL,
  `password_reset_token` varchar(255) DEFAULT NULL,
  `password_reset_expires` timestamp NULL DEFAULT NULL,
  `two_factor_secret` varchar(32) DEFAULT NULL,
  `two_factor_enabled` tinyint(1) DEFAULT 0,
  `login_attempts` int(11) DEFAULT 0,
  `last_login_attempt` timestamp NULL DEFAULT NULL,
  `last_login` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `email`, `username`, `password_hash`, `first_name`, `last_name`, `phone`, `country`, `balance`, `locked_balance`, `bonus_balance`, `total_invested`, `total_withdrawn`, `total_earned`, `referral_code`, `referred_by`, `kyc_status`, `kyc_document_path`, `is_active`, `is_admin`, `email_verified`, `email_verification_token`, `password_reset_token`, `password_reset_expires`, `two_factor_secret`, `two_factor_enabled`, `login_attempts`, `last_login_attempt`, `last_login`, `created_at`, `updated_at`) VALUES
(2, 'admin@cornerfield.local', 'cornerfield_admin', '$2y$10$GK3fop7f.b0PwNFe76krF.CTPDFTYMgOW0qwqaXIarXrBcAGdGU9i', 'John', 'Smith', '+1509319725', 'US', 1010180.30000000, 0.00000000, 500.00000000, 54000.00000000, 287214.00000000, 6705.00000000, '2CA28A7F', NULL, 'pending', NULL, 1, 0, 1, NULL, NULL, NULL, NULL, 0, 0, NULL, '2025-06-01 00:08:18', '2025-05-31 05:26:52', '2025-08-19 03:07:09'),
(3, 'badman@coder.com', 'badman', '$2y$10$fCqAzYk0PM/uV7XUz0qt5eU8oHzL18srE94KxPz.LatfdfsgkmMcy', 'badman', 'coder', NULL, 'US', 15745.00000000, 0.00000000, 0.00000000, 8000.00000000, 0.00000000, 720.00000000, '27226BCE', NULL, 'pending', NULL, 1, 0, 1, NULL, NULL, NULL, NULL, 0, 0, NULL, NULL, '2025-06-01 06:42:03', '2025-08-19 03:07:10'),
(4, 'tayothecoder@gmail.com', 'tayothecoder', '$2y$10$Ytvx7No1HERY6cutmDN9wODEDOqmohwuYXVpfx0yWsSCeZIchwm3i', 'Omotayo', 'Aseniserare', NULL, 'NG', 33825.00000000, 0.00000000, 0.00000000, 20000.00000000, 0.00000000, 2800.00000000, '0EFCD11A', 2, 'pending', NULL, 1, 0, 1, NULL, NULL, NULL, NULL, 0, 0, NULL, NULL, '2025-06-01 16:58:38', '2025-09-01 16:44:30'),
(5, 'rappa@punch.com', 'rappa', '$2y$10$ikEUjGteV8e8ueyNfxeyJu4JUMbGBjbV38bPbvn/MbrU7AAczT1cW', 'Met', 'Artrust', NULL, 'US', 1008.00000000, 0.00000000, 0.00000000, 0.00000000, 40.00000000, 0.00000000, '1FB84950', NULL, 'pending', NULL, 1, 0, 0, NULL, NULL, NULL, NULL, 0, 0, NULL, NULL, '2025-06-03 05:35:08', '2025-06-03 13:45:14');

-- --------------------------------------------------------

--
-- Table structure for table `user_2fa`
--

CREATE TABLE `user_2fa` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `user_id` int(11) NOT NULL,
  `secret_key` varchar(255) NOT NULL,
  `backup_codes` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`backup_codes`)),
  `is_enabled` tinyint(1) DEFAULT 0,
  `last_used_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `user_documents`
--

CREATE TABLE `user_documents` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `user_id` int(11) NOT NULL,
  `document_type` enum('passport','license','utility_bill','bank_statement') NOT NULL,
  `file_path` varchar(500) NOT NULL,
  `status` enum('pending','approved','rejected') DEFAULT 'pending',
  `reviewed_by` int(11) DEFAULT NULL,
  `reviewed_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `user_sessions`
--

CREATE TABLE `user_sessions` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `user_id` int(11) NOT NULL,
  `session_token` varchar(255) NOT NULL,
  `ip_address` varchar(45) NOT NULL,
  `user_agent` text DEFAULT NULL,
  `device_fingerprint` varchar(255) DEFAULT NULL,
  `location` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`location`)),
  `is_active` tinyint(1) DEFAULT 1,
  `expires_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `user_wallets`
--

CREATE TABLE `user_wallets` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `user_id` int(11) NOT NULL,
  `currency` varchar(20) NOT NULL,
  `network` varchar(50) NOT NULL,
  `address` varchar(255) NOT NULL,
  `is_verified` tinyint(1) DEFAULT 0,
  `is_default` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `withdrawals`
--

CREATE TABLE `withdrawals` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `transaction_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `requested_amount` decimal(15,8) NOT NULL,
  `fee_amount` decimal(15,8) NOT NULL DEFAULT 0.00000000,
  `wallet_address` varchar(255) NOT NULL,
  `currency` varchar(10) DEFAULT 'USDT',
  `network` varchar(50) DEFAULT 'TRC20',
  `status` enum('pending','processing','completed','failed','cancelled') DEFAULT 'pending',
  `admin_processed_by` int(10) UNSIGNED DEFAULT NULL,
  `processed_at` timestamp NULL DEFAULT NULL,
  `rejection_reason` text DEFAULT NULL,
  `withdrawal_hash` varchar(255) DEFAULT NULL COMMENT 'Blockchain transaction hash',
  `network_fee` decimal(15,8) DEFAULT 0.00000000 COMMENT 'Actual network fee paid',
  `processing_notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `withdrawals`
--

INSERT INTO `withdrawals` (`id`, `transaction_id`, `user_id`, `requested_amount`, `fee_amount`, `wallet_address`, `currency`, `network`, `status`, `admin_processed_by`, `processed_at`, `rejection_reason`, `withdrawal_hash`, `network_fee`, `processing_notes`, `created_at`, `updated_at`) VALUES
(1, 7, 2, 20000.00000000, 1000.00000000, 'This1smyWall3tAdress33Str1nG', 'USDT', 'TRC20', 'completed', NULL, NULL, NULL, NULL, 0.00000000, NULL, '2025-06-01 16:44:13', '2025-06-01 23:08:49'),
(2, 14, 2, 5000.00000000, 250.00000000, 'This1smyWall3tAdress33Str1nG', 'BTC', 'TRC20', 'failed', NULL, NULL, NULL, NULL, 0.00000000, NULL, '2025-06-01 23:21:06', '2025-06-01 23:08:49'),
(3, 15, 2, 5000.00000000, 250.00000000, 'This1smyWall3tAdress33Str1nG', 'BTC', 'TRC20', 'completed', NULL, NULL, NULL, NULL, 0.00000000, NULL, '2025-06-01 23:42:12', '2025-06-01 23:08:49'),
(4, 16, 2, 6214.00000000, 310.70000000, 'This1smyWall3tAdress33Str1nG', 'BTC', 'TRC20', 'completed', NULL, NULL, NULL, NULL, 0.00000000, NULL, '2025-06-02 00:03:03', '2025-06-02 00:03:26'),
(8, 37, 5, 20.00000000, 1.00000000, 'This1smyWall3tAdress33Str1nG', 'BTC', 'TRC20', 'completed', 1, '2025-06-03 14:34:14', NULL, NULL, 0.00000000, 'Auto-approved by system', '2025-06-03 12:58:21', '2025-06-03 13:34:14'),
(9, 38, 5, 20.00000000, 1.00000000, 'This1smyWall3tAdress33Str1nG', 'BTC', 'TRC20', 'completed', 1, '2025-06-03 14:34:14', NULL, NULL, 0.00000000, 'Auto-approved by system', '2025-06-03 13:33:29', '2025-06-03 13:34:14');

-- --------------------------------------------------------

--
-- Table structure for table `withdrawal_methods`
--

CREATE TABLE `withdrawal_methods` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `gateway_id` int(10) UNSIGNED DEFAULT NULL,
  `name` varchar(255) NOT NULL,
  `type` enum('auto','manual') NOT NULL DEFAULT 'manual',
  `currency` varchar(255) NOT NULL DEFAULT 'USD',
  `currency_symbol` varchar(255) NOT NULL DEFAULT '$',
  `charge` double NOT NULL DEFAULT 0,
  `charge_type` enum('percentage','fixed') NOT NULL DEFAULT 'percentage',
  `minimum_withdrawal` double NOT NULL DEFAULT 10,
  `maximum_withdrawal` double NOT NULL DEFAULT 999999,
  `processing_time` varchar(100) DEFAULT '1-24 hours',
  `instructions` text DEFAULT NULL,
  `status` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `admins`
--
ALTER TABLE `admins`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD UNIQUE KEY `email` (`email`),
  ADD KEY `idx_email` (`email`),
  ADD KEY `idx_role` (`role`);

--
-- Indexes for table `admin_sessions`
--
ALTER TABLE `admin_sessions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `admin_sessions_admin_id_index` (`admin_id`),
  ADD KEY `admin_sessions_last_activity_index` (`last_activity`);

--
-- Indexes for table `admin_settings`
--
ALTER TABLE `admin_settings`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `setting_key` (`setting_key`);

--
-- Indexes for table `admin_users`
--
ALTER TABLE `admin_users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD UNIQUE KEY `email` (`email`),
  ADD KEY `idx_email` (`email`),
  ADD KEY `idx_role` (`role`);

--
-- Indexes for table `deposits`
--
ALTER TABLE `deposits`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `transaction_id` (`transaction_id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `deposit_method_id` (`deposit_method_id`),
  ADD KEY `status` (`status`),
  ADD KEY `verification_status` (`verification_status`),
  ADD KEY `admin_processed_by` (`admin_processed_by`);

--
-- Indexes for table `deposit_methods`
--
ALTER TABLE `deposit_methods`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_type` (`type`),
  ADD KEY `idx_status` (`status`);

--
-- Indexes for table `email_logs`
--
ALTER TABLE `email_logs`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `investments`
--
ALTER TABLE `investments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `schema_id` (`schema_id`);

--
-- Indexes for table `investment_schemas`
--
ALTER TABLE `investment_schemas`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `payment_gateways`
--
ALTER TABLE `payment_gateways`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `code` (`code`);

--
-- Indexes for table `profits`
--
ALTER TABLE `profits`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `transaction_id` (`transaction_id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `investment_id` (`investment_id`),
  ADD KEY `schema_id` (`schema_id`),
  ADD KEY `calculation_date` (`calculation_date`),
  ADD KEY `profit_type` (`profit_type`),
  ADD KEY `status` (`status`),
  ADD KEY `profits_admin_fk` (`admin_processed_by`);

--
-- Indexes for table `referrals`
--
ALTER TABLE `referrals`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_referral` (`referrer_id`,`referred_id`),
  ADD KEY `idx_referrer_id` (`referrer_id`),
  ADD KEY `idx_referred_id` (`referred_id`);

--
-- Indexes for table `security_logs`
--
ALTER TABLE `security_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_event_type` (`event_type`),
  ADD KEY `idx_ip_address` (`ip_address`),
  ADD KEY `idx_created_at` (`created_at`);

--
-- Indexes for table `site_settings`
--
ALTER TABLE `site_settings`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `setting_key` (`setting_key`);

--
-- Indexes for table `transactions`
--
ALTER TABLE `transactions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_user_id` (`user_id`),
  ADD KEY `idx_type` (`type`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_created_at` (`created_at`),
  ADD KEY `transactions_user_processed_fk` (`processed_by`),
  ADD KEY `transactions_admin_processed_fk` (`admin_processed_by`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`),
  ADD UNIQUE KEY `username` (`username`),
  ADD UNIQUE KEY `referral_code` (`referral_code`),
  ADD KEY `idx_email` (`email`),
  ADD KEY `idx_referral_code` (`referral_code`),
  ADD KEY `idx_referred_by` (`referred_by`),
  ADD KEY `idx_users_email_active` (`email`,`is_active`);

--
-- Indexes for table `user_2fa`
--
ALTER TABLE `user_2fa`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `user_id` (`user_id`);

--
-- Indexes for table `user_documents`
--
ALTER TABLE `user_documents`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_user_status` (`user_id`,`status`);

--
-- Indexes for table `user_sessions`
--
ALTER TABLE `user_sessions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `session_token` (`session_token`),
  ADD KEY `idx_token` (`session_token`),
  ADD KEY `idx_user_active` (`user_id`,`is_active`),
  ADD KEY `idx_expires` (`expires_at`);

--
-- Indexes for table `user_wallets`
--
ALTER TABLE `user_wallets`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_user_currency_address` (`user_id`,`currency`,`address`),
  ADD KEY `idx_user_currency` (`user_id`,`currency`);

--
-- Indexes for table `withdrawals`
--
ALTER TABLE `withdrawals`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `transaction_id` (`transaction_id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `status` (`status`),
  ADD KEY `admin_processed_by` (`admin_processed_by`);

--
-- Indexes for table `withdrawal_methods`
--
ALTER TABLE `withdrawal_methods`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_type` (`type`),
  ADD KEY `idx_status` (`status`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `admins`
--
ALTER TABLE `admins`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `admin_settings`
--
ALTER TABLE `admin_settings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=45;

--
-- AUTO_INCREMENT for table `admin_users`
--
ALTER TABLE `admin_users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `deposits`
--
ALTER TABLE `deposits`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `deposit_methods`
--
ALTER TABLE `deposit_methods`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `email_logs`
--
ALTER TABLE `email_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `investments`
--
ALTER TABLE `investments`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT for table `investment_schemas`
--
ALTER TABLE `investment_schemas`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `payment_gateways`
--
ALTER TABLE `payment_gateways`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `profits`
--
ALTER TABLE `profits`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=33;

--
-- AUTO_INCREMENT for table `referrals`
--
ALTER TABLE `referrals`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `security_logs`
--
ALTER TABLE `security_logs`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=22;

--
-- AUTO_INCREMENT for table `site_settings`
--
ALTER TABLE `site_settings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=22;

--
-- AUTO_INCREMENT for table `transactions`
--
ALTER TABLE `transactions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=81;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `user_2fa`
--
ALTER TABLE `user_2fa`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `user_documents`
--
ALTER TABLE `user_documents`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `user_sessions`
--
ALTER TABLE `user_sessions`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `user_wallets`
--
ALTER TABLE `user_wallets`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `withdrawals`
--
ALTER TABLE `withdrawals`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `withdrawal_methods`
--
ALTER TABLE `withdrawal_methods`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `admin_sessions`
--
ALTER TABLE `admin_sessions`
  ADD CONSTRAINT `admin_sessions_ibfk_1` FOREIGN KEY (`admin_id`) REFERENCES `admins` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `deposits`
--
ALTER TABLE `deposits`
  ADD CONSTRAINT `deposits_admin_fk` FOREIGN KEY (`admin_processed_by`) REFERENCES `admins` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `deposits_method_fk` FOREIGN KEY (`deposit_method_id`) REFERENCES `deposit_methods` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `deposits_transaction_fk` FOREIGN KEY (`transaction_id`) REFERENCES `transactions` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `deposits_user_fk` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `investments`
--
ALTER TABLE `investments`
  ADD CONSTRAINT `investments_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `investments_ibfk_2` FOREIGN KEY (`schema_id`) REFERENCES `investment_schemas` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `profits`
--
ALTER TABLE `profits`
  ADD CONSTRAINT `profits_admin_fk` FOREIGN KEY (`admin_processed_by`) REFERENCES `admins` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `profits_investment_fk` FOREIGN KEY (`investment_id`) REFERENCES `investments` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `profits_schema_fk` FOREIGN KEY (`schema_id`) REFERENCES `investment_schemas` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `profits_transaction_fk` FOREIGN KEY (`transaction_id`) REFERENCES `transactions` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `profits_user_fk` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `referrals`
--
ALTER TABLE `referrals`
  ADD CONSTRAINT `referrals_ibfk_1` FOREIGN KEY (`referrer_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `referrals_ibfk_2` FOREIGN KEY (`referred_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `transactions`
--
ALTER TABLE `transactions`
  ADD CONSTRAINT `transactions_admin_processed_fk` FOREIGN KEY (`admin_processed_by`) REFERENCES `admins` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `transactions_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `transactions_user_processed_fk` FOREIGN KEY (`processed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `users`
--
ALTER TABLE `users`
  ADD CONSTRAINT `users_ibfk_1` FOREIGN KEY (`referred_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `user_2fa`
--
ALTER TABLE `user_2fa`
  ADD CONSTRAINT `user_2fa_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `user_documents`
--
ALTER TABLE `user_documents`
  ADD CONSTRAINT `user_documents_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `user_sessions`
--
ALTER TABLE `user_sessions`
  ADD CONSTRAINT `user_sessions_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `user_wallets`
--
ALTER TABLE `user_wallets`
  ADD CONSTRAINT `user_wallets_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `withdrawals`
--
ALTER TABLE `withdrawals`
  ADD CONSTRAINT `withdrawals_admin_fk` FOREIGN KEY (`admin_processed_by`) REFERENCES `admins` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `withdrawals_transaction_fk` FOREIGN KEY (`transaction_id`) REFERENCES `transactions` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `withdrawals_user_fk` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
