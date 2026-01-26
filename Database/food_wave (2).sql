-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Dec 14, 2025 at 08:21 PM
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
(10, 1, 'dal', 'Dal', 'khans', 20.00, 1, '2025-12-14 19:18:35'),
(11, 1, 'burger', 'Burger', 'olympia', 70.00, 1, '2025-12-14 19:18:46'),
(12, 1, 'biriyani', 'Biriyani', 'olympia', 130.00, 1, '2025-12-14 19:18:47'),
(13, 1, 'pudding', 'Pudding', 'neptune', 38.00, 1, '2025-12-14 19:18:56'),
(14, 1, 'singara', 'Singara', 'neptune', 9.00, 1, '2025-12-14 19:18:59'),
(15, 1, 'milk_tea', 'Milk Tea', 'neptune', 11.00, 1, '2025-12-14 19:19:01'),
(16, 1, 'porota', 'Porota', 'khans', 8.00, 3, '2025-12-14 19:19:44');

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
  `status` enum('active','inactive') DEFAULT 'active'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `customer`
--

INSERT INTO `customer` (`customer_id`, `username`, `fullname`, `email`, `password`, `phone`, `address`, `created_at`, `updated_at`, `status`) VALUES
(1, 'Hasan', 'Hasan Mahmud', 'hasan@gmail.com', '123456', NULL, NULL, '2025-12-09 06:48:16', '2025-12-09 06:48:16', 'active');

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
(1, 1, '2025-12-14 15:19:15', '2025-12-14 15:19:15');

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
  `status` enum('active','inactive','busy') DEFAULT 'active'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `delivery`
--

