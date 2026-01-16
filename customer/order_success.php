<?php
session_start();
require_once __DIR__ . '/../config/db_connection.php';

// Check if order was placed
if (!isset($_SESSION['order_placed']) || !isset($_GET['order'])) {
    header("Location: cart.php");
    exit();
}

$orderNumber = $_GET['order'];
$customerId = (int)$_SESSION['user_id'];

// Fetch order details
$order = null;
$orderItems = [];

$stmt = $conn->prepare("
    SELECT o.*, c.fullname, c.email, c.phone 
    FROM orders o 
    JOIN customer c ON o.customer_id = c.customer_id 
    WHERE o.order_number = ? AND o.customer_id = ?
");
$stmt->bind_param("si", $orderNumber, $customerId);
$stmt->execute();
$result = $stmt->get_result();
$order = $result->fetch_assoc();
$stmt->close();

if ($order) {
    $stmt = $conn->prepare("SELECT * FROM order_items WHERE order_id = ?");
    $stmt->bind_param("i", $order['order_id']);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $orderItems[] = $row;
    }
    $stmt->close();
}

// Clear session
unset($_SESSION['order_placed']);

function initials($name) {
    $parts = preg_split('/\s+/', trim($name));
    $letters = '';
    foreach ($parts as $p) {
        if ($p !== '') $letters .= strtoupper(substr($p, 0, 1));
        if (strlen($letters) >= 2) break;
    }
    return $letters ?: 'C';
}

