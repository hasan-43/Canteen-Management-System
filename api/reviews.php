<?php
session_start();
require_once __DIR__ . '/../config/db_connection.php';

header('Content-Type: application/json');

$action = $_GET['action'] ?? $_POST['action'] ?? '';

// Simple endpoint: Get reviews for a product (no action parameter needed)
if (!$action && isset($_GET['product_code']) && isset($_GET['kitchen'])) {
    $product_code = $_GET['product_code'];
    $kitchen = $_GET['kitchen'];
    
    // Get statistics
    $statsStmt = $conn->prepare("
        SELECT 
            COUNT(*) as total_reviews,
            COALESCE(AVG(rating), 0) as avg_rating
        FROM product_reviews 
        WHERE product_code = ? AND kitchen = ?
    ");
    $statsStmt->bind_param("ss", $product_code, $kitchen);
    $statsStmt->execute();
    $stats = $statsStmt->get_result()->fetch_assoc();
    $statsStmt->close();
    
    // Get reviews
    $reviewsStmt = $conn->prepare("
        SELECT 
            pr.rating,
            pr.review_text,
            pr.is_verified_purchase,
            c.fullname as customer_name,
            DATE_FORMAT(pr.created_at, '%b %d, %Y') as review_date
        FROM product_reviews pr
        JOIN customer c ON pr.customer_id = c.customer_id
        WHERE pr.product_code = ? AND pr.kitchen = ?
        ORDER BY pr.created_at DESC
        LIMIT 20
    ");
    $reviewsStmt->bind_param("ss", $product_code, $kitchen);
    $reviewsStmt->execute();
    $result = $reviewsStmt->get_result();
    
    $reviews = [];
    while ($row = $result->fetch_assoc()) {
        $reviews[] = $row;
    }
    $reviewsStmt->close();
    
    echo json_encode([
        'success' => true,
        'reviews' => $reviews,
        'stats' => $stats
    ]);
    exit;
}

// Get average rating for a product
if ($action === 'get_product_rating') {
    $product_code = $_GET['product_code'] ?? '';
    $kitchen = $_GET['kitchen'] ?? '';
    
    if ($product_code && $kitchen) {
        $stmt = $conn->prepare("
            SELECT 
                COUNT(*) as review_count,
                COALESCE(AVG(rating), 0) as avg_rating,
                SUM(CASE WHEN rating = 5 THEN 1 ELSE 0 END) as five_star,
                SUM(CASE WHEN rating = 4 THEN 1 ELSE 0 END) as four_star,
                SUM(CASE WHEN rating = 3 THEN 1 ELSE 0 END) as three_star,
                SUM(CASE WHEN rating = 2 THEN 1 ELSE 0 END) as two_star,
                SUM(CASE WHEN rating = 1 THEN 1 ELSE 0 END) as one_star
            FROM product_reviews 
            WHERE product_code = ? AND kitchen = ?
        ");
        $stmt->bind_param("ss", $product_code, $kitchen);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        
        echo json_encode([
            'success' => true,
            'data' => $result
        ]);
        $stmt->close();
    } else {
        echo json_encode(['success' => false, 'message' => 'Missing parameters']);
    }
    exit;
}

// Get reviews for a product
if ($action === 'get_product_reviews') {
    $product_code = $_GET['product_code'] ?? '';
    $kitchen = $_GET['kitchen'] ?? '';
    $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 10;
    $offset = isset($_GET['offset']) ? (int)$_GET['offset'] : 0;
    
    if ($product_code && $kitchen) {
        $stmt = $conn->prepare("
            SELECT 
                pr.*,
                c.username,
                c.fullname,
                DATE_FORMAT(pr.created_at, '%M %d, %Y') as review_date
            FROM product_reviews pr
            JOIN customer c ON pr.customer_id = c.customer_id
            WHERE pr.product_code = ? AND pr.kitchen = ?
            ORDER BY pr.created_at DESC
            LIMIT ? OFFSET ?
        ");
        $stmt->bind_param("ssii", $product_code, $kitchen, $limit, $offset);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $reviews = [];
        while ($row = $result->fetch_assoc()) {
            $reviews[] = $row;
        }
        
        echo json_encode([
            'success' => true,
            'data' => $reviews
        ]);
        $stmt->close();
    } else {
        echo json_encode(['success' => false, 'message' => 'Missing parameters']);
    }
    exit;
}

// Submit product review
if ($action === 'submit_product_review' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_SESSION['user_id']) || $_SESSION['usertype'] !== 'customer') {
        echo json_encode(['success' => false, 'message' => 'Please log in to submit a review']);
        exit;
    }
    
    $customer_id = (int)$_SESSION['user_id'];
    $product_code = trim($_POST['product_code'] ?? '');
    $kitchen = trim($_POST['kitchen'] ?? '');
    $rating = (int)($_POST['rating'] ?? 0);
    $review_text = trim($_POST['review_text'] ?? '');
    $order_id = isset($_POST['order_id']) ? (int)$_POST['order_id'] : null;
    
    if (!$product_code || !$kitchen || $rating < 1 || $rating > 5) {
        echo json_encode(['success' => false, 'message' => 'Invalid input']);
        exit;
    }
    
    // Check if customer has already reviewed this product
    $check = $conn->prepare("SELECT review_id FROM product_reviews WHERE customer_id = ? AND product_code = ? AND kitchen = ?");
    $check->bind_param("iss", $customer_id, $product_code, $kitchen);
    $check->execute();
    if ($check->get_result()->num_rows > 0) {
        echo json_encode(['success' => false, 'message' => 'You have already reviewed this product']);
        $check->close();
        exit;
    }
    $check->close();
    
    // Check if it's a verified purchase
    $is_verified = 0;
    if ($order_id) {
        $verify = $conn->prepare("
            SELECT 1 FROM order_items oi
            JOIN orders o ON oi.order_id = o.order_id
            WHERE o.order_id = ? AND o.customer_id = ? AND oi.product_code = ? AND o.order_status = 'delivered'
        ");
        $verify->bind_param("iis", $order_id, $customer_id, $product_code);
        $verify->execute();
        if ($verify->get_result()->num_rows > 0) {
            $is_verified = 1;
        }
        $verify->close();
    }
    
    $stmt = $conn->prepare("
        INSERT INTO product_reviews (customer_id, product_code, kitchen, order_id, rating, review_text, is_verified_purchase)
        VALUES (?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->bind_param("issiisi", $customer_id, $product_code, $kitchen, $order_id, $rating, $review_text, $is_verified);
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Review submitted successfully!']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to submit review: ' . $stmt->error]);
    }
    $stmt->close();
    exit;
}

// Submit order review
if ($action === 'submit_order_review' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_SESSION['user_id']) || $_SESSION['usertype'] !== 'customer') {
        echo json_encode(['success' => false, 'message' => 'Please log in to submit a review']);
        exit;
    }
    
    $customer_id = (int)$_SESSION['user_id'];
    $order_id = (int)($_POST['order_id'] ?? 0);
    $overall_rating = (int)($_POST['overall_rating'] ?? 0);
    $food_rating = isset($_POST['food_rating']) ? (int)$_POST['food_rating'] : null;
    $delivery_rating = isset($_POST['delivery_rating']) ? (int)$_POST['delivery_rating'] : null;
    $review_text = trim($_POST['review_text'] ?? '');
    
    if (!$order_id || $overall_rating < 1 || $overall_rating > 5) {
        echo json_encode(['success' => false, 'message' => 'Invalid input']);
        exit;
    }
    
    // Verify order belongs to customer and is delivered
    $verify = $conn->prepare("SELECT order_id FROM orders WHERE order_id = ? AND customer_id = ? AND order_status = 'delivered'");
    $verify->bind_param("ii", $order_id, $customer_id);
    $verify->execute();
    if ($verify->get_result()->num_rows === 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid order or order not delivered yet']);
        $verify->close();
        exit;
    }
    $verify->close();
    
    // Check if already reviewed
    $check = $conn->prepare("SELECT review_id FROM order_reviews WHERE order_id = ?");
    $check->bind_param("i", $order_id);
    $check->execute();
    if ($check->get_result()->num_rows > 0) {
        echo json_encode(['success' => false, 'message' => 'You have already reviewed this order']);
        $check->close();
        exit;
    }
    $check->close();
    
    $stmt = $conn->prepare("
        INSERT INTO order_reviews (order_id, customer_id, overall_rating, food_rating, delivery_rating, review_text)
        VALUES (?, ?, ?, ?, ?, ?)
    ");
    $stmt->bind_param("iiiiss", $order_id, $customer_id, $overall_rating, $food_rating, $delivery_rating, $review_text);
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Order review submitted successfully!']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to submit review: ' . $stmt->error]);
    }
    $stmt->close();
    exit;
}

// Get order review status
if ($action === 'get_order_review_status') {
    if (!isset($_SESSION['user_id'])) {
        echo json_encode(['success' => false, 'message' => 'Not logged in']);
        exit;
    }
    
    $order_id = (int)($_GET['order_id'] ?? 0);
    $customer_id = (int)$_SESSION['user_id'];
    
    if ($order_id) {
        $stmt = $conn->prepare("
            SELECT review_id, overall_rating, food_rating, delivery_rating, review_text 
            FROM order_reviews 
            WHERE order_id = ? AND customer_id = ?
        ");
        $stmt->bind_param("ii", $order_id, $customer_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($row = $result->fetch_assoc()) {
            echo json_encode(['success' => true, 'reviewed' => true, 'data' => $row]);
        } else {
            echo json_encode(['success' => true, 'reviewed' => false]);
        }
        $stmt->close();
    } else {
        echo json_encode(['success' => false, 'message' => 'Missing order ID']);
    }
    exit;
}

echo json_encode(['success' => false, 'message' => 'Invalid action']);