INSERT INTO `delivery` (`delivery_id`, `username`, `fullname`, `email`, `password`, `phone`, `vehicle_type`, `license_number`, `created_at`, `updated_at`, `status`) VALUES
(1, 'Shifat', 'Shifat Mahmud', 'shifat@gmail.com', '123456', NULL, NULL, NULL, '2025-12-09 06:50:45', '2025-12-09 06:50:45', 'active');

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
(3, 'dal', 'Dal', NULL, 20.00, 'mains', 'Dal.jpg', 30, 1, '2025-12-11 06:00:00', '2025-12-11 06:00:00'),
(4, 'biriyani', 'Biriyani', NULL, 120.00, 'mains', 'Biriyani.jpg', 25, 1, '2025-12-11 06:00:00', '2025-12-11 06:00:00'),
(5, 'fried_rice', 'Fried Rice', NULL, 40.00, 'mains', 'Fried Rice.jpg', 35, 1, '2025-12-11 06:00:00', '2025-12-11 06:00:00'),
(6, 'khichuri', 'Khichuri', NULL, 40.00, 'mains', 'khichuri.jpg', 30, 1, '2025-12-11 06:00:00', '2025-12-11 06:00:00'),
(7, 'chowmein', 'Chowmein', NULL, 40.00, 'mains', 'Chow mein.jpg', 30, 1, '2025-12-11 06:00:00', '2025-12-11 06:00:00'),
(8, 'burger', 'Burger', NULL, 60.00, 'mains', 'Burger.jpg', 25, 1, '2025-12-11 06:00:00', '2025-12-11 06:00:00'),
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
(1, 'rice', 'Rice', NULL, 22.00, 'mains', 'Rice.jpg', 35, 1, '2025-12-11 06:00:00', '2025-12-11 06:00:00'),
(2, 'dal', 'Dal', NULL, 22.00, 'mains', 'Dal.jpg', 35, 1, '2025-12-11 06:00:00', '2025-12-11 06:00:00'),
(3, 'khichuri', 'Khichuri', NULL, 42.00, 'mains', 'Khichuri.jpg', 35, 1, '2025-12-11 06:00:00', '2025-12-11 06:00:00'),
(4, 'ruti', 'Ruti', NULL, 10.00, 'mains', 'Ruti.jpg', 40, 1, '2025-12-11 06:00:00', '2025-12-11 06:00:00'),
(5, 'puri', 'Puri', NULL, 10.00, 'sides', 'Puri.jpg', 50, 1, '2025-12-11 06:00:00', '2025-12-11 06:00:00'),
(6, 'jilapi', 'Jilapi', NULL, 10.00, 'sides', 'Jilapi.jpg', 50, 1, '2025-12-11 06:00:00', '2025-12-11 06:00:00'),
(7, 'biscuits', 'Biscuits', NULL, 20.00, 'sides', 'Biscuits.jpg', 40, 1, '2025-12-11 06:00:00', '2025-12-11 06:00:00'),
(8, 'chips', 'Chips', NULL, 20.00, 'sides', 'Chips.jpg', 35, 1, '2025-12-11 06:00:00', '2025-12-11 06:00:00'),
(9, 'pudding', 'Pudding', NULL, 38.00, 'sides', 'Pudding.jpg', 25, 1, '2025-12-11 06:00:00', '2025-12-11 06:00:00'),
(10, 'vegetables', 'Mixed Vegetables', NULL, 22.00, 'sides', 'Mixed Vegitables.jpg', 30, 1, '2025-12-11 06:00:00', '2025-12-11 06:00:00'),
(11, 'singara', 'Singara', NULL, 9.00, 'sides', 'Singara.jpg', 45, 1, '2025-12-11 06:00:00', '2025-12-11 06:00:00'),
(12, 'cake', 'Cake', NULL, 35.00, 'sides', 'Cake.jpg', 20, 1, '2025-12-11 06:00:00', '2025-12-11 06:00:00'),
(13, 'juice', 'Juice', NULL, 20.00, 'drinks', 'Juice.jpg', 35, 1, '2025-12-11 06:00:00', '2025-12-11 06:00:00'),
(14, 'milk_tea', 'Milk Tea', NULL, 11.00, 'drinks', 'Milk Tea.jpg', 50, 1, '2025-12-11 06:00:00', '2025-12-11 06:00:00'),
(15, 'coffee', 'Coffee', NULL, 22.00, 'drinks', 'Coffee.jpg', 45, 1, '2025-12-11 06:00:00', '2025-12-11 06:00:00'),
(16, 'soft_drinks', 'Soft Drinks', NULL, 22.00, 'drinks', 'Soft Drinks.jpg', 45, 1, '2025-12-11 06:00:00', '2025-12-11 06:00:00'),
(17, 'water', 'Water', NULL, 18.00, 'drinks', 'Water.jpg', 60, 1, '2025-12-11 06:00:00', '2025-12-11 06:00:00');

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
(5, 'fried_rice', 'Fried Rice', NULL, 45.00, 'mains', 'Fried Rice.jpg', 40, 1, '2025-12-11 06:00:00', '2025-12-11 06:00:00'),
(6, 'khichuri', 'Khichuri', NULL, 45.00, 'mains', 'Khichuri.jpg', 35, 1, '2025-12-11 06:00:00', '2025-12-11 06:00:00'),
(7, 'chowmein', 'Chowmein', NULL, 45.00, 'mains', 'Chow mein.jpg', 35, 1, '2025-12-11 06:00:00', '2025-12-11 06:00:00'),
(8, 'burger', 'Burger', NULL, 70.00, 'mains', 'Burger.jpg', 30, 1, '2025-12-11 06:00:00', '2025-12-11 06:00:00'),
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

CREATE TABLE chat_messages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    customer_id INT NOT NULL,
    kitchen VARCHAR(50) NOT NULL,
    sender ENUM('customer', 'kitchen') NOT NULL,
    message TEXT NOT NULL,
    is_read TINYINT(1) NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    INDEX idx_customer_id (customer_id),
    INDEX idx_kitchen (kitchen),

    CONSTRAINT fk_chat_customer
        FOREIGN KEY (customer_id)
        REFERENCES customer(customer_id)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


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
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `cart_items`
--
ALTER TABLE `cart_items`
  MODIFY `item_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=17;

--
-- AUTO_INCREMENT for table `customer`
--
ALTER TABLE `customer`
  MODIFY `customer_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `customer_cart`
--
ALTER TABLE `customer_cart`
  MODIFY `cart_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `delivery`
--
ALTER TABLE `delivery`
  MODIFY `delivery_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `khans`
--
ALTER TABLE `khans`
  MODIFY `khans_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=24;

--
-- AUTO_INCREMENT for table `neptune`
--
ALTER TABLE `neptune`
  MODIFY `neptune_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=18;

--
-- AUTO_INCREMENT for table `olympia`
--
ALTER TABLE `olympia`
  MODIFY `olympia_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=23;

--
-- AUTO_INCREMENT for table `orders`
--
ALTER TABLE `orders`
  MODIFY `order_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `order_items`
--
ALTER TABLE `order_items`
  MODIFY `order_item_id` int(11) NOT NULL AUTO_INCREMENT;

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