$displayName = $_SESSION['fullname'] ?? $_SESSION['username'] ?? 'Customer';
$profilePic = $_SESSION['profile_pic'] ?? null;
$initials_text = initials($displayName);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Order Successful - Food Wave</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap');
        
        body {
            font-family: 'Inter', sans-serif;
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
        }
        
        .food-wave {
            font-weight: 900;
            letter-spacing: 3px;
            font-size: 2.4rem;
            background: linear-gradient(90deg, #ff0000);
            -webkit-background-clip: text;
            background-clip: text;
            -webkit-text-fill-color: transparent;
            animation: fadeOpenClose 3s ease-in-out infinite;
            display: inline-block;
        }
        
        @keyframes fadeOpenClose {
            0%   { opacity:0; transform: scale(0.6); }
            25%  { opacity:1; transform: scale(1); }
            75%  { opacity:1; transform: scale(1); }
            100% { opacity:0; transform: scale(0.6); }
        }
        
        .success-icon {
            animation: scaleIn 0.5s ease-out;
        }
        
        @keyframes scaleIn {
            0% { transform: scale(0); }
            50% { transform: scale(1.2); }
            100% { transform: scale(1); }
        }
        
        .checkmark-circle {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            background: linear-gradient(135deg, #10b981, #059669);
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto;
            box-shadow: 0 10px 40px rgba(16, 185, 129, 0.4);
        }
    </style>
</head>

<body class="min-h-screen">
    <!-- Header -->
    <header class="bg-white/95 backdrop-blur border-b border-gray-200">
        <div class="max-w-6xl mx-auto px-4 h-16 flex items-center justify-between gap-6">
            <div class="food-wave">Food Wave</div>
        </div>
    </header>

    <!-- Main Content -->
    <main class="max-w-4xl mx-auto px-4 py-12">
        <div class="bg-white rounded-3xl shadow-2xl overflow-hidden">
            <!-- Success Header -->
            <div class="bg-gradient-to-r from-green-500 to-green-600 p-12 text-center text-white">
                <div class="checkmark-circle success-icon mb-6">
                    <i class="fas fa-check text-white text-6xl"></i>
                </div>
                <h1 class="text-4xl font-bold mb-3">Order Placed Successfully!</h1>
                <p class="text-lg text-green-100">Thank you for your order</p>
            </div>
            
            <?php if ($order): ?>
                <!-- Order Details -->
                <div class="p-8">
                    <div class="bg-blue-50 rounded-xl p-6 mb-6 text-center">
                        <p class="text-sm text-gray-600 mb-2">Order Number</p>
                        <p class="text-3xl font-bold text-blue-600"><?= htmlspecialchars($orderNumber) ?></p>
                        <p class="text-sm text-gray-600 mt-2"><?= date('F j, Y - g:i A', strtotime($order['order_date'])) ?></p>
                    </div>
                    
                    <div class="grid md:grid-cols-2 gap-6 mb-6">
                        <div class="bg-gray-50 rounded-xl p-5">
                            <h3 class="font-bold text-gray-800 mb-3 flex items-center">
                                <i class="fas fa-map-marker-alt text-red-600 mr-2"></i>
                                Delivery Address
                            </h3>
                            <p class="text-gray-700"><?= nl2br(htmlspecialchars($order['delivery_address'])) ?></p>
                        </div>
                        
                        <div class="bg-gray-50 rounded-xl p-5">
                            <h3 class="font-bold text-gray-800 mb-3 flex items-center">
                                <i class="fas fa-wallet text-green-600 mr-2"></i>
                                Payment Method
                            </h3>
                            <p class="text-gray-700">
                                <?php
                                $paymentIcons = [
                                    'cash_on_delivery' => 'fa-money-bill-wave',
                                    'mobile_banking' => 'fa-mobile-alt',
                                    'card' => 'fa-credit-card'
                                ];
                                $paymentNames = [
                                    'cash_on_delivery' => 'Cash on Delivery',
                                    'mobile_banking' => 'Mobile Banking',
                                    'card' => 'Card Payment'
                                ];
                                ?>
                                <i class="fas <?= $paymentIcons[$order['payment_method']] ?> mr-2"></i>
                                <?= $paymentNames[$order['payment_method']] ?>
                            </p>
                        </div>
                    </div>
                    
                    <?php if ($order['special_instructions']): ?>
                        <div class="bg-yellow-50 rounded-xl p-5 mb-6">
                            <h3 class="font-bold text-gray-800 mb-2">
                                <i class="fas fa-comment-dots text-yellow-600 mr-2"></i>
                                Special Instructions
                            </h3>
                            <p class="text-gray-700"><?= nl2br(htmlspecialchars($order['special_instructions'])) ?></p>
                        </div>
                    <?php endif; ?>
                    
                    <div class="mb-6">
                        <h3 class="font-bold text-gray-800 mb-4 text-lg">
                            <i class="fas fa-utensils text-red-600 mr-2"></i>
                            Order Items - <?= ucfirst($order['kitchen']) ?> Kitchen
                        </h3>
                        <div class="space-y-3">
                            <?php foreach ($orderItems as $item): ?>
                                <div class="flex justify-between items-center p-4 bg-gray-50 rounded-lg">
                                    <div>
                                        <p class="font-semibold text-gray-800"><?= htmlspecialchars($item['product_name']) ?></p>
                                        <p class="text-sm text-gray-600">৳<?= number_format($item['price'], 2) ?> × <?= $item['quantity'] ?></p>
                                    </div>
                                    <p class="font-bold text-gray-800">৳<?= number_format($item['subtotal'], 2) ?></p>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    
                    <div class="bg-gradient-to-r from-red-50 to-orange-50 rounded-xl p-6 border-2 border-red-200">
                        <div class="space-y-2">
                            <div class="flex justify-between text-gray-700">
                                <span>Subtotal:</span>
                                <span class="font-semibold">৳<?= number_format($order['subtotal'], 2) ?></span>
                            </div>
                            <div class="flex justify-between text-gray-700">
                                <span>Delivery Fee:</span>
                                <span class="font-semibold">৳<?= number_format($order['delivery_fee'], 2) ?></span>
                            </div>
                            <div class="flex justify-between pt-3 border-t-2 border-red-300">
                                <span class="text-xl font-bold text-gray-800">Total:</span>
                                <span class="text-2xl font-bold text-red-600">৳<?= number_format($order['total_amount'], 2) ?></span>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mt-8 text-center space-y-3">
                        <p class="text-gray-600">
                            <i class="fas fa-info-circle text-blue-600 mr-2"></i>
                            Your order is being prepared and will be delivered soon
                        </p>
                        
                        <div class="flex gap-4 justify-center pt-4">
                            <a href="navbar.php" class="px-8 py-3 bg-gradient-to-r from-red-600 to-red-700 text-white font-semibold rounded-lg hover:shadow-xl transition">
                                <i class="fas fa-home mr-2"></i>Back to Home
                            </a>
                            <a href="cart.php" class="px-8 py-3 bg-gray-600 text-white font-semibold rounded-lg hover:bg-gray-700 transition">
                                <i class="fas fa-shopping-cart mr-2"></i>Continue Shopping
                            </a>
                        </div>
                    </div>
                </div>
            <?php else: ?>
                <div class="p-12 text-center">
                    <i class="fas fa-exclamation-triangle text-red-600 text-5xl mb-4"></i>
                    <h2 class="text-2xl font-bold text-gray-800 mb-4">Order Not Found</h2>
                    <a href="cart.php" class="inline-block px-6 py-3 bg-red-600 text-white rounded-lg">Back to Cart</a>
                </div>
            <?php endif; ?>
        </div>
    </main>
</body>
</html>
