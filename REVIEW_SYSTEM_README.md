# Rating & Review System - Installation Guide

## Overview
This document explains the Rating & Review System implementation for the Campus Cravings Canteen Management System.

## Features Implemented

### 1. Database Tables
- **product_reviews**: Stores customer reviews for individual food items
- **order_reviews**: Stores overall reviews for complete orders

### 2. Customer Features
- View average ratings and review counts on product pages
- Write reviews for delivered orders
- Rate individual products within an order
- Rate food quality and delivery service separately
- View all reviews for any product
- Verified purchase badges for reviews

### 3. Admin Features
- View review statistics in dashboard
- See average rating, total reviews, and 5-star percentage
- View recent customer reviews
- Monitor customer feedback

## Installation Steps

### Step 1: Update Database
1. Open phpMyAdmin or your MySQL client
2. Select your `food_wave` database
3. Execute the updated SQL file:
   ```
   Database/food_wave (2).sql
   ```
   OR run these SQL commands directly:

```sql
-- Create product_reviews table
CREATE TABLE `product_reviews` (
  `review_id` int(11) NOT NULL AUTO_INCREMENT,
  `customer_id` int(11) NOT NULL,
  `product_code` varchar(50) NOT NULL,
  `kitchen` enum('khans','olympia','neptune') NOT NULL,
  `order_id` int(11) DEFAULT NULL,
  `rating` tinyint(4) NOT NULL CHECK (`rating` >= 1 AND `rating` <= 5),
  `review_text` text DEFAULT NULL,
  `is_verified_purchase` tinyint(1) NOT NULL DEFAULT 0,
  `helpful_count` int(11) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`review_id`),
  KEY `idx_product` (`product_code`, `kitchen`),
  KEY `idx_customer` (`customer_id`),
  KEY `idx_rating` (`rating`),
  KEY `fk_review_order` (`order_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Create order_reviews table
CREATE TABLE `order_reviews` (
  `review_id` int(11) NOT NULL AUTO_INCREMENT,
  `order_id` int(11) NOT NULL,
  `customer_id` int(11) NOT NULL,
  `overall_rating` tinyint(4) NOT NULL CHECK (`overall_rating` >= 1 AND `overall_rating` <= 5),
  `food_rating` tinyint(4) DEFAULT NULL CHECK (`food_rating` >= 1 AND `food_rating` <= 5),
  `delivery_rating` tinyint(4) DEFAULT NULL CHECK (`delivery_rating` >= 1 AND `delivery_rating` <= 5),
  `review_text` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`review_id`),
  UNIQUE KEY `unique_order_review` (`order_id`),
  KEY `idx_customer` (`customer_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Add foreign key constraints
ALTER TABLE `product_reviews`
  ADD CONSTRAINT `fk_product_review_customer` FOREIGN KEY (`customer_id`) REFERENCES `customer` (`customer_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_product_review_order` FOREIGN KEY (`order_id`) REFERENCES `orders` (`order_id`) ON DELETE SET NULL;

ALTER TABLE `order_reviews`
  ADD CONSTRAINT `fk_order_review_order` FOREIGN KEY (`order_id`) REFERENCES `orders` (`order_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_order_review_customer` FOREIGN KEY (`customer_id`) REFERENCES `customer` (`customer_id`) ON DELETE CASCADE;
```

### Step 2: Verify File Structure
Ensure these files are in place:

**New Files:**
- `api/reviews.php` - Handles review API requests
- `customer/write_review.php` - Review submission page
- `customer/product_reviews.php` - Display all reviews for a product

**Modified Files:**
- `customer/khans.php` - Shows ratings on products
- `customer/neptune.php` - Shows ratings on products
- `customer/olympia.php` - Shows ratings on products
- `customer/invoice.php` - Added "Write Review" button for delivered orders
- `admin/khans/statistics.php` - Shows review statistics
- `Database/food_wave (2).sql` - Updated database schema

### Step 3: Test the Features

1. **As Customer:**
   - Place an order and wait for it to be marked as "delivered"
   - Go to Invoice page
   - Click "Write a Review" button on a delivered order
   - Submit your review with ratings
   - View products and see the average ratings displayed
   - Click "View reviews" to see all product reviews

2. **As Admin:**
   - Go to Statistics page
   - View review metrics (Average Rating, Total Reviews, 5-Star %)
   - See recent customer reviews section

## API Endpoints

The `api/reviews.php` file provides these endpoints:

### Get Product Rating
```
GET api/reviews.php?action=get_product_rating&product_code=XXX&kitchen=khans
```

### Get Product Reviews
```
GET api/reviews.php?action=get_product_reviews&product_code=XXX&kitchen=khans
```

### Submit Product Review
```
POST api/reviews.php?action=submit_product_review
Parameters: customer_id, product_code, kitchen, rating, review_text, order_id
```

### Submit Order Review
```
POST api/reviews.php?action=submit_order_review
Parameters: order_id, customer_id, overall_rating, food_rating, delivery_rating, review_text
```

### Check Order Review Status
```
GET api/reviews.php?action=get_order_review_status&order_id=XXX
```

## Database Schema Details

### product_reviews Table
| Column | Type | Description |
|--------|------|-------------|
| review_id | INT | Primary key |
| customer_id | INT | Foreign key to customer |
| product_code | VARCHAR(50) | Product identifier |
| kitchen | ENUM | Kitchen name (khans/olympia/neptune) |
| order_id | INT | Related order (nullable) |
| rating | TINYINT | 1-5 star rating |
| review_text | TEXT | Review content |
| is_verified_purchase | BOOLEAN | If customer bought it |
| helpful_count | INT | Future feature placeholder |
| created_at | TIMESTAMP | When review was created |
| updated_at | TIMESTAMP | Last update time |

### order_reviews Table
| Column | Type | Description |
|--------|------|-------------|
| review_id | INT | Primary key |
| order_id | INT | Foreign key to orders |
| customer_id | INT | Foreign key to customer |
| overall_rating | TINYINT | 1-5 overall rating |
| food_rating | TINYINT | 1-5 food quality rating |
| delivery_rating | TINYINT | 1-5 delivery rating |
| review_text | TEXT | Review content |
| created_at | TIMESTAMP | When review was created |

## Features You Can Add Later

1. **Review Moderation:** Admin can approve/reject reviews
2. **Helpful Votes:** Customers can mark reviews as helpful
3. **Photo Upload:** Allow customers to upload food photos
4. **Response System:** Allow kitchen admins to respond to reviews
5. **Filter Reviews:** By rating, date, verified purchase
6. **Review Reminders:** Email customers to review their orders
7. **Incentives:** Reward points for writing reviews
8. **Analytics:** Detailed review trends and insights

## Troubleshooting

### Reviews not showing?
- Check if database tables were created successfully
- Verify foreign key constraints are in place
- Ensure orders are marked as "delivered" before reviewing

### Ratings not displaying on products?
- Clear browser cache
- Check if product_reviews table has data
- Verify kitchen names match exactly (lowercase)

### Can't submit review?
- Make sure you're logged in as a customer
- Order must be in "delivered" status
- Check browser console for JavaScript errors

## Support

For issues or questions about the Rating & Review System:
1. Check the database connection in `config/db_connection.php`
2. Enable error reporting in PHP to see detailed errors
3. Check browser console for JavaScript errors
4. Verify all files are uploaded to correct locations

---

**System Version:** 1.0  
**Last Updated:** January 26, 2026  
**Author:** GitHub Copilot
