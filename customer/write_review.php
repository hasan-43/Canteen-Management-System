<?php
session_start();
require_once __DIR__ . '/../config/db_connection.php';

// Check if user is logged in
if (!isset($_SESSION['user_id']) || $_SESSION['usertype'] !== 'customer') {
    header("Location: ../login.php");
    exit();
}

$customerId = (int)$_SESSION['user_id'];
$action = $_GET['action'] ?? 'list';
$orderId = isset($_GET['order_id']) ? (int)$_GET['order_id'] : null;

// Handle review submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_review'])) {
    $orderId = (int)$_POST['order_id'];
    $overall_rating = (int)$_POST['overall_rating'];
    $food_rating = isset($_POST['food_rating']) ? (int)$_POST['food_rating'] : null;
    $delivery_rating = isset($_POST['delivery_rating']) ? (int)$_POST['delivery_rating'] : null;
    $review_text = trim($_POST['review_text'] ?? '');
    
    // Submit order review
    $stmt = $conn->prepare("
        INSERT INTO order_reviews (order_id, customer_id, overall_rating, food_rating, delivery_rating, review_text)
        VALUES (?, ?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE 
            overall_rating = VALUES(overall_rating),
            food_rating = VALUES(food_rating),
            delivery_rating = VALUES(delivery_rating),
            review_text = VALUES(review_text)
    ");
    $stmt->bind_param("iiiiss", $orderId, $customerId, $overall_rating, $food_rating, $delivery_rating, $review_text);
    $stmt->execute();
    $stmt->close();
    
    // Submit product reviews if provided
    if (isset($_POST['product_ratings'])) {
        foreach ($_POST['product_ratings'] as $product_code => $rating) {
            if ($rating > 0) {
                $product_review = trim($_POST['product_reviews'][$product_code] ?? '');
                
                // Get kitchen for this product from order
                $stmt = $conn->prepare("
                    SELECT o.kitchen FROM order_items oi
                    JOIN orders o ON oi.order_id = o.order_id
                    WHERE oi.order_id = ? AND oi.product_code = ?
                    LIMIT 1
                ");
                $stmt->bind_param("is", $orderId, $product_code);
                $stmt->execute();
                $result = $stmt->get_result();
                if ($row = $result->fetch_assoc()) {
                    $kitchen = $row['kitchen'];
                    
                    // Insert product review
                    $stmt2 = $conn->prepare("
                        INSERT INTO product_reviews (customer_id, product_code, kitchen, order_id, rating, review_text, is_verified_purchase)
                        VALUES (?, ?, ?, ?, ?, ?, 1)
                        ON DUPLICATE KEY UPDATE 
                            rating = VALUES(rating),
                            review_text = VALUES(review_text)
                    ");
                    $stmt2->bind_param("isssis", $customerId, $product_code, $kitchen, $orderId, $rating, $product_review);
                    $stmt2->execute();
                    $stmt2->close();
                }
                $stmt->close();
            }
        }
    }
    
    $_SESSION['flash_message'] = 'Thank you for your review!';
    $_SESSION['flash_type'] = 'success';
    header("Location: invoice.php");
    exit();
}

// Fetch order details if reviewing specific order
$orderDetails = null;
$orderItems = [];
$existingReview = null;

if ($action === 'review' && $orderId) {
    $stmt = $conn->prepare("
        SELECT * FROM orders 
        WHERE order_id = ? AND customer_id = ? AND order_status = 'delivered'
    ");
    $stmt->bind_param("ii", $orderId, $customerId);
    $stmt->execute();
    $result = $stmt->get_result();
    $orderDetails = $result->fetch_assoc();
    $stmt->close();
    
    if ($orderDetails) {
        // Get order items
        $stmt = $conn->prepare("SELECT * FROM order_items WHERE order_id = ?");
        $stmt->bind_param("i", $orderId);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $orderItems[] = $row;
        }
        $stmt->close();
        
        // Check for existing review
        $stmt = $conn->prepare("SELECT * FROM order_reviews WHERE order_id = ?");
        $stmt->bind_param("i", $orderId);
        $stmt->execute();
        $result = $stmt->get_result();
        $existingReview = $result->fetch_assoc();
        $stmt->close();
    }
}

// Note: initials() function is defined in navbar.php

$displayName = $_SESSION['fullname'] ?? $_SESSION['username'] ?? 'Customer';
$profilePic = $_SESSION['profile_pic'] ?? null;

// Check if this is an AJAX request
$isAjax = isset($_GET['ajax']) && $_GET['ajax'] == '1';

if ($isAjax && $action === 'review' && $orderDetails) {
    // Return only the form content for AJAX requests
    ?>
    <form method="POST" action="">
        <input type="hidden" name="order_id" value="<?= $orderId ?>">
        
        <!-- Overall Rating -->
        <div class="mb-8">
            <label class="block text-lg font-semibold text-gray-900 mb-3">Overall Experience</label>
            <div class="star-rating" id="overall-stars">
                <?php for ($i = 1; $i <= 5; $i++): ?>
                    <span class="star-btn" data-rating="<?= $i ?>" data-field="overall_rating">★</span>
                <?php endfor; ?>
            </div>
            <input type="hidden" name="overall_rating" id="overall_rating" required>
        </div>
        
        <!-- Food Rating -->
        <div class="mb-8">
            <label class="block text-lg font-semibold text-gray-900 mb-3">Food Quality</label>
            <div class="star-rating" id="food-stars">
                <?php for ($i = 1; $i <= 5; $i++): ?>
                    <span class="star-btn" data-rating="<?= $i ?>" data-field="food_rating">★</span>
                <?php endfor; ?>
            </div>
            <input type="hidden" name="food_rating" id="food_rating">
        </div>
        
        <!-- Delivery Rating -->
        <div class="mb-8">
            <label class="block text-lg font-semibold text-gray-900 mb-3">Delivery Service</label>
            <div class="star-rating" id="delivery-stars">
                <?php for ($i = 1; $i <= 5; $i++): ?>
                    <span class="star-btn" data-rating="<?= $i ?>" data-field="delivery_rating">★</span>
                <?php endfor; ?>
            </div>
            <input type="hidden" name="delivery_rating" id="delivery_rating">
        </div>
        
        <!-- Review Text -->
        <div class="mb-8">
            <label class="block text-lg font-semibold text-gray-900 mb-3">Your Review</label>
            <textarea name="review_text" rows="4" class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-red-500 focus:border-transparent" placeholder="Share your experience with this order..."><?= $existingReview ? htmlspecialchars($existingReview['review_text']) : '' ?></textarea>
        </div>
        
        <!-- Product Reviews -->
        <div class="mb-8">
            <h3 class="text-xl font-bold text-gray-900 mb-4">Rate Individual Items</h3>
            <?php foreach ($orderItems as $item): ?>
                <div class="border-t border-gray-200 pt-4 pb-4">
                    <h4 class="font-semibold text-gray-900 mb-2"><?= htmlspecialchars($item['product_name']) ?></h4>
                    <div class="star-rating mb-2" data-product="<?= htmlspecialchars($item['product_code']) ?>">
                        <?php for ($i = 1; $i <= 5; $i++): ?>
                            <span class="star-btn" data-rating="<?= $i ?>" data-field="product_ratings_<?= htmlspecialchars($item['product_code']) ?>">★</span>
                        <?php endfor; ?>
                    </div>
                    <input type="hidden" name="product_ratings[<?= htmlspecialchars($item['product_code']) ?>]" id="product_ratings_<?= htmlspecialchars($item['product_code']) ?>" value="0">
                    <textarea name="product_reviews[<?= htmlspecialchars($item['product_code']) ?>]" rows="2" class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg mt-2" placeholder="Comments about this item..."></textarea>
                </div>
            <?php endforeach; ?>
        </div>
        
        <div class="flex gap-4">
            <button type="submit" name="submit_review" class="flex-1 bg-red-600 hover:bg-red-700 text-white font-semibold py-3 px-6 rounded-lg transition">
                Submit Review
            </button>
            <button type="button" onclick="closeReviewModal()" class="flex-1 bg-gray-200 hover:bg-gray-300 text-gray-800 font-semibold py-3 px-6 rounded-lg transition">
                Cancel
            </button>
        </div>
    </form>
    <?php
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Write Review - Campus Cravings</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        body { font-family: 'Inter', sans-serif; background: #f5f7fa; }
        .star-rating { display: flex; gap: 0.5rem; }
        .star-btn { font-size: 2rem; color: #d1d5db; cursor: pointer; transition: all 0.2s; }
        .star-btn:hover, .star-btn.active { color: #fbbf24; transform: scale(1.1); }
        .review-card { background: white; border-radius: 1rem; padding: 1.5rem; box-shadow: 0 1px 3px rgba(0,0,0,0.1); }
    </style>
</head>
<body>
    <?php include __DIR__ . '/navbar.php'; ?>
    
    <div class="max-w-4xl mx-auto px-4 py-8 mt-20">
        <?php if ($action === 'review' && $orderDetails): ?>
            <div class="review-card mb-6">
                <h1 class="text-3xl font-bold text-gray-900 mb-4">Write a Review</h1>
                <p class="text-gray-600 mb-6">Order #<?= htmlspecialchars($orderDetails['order_number']) ?> from <?= ucfirst($orderDetails['kitchen']) ?> Kitchen</p>
                
                <form method="POST" action="">
                    <input type="hidden" name="order_id" value="<?= $orderId ?>">
                    
                    <!-- Overall Rating -->
                    <div class="mb-8">
                        <label class="block text-lg font-semibold text-gray-900 mb-3">Overall Experience</label>
                        <div class="star-rating" id="overall-stars">
                            <?php for ($i = 1; $i <= 5; $i++): ?>
                                <span class="star-btn" data-rating="<?= $i ?>" data-field="overall_rating">★</span>
                            <?php endfor; ?>
                        </div>
                        <input type="hidden" name="overall_rating" id="overall_rating" required>
                    </div>
                    
                    <!-- Food Rating -->
                    <div class="mb-8">
                        <label class="block text-lg font-semibold text-gray-900 mb-3">Food Quality</label>
                        <div class="star-rating" id="food-stars">
                            <?php for ($i = 1; $i <= 5; $i++): ?>
                                <span class="star-btn" data-rating="<?= $i ?>" data-field="food_rating">★</span>
                            <?php endfor; ?>
                        </div>
                        <input type="hidden" name="food_rating" id="food_rating">
                    </div>
                    
                    <!-- Delivery Rating -->
                    <div class="mb-8">
                        <label class="block text-lg font-semibold text-gray-900 mb-3">Delivery Service</label>
                        <div class="star-rating" id="delivery-stars">
                            <?php for ($i = 1; $i <= 5; $i++): ?>
                                <span class="star-btn" data-rating="<?= $i ?>" data-field="delivery_rating">★</span>
                            <?php endfor; ?>
                        </div>
                        <input type="hidden" name="delivery_rating" id="delivery_rating">
                    </div>
                    
                    <!-- Review Text -->
                    <div class="mb-8">
                        <label class="block text-lg font-semibold text-gray-900 mb-3">Your Review</label>
                        <textarea name="review_text" rows="4" class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-red-500 focus:border-transparent" placeholder="Share your experience with this order..."><?= $existingReview ? htmlspecialchars($existingReview['review_text']) : '' ?></textarea>
                    </div>
                    
                    <!-- Product Reviews -->
                    <div class="mb-8">
                        <h3 class="text-xl font-bold text-gray-900 mb-4">Rate Individual Items</h3>
                        <?php foreach ($orderItems as $item): ?>
                            <div class="border-t border-gray-200 pt-4 pb-4">
                                <h4 class="font-semibold text-gray-900 mb-2"><?= htmlspecialchars($item['product_name']) ?></h4>
                                <div class="star-rating mb-2" data-product="<?= htmlspecialchars($item['product_code']) ?>">
                                    <?php for ($i = 1; $i <= 5; $i++): ?>
                                        <span class="star-btn" data-rating="<?= $i ?>" data-field="product_ratings[<?= htmlspecialchars($item['product_code']) ?>]">★</span>
                                    <?php endfor; ?>
                                </div>
                                <input type="hidden" name="product_ratings[<?= htmlspecialchars($item['product_code']) ?>]" id="product_ratings_<?= htmlspecialchars($item['product_code']) ?>" value="0">
                                <textarea name="product_reviews[<?= htmlspecialchars($item['product_code']) ?>]" rows="2" class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg mt-2" placeholder="Comments about this item..."></textarea>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    
                    <div class="flex gap-4">
                        <button type="submit" name="submit_review" class="flex-1 bg-red-600 hover:bg-red-700 text-white font-semibold py-3 px-6 rounded-lg transition">
                            Submit Review
                        </button>
                        <a href="invoice.php" class="flex-1 text-center bg-gray-200 hover:bg-gray-300 text-gray-800 font-semibold py-3 px-6 rounded-lg transition">
                            Cancel
                        </a>
                    </div>
                </form>
            </div>
        <?php else: ?>
            <div class="review-card">
                <p class="text-gray-600">Order not found or not eligible for review.</p>
                <a href="invoice.php" class="text-red-600 hover:underline">Back to Orders</a>
            </div>
        <?php endif; ?>
    </div>
    
    <script>
        // Star rating handler
        document.querySelectorAll('.star-rating').forEach(container => {
            const stars = container.querySelectorAll('.star-btn');
            
            stars.forEach(star => {
                star.addEventListener('click', function() {
                    const rating = parseInt(this.dataset.rating);
                    const field = this.dataset.field;
                    
                    // Update hidden input
                    const input = document.getElementById(field) || document.getElementById(field.replace(/\[|\]/g, '_').replace(/__/g, '_'));
                    if (input) {
                        input.value = rating;
                    }
                    
                    // Update visual stars
                    stars.forEach((s, index) => {
                        if (index < rating) {
                            s.classList.add('active');
                        } else {
                            s.classList.remove('active');
                        }
                    });
                });
            });
        });
    </script>
</body>
</html>
