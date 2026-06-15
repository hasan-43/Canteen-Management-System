-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Jun 12, 2026 at 06:05 PM
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
-- Database: `food_wave`
--

-- --------------------------------------------------------

--
-- Table structure for table `cart_items`
--

CREATE TABLE `cart_items` (
  `item_id` int(11) NOT NULL,
  `cart_id` int(11) NOT NULL,
  `product_code` varchar(50) NOT NULL,
  `product_name` varchar(255) NOT NULL,
  `kitchen` enum('khans','olympia','neptune') NOT NULL,
  `price` decimal(10,2) NOT NULL,
  `quantity` int(11) NOT NULL DEFAULT 1,
  `added_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `cart_items`
--

INSERT INTO `cart_items` (`item_id`, `cart_id`, `product_code`, `product_name`, `kitchen`, `price`, `quantity`, `added_at`) VALUES
(44, 2, 'biriyani', 'Biriyani', 'khans', 120.00, 2, '2026-01-26 20:10:48'),
(58, 1, 'fried_rice', 'Fried Rice', 'olympia', 45.00, 2, '2026-01-27 07:23:30'),
(59, 1, 'khichuri', 'Khichuri', 'olympia', 45.00, 1, '2026-01-27 07:23:31'),
(60, 1, 'burger', 'Burger', 'khans', 60.00, 1, '2026-02-17 09:53:14'),
(61, 1, 'chowmein', 'Chowmein', 'khans', 40.00, 1, '2026-02-17 09:53:16');

-- --------------------------------------------------------

--
-- Table structure for table `chat_messages`
--

CREATE TABLE `chat_messages` (
  `id` int(11) NOT NULL,
  `customer_id` int(11) DEFAULT NULL,
  `kitchen` varchar(50) DEFAULT NULL,
  `rider_id` int(11) DEFAULT NULL,
  `sender` enum('customer','kitchen','rider') NOT NULL,
  `message` text NOT NULL,
  `is_read` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `chat_messages`
--

INSERT INTO `chat_messages` (`id`, `customer_id`, `kitchen`, `rider_id`, `sender`, `message`, `is_read`, `created_at`) VALUES
(1, 1, 'khans', NULL, 'customer', 'hi', 0, '2026-01-27 03:59:40');

-- --------------------------------------------------------

--
-- Table structure for table `customer`
--

CREATE TABLE `customer` (
  `customer_id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `fullname` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `status` enum('active','inactive') DEFAULT 'active',
  `profile_pic` varchar(255) DEFAULT NULL,
  `dob` date DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `customer`
--

INSERT INTO `customer` (`customer_id`, `username`, `fullname`, `email`, `password`, `phone`, `address`, `created_at`, `updated_at`, `status`, `profile_pic`, `dob`) VALUES
(1, 'Hasan', 'Hasan Mahmud', 'hasan@gmail.com', '123456', '01234567891', 'vatara', '2025-12-09 06:48:16', '2026-01-26 15:48:17', 'active', '../resources/ProfilePics/download-1-20260126164817-0934f032.jpg', '2026-01-21'),
(2, 'Toufiq', 'Toufiq Hasan', 'toufiq@gmail.com', '123456', NULL, NULL, '2026-01-26 17:07:00', '2026-01-26 17:07:00', 'active', NULL, NULL),
(3, 'Ridan', 'Ridan Ullah', 'ridan@gmail.com', '123456', '01234567891', 'vatara', '2026-01-26 17:07:32', '2026-01-26 17:09:09', 'active', '../resources/ProfilePics/UIU-Logo-250-20260126180909-53c94409.png', '2026-01-08');

-- --------------------------------------------------------

--
-- Table structure for table `customer_cart`
--

CREATE TABLE `customer_cart` (
  `cart_id` int(11) NOT NULL,
  `customer_id` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `customer_cart`
--

INSERT INTO `customer_cart` (`cart_id`, `customer_id`, `created_at`, `updated_at`) VALUES
(1, 1, '2025-12-14 15:19:15', '2025-12-14 15:19:15'),
(2, 3, '2026-01-26 17:09:43', '2026-01-26 17:09:43');

-- --------------------------------------------------------

--
-- Table structure for table `delivery`
--

CREATE TABLE `delivery` (
  `delivery_id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `fullname` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `vehicle_type` varchar(50) DEFAULT NULL,
  `license_number` varchar(50) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `status` enum('active','inactive','busy') DEFAULT 'active',
  `profile_pic` varchar(255) DEFAULT NULL,
  `dob` date DEFAULT NULL,
  `address` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `delivery`
--

INSERT INTO `delivery` (`delivery_id`, `username`, `fullname`, `email`, `password`, `phone`, `vehicle_type`, `license_number`, `created_at`, `updated_at`, `status`, `profile_pic`, `dob`, `address`) VALUES
(1, 'Shifat', 'Shifat Mahmud', 'shifat@gmail.com', '123456', NULL, NULL, NULL, '2025-12-09 06:50:45', '2025-12-09 06:50:45', 'active', NULL, NULL, NULL),
(2, 'Ripple', 'Rafiuzzaman', 'rafi@gmail.com', '$2y$10$pFTyM.16lTtIKBywXZTSc.CwRC3GyKHJKIvzSrV4RW9SOZwdeAT82', '1236547890', NULL, NULL, '2026-01-26 16:51:55', '2026-01-27 06:39:05', 'active', '../resources/ProfilePics/download-1-20260126193725-98d6fb7c.jpg', '2026-01-15', 'vatara'),
(3, 'sagor', 'sagor hossain', 'sagor@gmail.com', '123456', NULL, NULL, NULL, '2026-01-26 16:57:21', '2026-01-26 16:57:21', 'active', NULL, NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `khans`
--

CREATE TABLE `khans` (
  `khans_id` int(11) NOT NULL,
  `product_code` varchar(50) NOT NULL,
  `name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `price` decimal(10,2) NOT NULL,
  `category` enum('mains','drinks','sides') NOT NULL,
  `image` varchar(255) DEFAULT NULL,
  `stock` int(11) NOT NULL DEFAULT 0,
  `is_available` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `khans`
--

INSERT INTO `khans` (`khans_id`, `product_code`, `name`, `description`, `price`, `category`, `image`, `stock`, `is_available`, `created_at`, `updated_at`) VALUES
(1, 'rice', 'Rice', NULL, 20.00, 'mains', 'Rice.jpg', 30, 1, '2025-12-11 06:00:00', '2025-12-11 06:00:00'),
(2, 'porota', 'Porota', NULL, 8.00, 'mains', 'Porota.jpg', 40, 1, '2025-12-11 06:00:00', '2025-12-11 06:00:00'),
(3, 'dal', 'Dal', NULL, 20.00, 'mains', 'Dal.jpg', 29, 1, '2025-12-11 06:00:00', '2026-01-26 17:09:49'),
(4, 'biriyani', 'Biriyani', NULL, 120.00, 'mains', 'Biriyani.jpg', 17, 1, '2025-12-11 06:00:00', '2026-01-26 20:10:48'),
(5, 'fried_rice', 'Fried Rice', NULL, 40.00, 'mains', 'Fried Rice.jpg', 34, 1, '2025-12-11 06:00:00', '2026-01-26 17:09:51'),
(6, 'khichuri', 'Khichuri', NULL, 40.00, 'mains', 'khichuri.jpg', 30, 1, '2025-12-11 06:00:00', '2025-12-11 06:00:00'),
(7, 'chowmein', 'Chowmein', NULL, 40.00, 'mains', 'Chow mein.jpg', 26, 1, '2025-12-11 06:00:00', '2026-02-17 09:53:16'),
(8, 'burger', 'Burger', NULL, 60.00, 'mains', 'Burger.jpg', 19, 1, '2025-12-11 06:00:00', '2026-02-17 09:53:14'),
(9, 'shawarma', 'Shawarma', NULL, 55.00, 'mains', 'Shawarma.jpg', 25, 1, '2025-12-11 06:00:00', '2025-12-11 06:00:00'),
(10, 'roll', 'Roll', NULL, 35.00, 'mains', 'Roll.jpg', 30, 1, '2025-12-11 06:00:00', '2025-12-11 06:00:00'),
(11, 'sandwich', 'Sandwich', NULL, 45.00, 'mains', 'Sandwich.jpg', 25, 1, '2025-12-11 06:00:00', '2025-12-11 06:00:00'),
(12, 'chicken_onion', 'Chicken Onion', NULL, 60.00, 'sides', 'Chicken Oniion.jpg', 20, 1, '2025-12-11 06:00:00', '2025-12-11 06:00:00'),
(13, 'chicken_chili', 'Chicken Chili Onion', NULL, 60.00, 'sides', 'Chicken Cili onion.jpg', 20, 1, '2025-12-11 06:00:00', '2025-12-11 06:00:00'),
(14, 'chicken_fry', 'Chicken Fry', NULL, 65.00, 'sides', 'Chicken Fry.jpg', 20, 1, '2025-12-11 06:00:00', '2025-12-11 06:00:00'),
(15, 'egg_chop', 'Egg Chop', NULL, 30.00, 'sides', 'Egg Chop.jpg', 25, 1, '2025-12-11 06:00:00', '2025-12-11 06:00:00'),
(16, 'pudding', 'Pudding', NULL, 35.00, 'sides', 'Pudding.jpg', 20, 1, '2025-12-11 06:00:00', '2025-12-11 06:00:00'),
(17, 'vegetables', 'Mixed Vegetables', NULL, 20.00, 'sides', 'Mixed Vegitables.jpg', 25, 1, '2025-12-11 06:00:00', '2025-12-11 06:00:00'),
(18, 'singara', 'Singara', NULL, 8.00, 'sides', 'Singara.jpg', 40, 1, '2025-12-11 06:00:00', '2025-12-11 06:00:00'),
(19, 'color_tea', 'Color Tea', NULL, 8.00, 'drinks', 'Color Tea.jpg', 50, 1, '2025-12-11 06:00:00', '2025-12-11 06:00:00'),
(20, 'milk_tea', 'Milk Tea', NULL, 10.00, 'drinks', 'Milk Tea.jpg', 50, 1, '2025-12-11 06:00:00', '2025-12-11 06:00:00'),
(21, 'coffee', 'Coffee', NULL, 20.00, 'drinks', 'Coffee.jpg', 40, 1, '2025-12-11 06:00:00', '2025-12-11 06:00:00'),
(22, 'soft_drinks', 'Soft Drinks', NULL, 20.00, 'drinks', 'Soft Drinks.jpg', 40, 1, '2025-12-11 06:00:00', '2025-12-11 06:00:00'),
(23, 'water', 'Water', NULL, 20.00, 'drinks', 'Water.jpg', 60, 1, '2025-12-11 06:00:00', '2025-12-11 06:00:00');

-- --------------------------------------------------------

--
-- Table structure for table `neptune`
--

CREATE TABLE `neptune` (
  `neptune_id` int(11) NOT NULL,
  `product_code` varchar(50) NOT NULL,
  `name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `price` decimal(10,2) NOT NULL,
  `category` enum('mains','drinks','sides') NOT NULL,
  `image` varchar(255) DEFAULT NULL,
  `stock` int(11) NOT NULL DEFAULT 0,
  `is_available` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `neptune`
--

INSERT INTO `neptune` (`neptune_id`, `product_code`, `name`, `description`, `price`, `category`, `image`, `stock`, `is_available`, `created_at`, `updated_at`) VALUES
(1, 'rice', 'Rice', NULL, 22.00, 'mains', 'Rice.jpg', 32, 1, '2025-12-11 06:00:00', '2026-01-27 07:22:31'),
(3, 'khichuri', 'Khichuri', NULL, 42.00, 'mains', 'Khichuri.jpg', 32, 1, '2025-12-11 06:00:00', '2026-01-27 07:22:30'),
(4, 'ruti', 'Ruti', NULL, 10.00, 'mains', 'Ruti.jpg', 39, 1, '2025-12-11 06:00:00', '2026-01-27 06:42:50'),
(5, 'puri', 'Puri', NULL, 10.00, 'sides', 'Puri.jpg', 50, 1, '2025-12-11 06:00:00', '2025-12-11 06:00:00'),
(6, 'jilapi', 'Jilapi', NULL, 10.00, 'sides', 'Jilapi.jpg', 50, 1, '2025-12-11 06:00:00', '2025-12-11 06:00:00'),
(7, 'biscuits', 'Biscuits', NULL, 20.00, 'sides', 'Biscuits.jpg', 40, 1, '2025-12-11 06:00:00', '2025-12-11 06:00:00'),
(8, 'chips', 'Chips', NULL, 20.00, 'sides', 'Chips.jpg', 35, 1, '2025-12-11 06:00:00', '2025-12-11 06:00:00'),
(9, 'pudding', 'Pudding', NULL, 38.00, 'sides', 'Pudding.jpg', 25, 1, '2025-12-11 06:00:00', '2025-12-11 06:00:00'),
(10, 'vegetables', 'Mixed Vegetables', NULL, 22.00, 'sides', 'Mixed Vegitables.jpg', 30, 1, '2025-12-11 06:00:00', '2025-12-11 06:00:00'),
(11, 'singara', 'Singara', NULL, 9.00, 'sides', 'Singara.jpg', 45, 1, '2025-12-11 06:00:00', '2025-12-11 06:00:00'),
(12, 'cake', 'Cake', NULL, 35.00, 'sides', 'Cake.jpg', 18, 1, '2025-12-11 06:00:00', '2026-01-27 06:07:09'),
(13, 'juice', 'Juice', NULL, 20.00, 'drinks', 'Juice.jpg', 32, 1, '2025-12-11 06:00:00', '2026-01-27 07:22:32'),
(14, 'milk_tea', 'Milk Tea', NULL, 11.00, 'drinks', 'Milk Tea.jpg', 49, 1, '2025-12-11 06:00:00', '2026-01-27 07:23:03'),
(15, 'coffee', 'Coffee', NULL, 22.00, 'drinks', 'Coffee.jpg', 42, 1, '2025-12-11 06:00:00', '2026-01-27 06:42:51'),
(16, 'soft_drinks', 'Soft Drinks', NULL, 22.00, 'drinks', 'Soft Drinks.jpg', 42, 1, '2025-12-11 06:00:00', '2026-01-27 07:23:01'),
(17, 'water', 'Water', NULL, 18.00, 'drinks', 'Water.jpg', 58, 1, '2025-12-11 06:00:00', '2026-01-27 07:23:03'),
(19, 'DAL_1769494562', 'Dal', NULL, 20.00, 'mains', 'product_69785822ed8bf.jpg', 50, 1, '2026-01-27 06:16:02', '2026-01-27 06:16:11');

-- --------------------------------------------------------

--
-- Table structure for table `olympia`
--

CREATE TABLE `olympia` (
  `olympia_id` int(11) NOT NULL,
  `product_code` varchar(50) NOT NULL,
  `name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `price` decimal(10,2) NOT NULL,
  `category` enum('mains','drinks','sides') NOT NULL,
  `image` varchar(255) DEFAULT NULL,
  `stock` int(11) NOT NULL DEFAULT 0,
  `is_available` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `olympia`
--

INSERT INTO `olympia` (`olympia_id`, `product_code`, `name`, `description`, `price`, `category`, `image`, `stock`, `is_available`, `created_at`, `updated_at`) VALUES
(1, 'rice', 'Rice', NULL, 25.00, 'mains', 'Rice.jpg', 35, 1, '2025-12-11 06:00:00', '2025-12-11 06:00:00'),
(2, 'porota', 'Porota', NULL, 10.00, 'mains', 'Porota.jpg', 45, 1, '2025-12-11 06:00:00', '2025-12-11 06:00:00'),
(3, 'dal', 'Dal', NULL, 25.00, 'mains', 'Dal.jpg', 35, 1, '2025-12-11 06:00:00', '2025-12-11 06:00:00'),
(4, 'biriyani', 'Biriyani', NULL, 130.00, 'mains', 'Biriyani.jpg', 30, 1, '2025-12-11 06:00:00', '2025-12-11 06:00:00'),
(5, 'fried_rice', 'Fried Rice', NULL, 45.00, 'mains', 'Fried Rice.jpg', 38, 1, '2025-12-11 06:00:00', '2026-01-27 07:23:30'),
(6, 'khichuri', 'Khichuri', NULL, 45.00, 'mains', 'Khichuri.jpg', 34, 1, '2025-12-11 06:00:00', '2026-01-27 07:23:32'),
(7, 'chowmein', 'Chowmein', NULL, 45.00, 'mains', 'Chow mein.jpg', 34, 1, '2025-12-11 06:00:00', '2026-01-26 17:09:58'),
(8, 'burger', 'Burger', NULL, 70.00, 'mains', 'Burger.jpg', 29, 1, '2025-12-11 06:00:00', '2026-01-26 15:42:08'),
(9, 'shawarma', 'Shawarma', NULL, 65.00, 'mains', 'Shawarma.jpg', 30, 1, '2025-12-11 06:00:00', '2025-12-11 06:00:00'),
(10, 'roll', 'Roll', NULL, 40.00, 'mains', 'Roll.jpg', 35, 1, '2025-12-11 06:00:00', '2025-12-11 06:00:00'),
(11, 'sandwich', 'Sandwich', NULL, 50.00, 'mains', 'Sandwich.jpg', 30, 1, '2025-12-11 06:00:00', '2025-12-11 06:00:00'),
(12, 'chicken_onion', 'Chicken Onion', NULL, 70.00, 'sides', 'Chicken Oniion.jpg', 25, 1, '2025-12-11 06:00:00', '2025-12-11 06:00:00'),
(13, 'chicken_chili', 'Chicken Chili Onion', NULL, 70.00, 'sides', 'Chicken Cili onion.jpg', 25, 1, '2025-12-11 06:00:00', '2025-12-11 06:00:00'),
(14, 'chicken_fry', 'Chicken Fry', NULL, 75.00, 'sides', 'Chicken Fry.jpg', 25, 1, '2025-12-11 06:00:00', '2025-12-11 06:00:00'),
(15, 'egg_chop', 'Egg Chop', NULL, 35.00, 'sides', 'Egg Chop.jpg', 30, 1, '2025-12-11 06:00:00', '2025-12-11 06:00:00'),
(16, 'pudding', 'Pudding', NULL, 40.00, 'sides', 'Pudding.jpg', 25, 1, '2025-12-11 06:00:00', '2025-12-11 06:00:00'),
(17, 'vegetables', 'Mixed Vegetables', NULL, 25.00, 'sides', 'Mixed Vegitables.jpg', 30, 1, '2025-12-11 06:00:00', '2025-12-11 06:00:00'),
(18, 'singara', 'Singara', NULL, 10.00, 'sides', 'Singara.jpg', 45, 1, '2025-12-11 06:00:00', '2025-12-11 06:00:00'),
(19, 'color_tea', 'Color Tea', NULL, 10.00, 'drinks', 'Color Tea.jpg', 50, 1, '2025-12-11 06:00:00', '2025-12-11 06:00:00'),
(20, 'milk_tea', 'Milk Tea', NULL, 12.00, 'drinks', 'Milk Tea.jpg', 50, 1, '2025-12-11 06:00:00', '2025-12-11 06:00:00'),
(21, 'coffee', 'Coffee', NULL, 25.00, 'drinks', 'Coffee.jpg', 45, 1, '2025-12-11 06:00:00', '2025-12-11 06:00:00'),
(22, 'soft_drinks', 'Soft Drinks', NULL, 25.00, 'drinks', 'Soft Drinks.jpg', 45, 1, '2025-12-11 06:00:00', '2025-12-11 06:00:00');

-- --------------------------------------------------------

--
-- Table structure for table `orders`
--

CREATE TABLE `orders` (
  `order_id` int(11) NOT NULL,
  `order_number` varchar(50) NOT NULL,
  `customer_id` int(11) NOT NULL,
  `delivery_man_id` int(11) DEFAULT NULL,
  `kitchen` enum('khans','olympia','neptune') NOT NULL,
  `delivery_address` text NOT NULL,
  `payment_method` enum('cash_on_delivery','mobile_banking','card') NOT NULL DEFAULT 'cash_on_delivery',
  `special_instructions` text DEFAULT NULL,
  `subtotal` decimal(10,2) NOT NULL,
  `delivery_fee` decimal(10,2) NOT NULL DEFAULT 20.00,
  `total_amount` decimal(10,2) NOT NULL,
  `order_status` enum('pending','confirmed','preparing','out_for_delivery','delivered','cancelled') NOT NULL DEFAULT 'pending',
  `order_date` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `orders`
--

INSERT INTO `orders` (`order_id`, `order_number`, `customer_id`, `delivery_man_id`, `kitchen`, `delivery_address`, `payment_method`, `special_instructions`, `subtotal`, `delivery_fee`, `total_amount`, `order_status`, `order_date`, `updated_at`) VALUES
(1, 'ORD-20260126-KHA-4928', 1, NULL, 'khans', 'vatara', 'mobile_banking', '', 304.00, 0.00, 304.00, 'delivered', '2026-01-26 13:24:50', '2026-01-26 13:34:27'),
(2, 'ORD-20260126-KHA-4053', 1, NULL, 'khans', 'vatara', 'mobile_banking', '', 540.00, 0.00, 540.00, 'delivered', '2026-01-26 15:42:44', '2026-01-26 15:52:45'),
(3, 'ORD-20260126-OLY-8776', 1, NULL, 'olympia', 'vatara', 'card', '', 405.00, 0.00, 405.00, 'delivered', '2026-01-26 15:43:10', '2026-01-26 20:22:03'),
(4, 'ORD-20260126-NEP-6361', 1, NULL, 'neptune', 'vatara', 'card', 'road 2', 162.00, 0.00, 162.00, 'delivered', '2026-01-26 15:47:00', '2026-01-26 15:52:36'),
(5, 'ORD-20260126-KHA-3823', 3, NULL, 'khans', 'vatara', 'mobile_banking', '', 340.00, 0.00, 340.00, 'delivered', '2026-01-26 17:11:55', '2026-01-26 18:53:10'),
(6, 'ORD-20260126-KHA-6586', 3, NULL, 'khans', 'vatara', 'card', '', 220.00, 0.00, 220.00, 'pending', '2026-01-26 20:09:09', '2026-01-26 20:09:09'),
(7, 'ORD-20260126-NEP-8902', 3, NULL, 'neptune', 'vatara', 'mobile_banking', '', 64.00, 0.00, 64.00, 'confirmed', '2026-01-26 20:09:19', '2026-01-27 07:24:32'),
(8, 'ORD-20260126-OLY-1443', 3, NULL, 'olympia', 'vatara', 'mobile_banking', '', 45.00, 0.00, 45.00, '', '2026-01-26 20:10:36', '2026-01-27 06:12:55'),
(9, 'ORD-20260127-KHA-9206', 1, NULL, 'khans', 'vatara', 'card', '', 100.00, 0.00, 100.00, 'pending', '2026-01-27 05:36:36', '2026-01-27 05:36:36'),
(10, 'ORD-20260127-NEP-7536', 1, NULL, 'neptune', 'vatara', 'card', '', 42.00, 0.00, 42.00, 'delivered', '2026-01-27 05:41:00', '2026-01-27 06:18:59'),
(11, 'ORD-20260127-NEP-6445', 1, NULL, 'neptune', 'vatara', 'mobile_banking', '', 57.00, 0.00, 57.00, 'delivered', '2026-01-27 06:07:20', '2026-01-27 06:11:03'),
(12, 'ORD-20260127-NEP-9729', 1, NULL, 'neptune', 'vatara', 'card', '', 74.00, 0.00, 74.00, 'confirmed', '2026-01-27 06:43:02', '2026-01-27 06:59:01'),
(13, 'ORD-20260127-NEP-1430', 1, NULL, 'neptune', 'vatara', 'mobile_banking', '', 84.00, 0.00, 84.00, 'confirmed', '2026-01-27 07:22:40', '2026-01-27 07:24:44'),
(14, 'ORD-20260127-NEP-6505', 1, NULL, 'neptune', 'vatara', 'card', '', 51.00, 0.00, 51.00, 'pending', '2026-01-27 07:23:43', '2026-01-27 07:23:43');

-- --------------------------------------------------------

--
-- Table structure for table `order_items`
--

CREATE TABLE `order_items` (
  `order_item_id` int(11) NOT NULL,
  `order_id` int(11) NOT NULL,
  `product_code` varchar(50) NOT NULL,
  `product_name` varchar(255) NOT NULL,
  `price` decimal(10,2) NOT NULL,
  `quantity` int(11) NOT NULL,
  `subtotal` decimal(10,2) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `order_items`
--

INSERT INTO `order_items` (`order_item_id`, `order_id`, `product_code`, `product_name`, `price`, `quantity`, `subtotal`) VALUES
(1, 1, 'biriyani', 'Biriyani', 120.00, 1, 120.00),
(2, 1, 'burger', 'Burger', 60.00, 1, 60.00),
(3, 1, 'chowmein', 'Chowmein', 40.00, 2, 80.00),
(4, 1, 'dal', 'Dal', 20.00, 1, 20.00),
(5, 1, 'porota', 'Porota', 8.00, 3, 24.00),
(6, 2, 'biriyani', 'Biriyani', 120.00, 4, 480.00),
(7, 2, 'burger', 'Burger', 60.00, 1, 60.00),
(8, 3, 'biriyani', 'Biriyani', 130.00, 1, 130.00),
(9, 3, 'burger', 'Burger', 70.00, 2, 140.00),
(10, 3, 'fried_rice', 'Fried Rice', 45.00, 2, 90.00),
(11, 3, 'khichuri', 'Khichuri', 45.00, 1, 45.00),
(12, 4, 'khichuri', 'Khichuri', 42.00, 1, 42.00),
(13, 4, 'milk_tea', 'Milk Tea', 11.00, 1, 11.00),
(14, 4, 'pudding', 'Pudding', 38.00, 1, 38.00),
(15, 4, 'rice', 'Rice', 22.00, 1, 22.00),
(16, 4, 'singara', 'Singara', 9.00, 1, 9.00),
(17, 4, 'soft_drinks', 'Soft Drinks', 22.00, 1, 22.00),
(18, 4, 'water', 'Water', 18.00, 1, 18.00),
(19, 5, 'biriyani', 'Biriyani', 120.00, 1, 120.00),
(20, 5, 'burger', 'Burger', 60.00, 2, 120.00),
(21, 5, 'chowmein', 'Chowmein', 40.00, 1, 40.00),
(22, 5, 'dal', 'Dal', 20.00, 1, 20.00),
(23, 5, 'fried_rice', 'Fried Rice', 40.00, 1, 40.00),
(24, 6, 'biriyani', 'Biriyani', 120.00, 1, 120.00),
(25, 6, 'burger', 'Burger', 60.00, 1, 60.00),
(26, 6, 'chowmein', 'Chowmein', 40.00, 1, 40.00),
(27, 7, 'khichuri', 'Khichuri', 42.00, 1, 42.00),
(28, 7, 'rice', 'Rice', 22.00, 1, 22.00),
(29, 8, 'chowmein', 'Chowmein', 45.00, 1, 45.00),
(30, 9, 'burger', 'Burger', 60.00, 1, 60.00),
(31, 9, 'chowmein', 'Chowmein', 40.00, 1, 40.00),
(32, 10, 'coffee', 'Coffee', 22.00, 1, 22.00),
(33, 10, 'juice', 'Juice', 20.00, 1, 20.00),
(34, 11, 'cake', 'Cake', 35.00, 1, 35.00),
(35, 11, 'soft_drinks', 'Soft Drinks', 22.00, 1, 22.00),
(36, 12, 'coffee', 'Coffee', 22.00, 2, 44.00),
(37, 12, 'juice', 'Juice', 20.00, 1, 20.00),
(38, 12, 'ruti', 'Ruti', 10.00, 1, 10.00),
(39, 13, 'juice', 'Juice', 20.00, 1, 20.00),
(40, 13, 'khichuri', 'Khichuri', 42.00, 1, 42.00),
(41, 13, 'rice', 'Rice', 22.00, 1, 22.00),
(42, 14, 'milk_tea', 'Milk Tea', 11.00, 1, 11.00),
(43, 14, 'soft_drinks', 'Soft Drinks', 22.00, 1, 22.00),
(44, 14, 'water', 'Water', 18.00, 1, 18.00);

-- --------------------------------------------------------

--
-- Table structure for table `order_reviews`
--

CREATE TABLE `order_reviews` (
  `review_id` int(11) NOT NULL,
  `order_id` int(11) NOT NULL,
  `customer_id` int(11) NOT NULL,
  `overall_rating` tinyint(4) NOT NULL CHECK (`overall_rating` >= 1 and `overall_rating` <= 5),
  `food_rating` tinyint(4) DEFAULT NULL CHECK (`food_rating` >= 1 and `food_rating` <= 5),
  `delivery_rating` tinyint(4) DEFAULT NULL CHECK (`delivery_rating` >= 1 and `delivery_rating` <= 5),
  `review_text` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `order_reviews`
--

INSERT INTO `order_reviews` (`review_id`, `order_id`, `customer_id`, `overall_rating`, `food_rating`, `delivery_rating`, `review_text`, `created_at`) VALUES
(1, 1, 1, 5, 5, 5, 'Good', '2026-01-26 13:46:52'),
(2, 4, 1, 5, 5, 5, 'good', '2026-01-26 17:16:06');

-- --------------------------------------------------------

--
-- Table structure for table `product_reviews`
--

CREATE TABLE `product_reviews` (
  `review_id` int(11) NOT NULL,
  `customer_id` int(11) NOT NULL,
  `product_code` varchar(50) NOT NULL,
  `kitchen` enum('khans','olympia','neptune') NOT NULL,
  `order_id` int(11) DEFAULT NULL,
  `rating` tinyint(4) NOT NULL CHECK (`rating` >= 1 and `rating` <= 5),
  `review_text` text DEFAULT NULL,
  `is_verified_purchase` tinyint(1) NOT NULL DEFAULT 0,
  `helpful_count` int(11) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `product_reviews`
--

INSERT INTO `product_reviews` (`review_id`, `customer_id`, `product_code`, `kitchen`, `order_id`, `rating`, `review_text`, `is_verified_purchase`, `helpful_count`, `created_at`, `updated_at`) VALUES
(1, 1, 'khichuri', 'neptune', 4, 5, 'good', 1, 0, '2026-01-26 17:16:07', '2026-01-26 17:16:07'),
(2, 1, 'milk_tea', 'neptune', 4, 5, 'good', 1, 0, '2026-01-26 17:16:07', '2026-01-26 17:16:07'),
(3, 1, 'pudding', 'neptune', 4, 5, 'good', 1, 0, '2026-01-26 17:16:07', '2026-01-26 17:16:07'),
(4, 1, 'rice', 'neptune', 4, 5, '', 1, 0, '2026-01-26 17:16:07', '2026-01-26 17:16:07'),
(5, 1, 'singara', 'neptune', 4, 5, '', 1, 0, '2026-01-26 17:16:07', '2026-01-26 17:16:07'),
(6, 1, 'soft_drinks', 'neptune', 4, 5, '', 1, 0, '2026-01-26 17:16:07', '2026-01-26 17:16:07'),
(7, 1, 'water', 'neptune', 4, 5, '', 1, 0, '2026-01-26 17:16:07', '2026-01-26 17:16:07');

-- --------------------------------------------------------

--
-- Table structure for table `shop`
--

CREATE TABLE `shop` (
  `shop_id` int(11) NOT NULL,
  `shop_name` varchar(255) NOT NULL,
  `password` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `shop`
--

INSERT INTO `shop` (`shop_id`, `shop_name`, `password`) VALUES
(1, 'khans', '123456'),
(2, 'olympia', '123456'),
(3, 'neptune', '123456');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `cart_items`
--
ALTER TABLE `cart_items`
  ADD PRIMARY KEY (`item_id`),
  ADD KEY `cart_id` (`cart_id`);

--
-- Indexes for table `chat_messages`
--
ALTER TABLE `chat_messages`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_customer_id` (`customer_id`),
  ADD KEY `idx_kitchen` (`kitchen`),
  ADD KEY `idx_rider_id` (`rider_id`);

--
-- Indexes for table `customer`
--
ALTER TABLE `customer`
  ADD PRIMARY KEY (`customer_id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD UNIQUE KEY `email` (`email`);

--
-- Indexes for table `customer_cart`
--
ALTER TABLE `customer_cart`
  ADD PRIMARY KEY (`cart_id`),
  ADD UNIQUE KEY `customer_id` (`customer_id`);

--
-- Indexes for table `delivery`
--
ALTER TABLE `delivery`
  ADD PRIMARY KEY (`delivery_id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD UNIQUE KEY `email` (`email`);

--
-- Indexes for table `khans`
--
ALTER TABLE `khans`
  ADD PRIMARY KEY (`khans_id`),
  ADD UNIQUE KEY `product_code` (`product_code`);

--
-- Indexes for table `neptune`
--
ALTER TABLE `neptune`
  ADD PRIMARY KEY (`neptune_id`),
  ADD UNIQUE KEY `product_code` (`product_code`);

--
-- Indexes for table `olympia`
--
ALTER TABLE `olympia`
  ADD PRIMARY KEY (`olympia_id`),
  ADD UNIQUE KEY `product_code` (`product_code`);

--
-- Indexes for table `orders`
--
ALTER TABLE `orders`
  ADD PRIMARY KEY (`order_id`),
  ADD UNIQUE KEY `order_number` (`order_number`),
  ADD KEY `customer_id` (`customer_id`),
  ADD KEY `kitchen` (`kitchen`),
  ADD KEY `order_status` (`order_status`);

--
-- Indexes for table `order_items`
--
ALTER TABLE `order_items`
  ADD PRIMARY KEY (`order_item_id`),
  ADD KEY `order_id` (`order_id`);

--
-- Indexes for table `order_reviews`
--
ALTER TABLE `order_reviews`
  ADD PRIMARY KEY (`review_id`),
  ADD UNIQUE KEY `unique_order_review` (`order_id`),
  ADD KEY `idx_customer` (`customer_id`);

--
-- Indexes for table `product_reviews`
--
ALTER TABLE `product_reviews`
  ADD PRIMARY KEY (`review_id`),
  ADD KEY `idx_product` (`product_code`,`kitchen`),
  ADD KEY `idx_customer` (`customer_id`),
  ADD KEY `idx_rating` (`rating`),
  ADD KEY `fk_review_order` (`order_id`);

--
-- Indexes for table `shop`
--
ALTER TABLE `shop`
  ADD PRIMARY KEY (`shop_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `cart_items`
--
ALTER TABLE `cart_items`
  MODIFY `item_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=62;

--
-- AUTO_INCREMENT for table `chat_messages`
--
ALTER TABLE `chat_messages`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `customer`
--
ALTER TABLE `customer`
  MODIFY `customer_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `customer_cart`
--
ALTER TABLE `customer_cart`
  MODIFY `cart_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `delivery`
--
ALTER TABLE `delivery`
  MODIFY `delivery_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `khans`
--
ALTER TABLE `khans`
  MODIFY `khans_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=24;

--
-- AUTO_INCREMENT for table `neptune`
--
ALTER TABLE `neptune`
  MODIFY `neptune_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=20;

--
-- AUTO_INCREMENT for table `olympia`
--
ALTER TABLE `olympia`
  MODIFY `olympia_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=23;

--
-- AUTO_INCREMENT for table `orders`
--
ALTER TABLE `orders`
  MODIFY `order_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=15;

--
-- AUTO_INCREMENT for table `order_items`
--
ALTER TABLE `order_items`
  MODIFY `order_item_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=45;

--
-- AUTO_INCREMENT for table `order_reviews`
--
ALTER TABLE `order_reviews`
  MODIFY `review_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `product_reviews`
--
ALTER TABLE `product_reviews`
  MODIFY `review_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `shop`
--
ALTER TABLE `shop`
  MODIFY `shop_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `cart_items`
--
ALTER TABLE `cart_items`
  ADD CONSTRAINT `fk_cartitem_cart` FOREIGN KEY (`cart_id`) REFERENCES `customer_cart` (`cart_id`) ON DELETE CASCADE;

--
-- Constraints for table `customer_cart`
--
ALTER TABLE `customer_cart`
  ADD CONSTRAINT `fk_cart_customer` FOREIGN KEY (`customer_id`) REFERENCES `customer` (`customer_id`) ON DELETE CASCADE;

--
-- Constraints for table `orders`
--
ALTER TABLE `orders`
  ADD CONSTRAINT `fk_order_customer` FOREIGN KEY (`customer_id`) REFERENCES `customer` (`customer_id`) ON DELETE CASCADE;

--
-- Constraints for table `order_items`
--
ALTER TABLE `order_items`
  ADD CONSTRAINT `fk_orderitem_order` FOREIGN KEY (`order_id`) REFERENCES `orders` (`order_id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
