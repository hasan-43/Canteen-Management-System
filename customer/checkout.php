<?php
session_start();
require_once __DIR__ . '/../config/db_connection.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
}

$customerId = (int)$_SESSION['user_id'];
$kitchen = isset($_POST['kitchen']) ? $_POST['kitchen'] : (isset($_GET['kitchen']) ? $_GET['kitchen'] : '');

// Fetch allowed kitchens dynamically
$allowedTables = [];
$shopResult = $conn->query("SELECT shop_name FROM shop");
if ($shopResult) {
    while ($row = $shopResult->fetch_assoc()) {
        $allowedTables[] = $row['shop_name'];
    }
}

if (!in_array($kitchen, $allowedTables, true)) {
    header("Location: cart.php");
    exit();
}

// Get customer's cart
$cartId = null;
$stmt = $conn->prepare("SELECT cart_id FROM customer_cart WHERE customer_id = ? LIMIT 1");
$stmt->bind_param("i", $customerId);
$stmt->execute();
$result = $stmt->get_result();
if ($row = $result->fetch_assoc()) {
    $cartId = $row['cart_id'];
}
$stmt->close();

if (!$cartId) {
    header("Location: cart.php");
    exit();
}

// Fetch items for this kitchen only
$cartItems = [];
$stmt = $conn->prepare("
    SELECT item_id, product_code, product_name, kitchen, price, quantity 
    FROM cart_items 
    WHERE cart_id = ? AND kitchen = ?
    ORDER BY product_name
");
$stmt->bind_param("is", $cartId, $kitchen);
$stmt->execute();
$result = $stmt->get_result();

while ($row = $result->fetch_assoc()) {
    // Get product image
    $image = '';
    $imgStmt = $conn->prepare("SELECT image FROM {$row['kitchen']} WHERE product_code = ? LIMIT 1");
    $imgStmt->bind_param("s", $row['product_code']);
    $imgStmt->execute();
    $imgResult = $imgStmt->get_result();
    if ($imgRow = $imgResult->fetch_assoc()) {
        $image = $imgRow['image'];
    }
    $imgStmt->close();
    
    $row['image'] = $image;
    $cartItems[] = $row;
}
$stmt->close();

if (empty($cartItems)) {
    header("Location: cart.php");
    exit();
}

// Calculate total
$subtotal = 0;
foreach ($cartItems as $item) {
    $subtotal += $item['price'] * $item['quantity'];
}
$deliveryFee = 0.00; // Free delivery
$total = $subtotal + $deliveryFee;

// Get customer info
$customerInfo = [];
$stmt = $conn->prepare("SELECT fullname, email, phone, address FROM customer WHERE customer_id = ? LIMIT 1");
$stmt->bind_param("i", $customerId);
$stmt->execute();
$result = $stmt->get_result();
if ($row = $result->fetch_assoc()) {
    $customerInfo = $row;
}
$stmt->close();

// Handle order placement
$orderSuccess = false;
$orderMessage = '';
$debugInfo = [];

// Ensure required order tables exist (auto-create if missing)
function fw_ensure_order_tables($conn) {
    try {
        $conn->query("CREATE TABLE IF NOT EXISTS orders (
            order_id INT AUTO_INCREMENT PRIMARY KEY,
            order_number VARCHAR(50) NOT NULL UNIQUE,
            customer_id INT NOT NULL,
            kitchen VARCHAR(50) NOT NULL,
            phone_number VARCHAR(20) NOT NULL,
            delivery_address TEXT NOT NULL,
            payment_method ENUM('cash_on_delivery','mobile_banking','card') NOT NULL DEFAULT 'cash_on_delivery',
            special_instructions TEXT NULL,
            subtotal DECIMAL(10,2) NOT NULL,
            delivery_fee DECIMAL(10,2) NOT NULL DEFAULT 0.00,
            total_amount DECIMAL(10,2) NOT NULL,
            order_status ENUM('pending','confirmed','preparing','out_for_delivery','delivered','cancelled') NOT NULL DEFAULT 'pending',
            order_date TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;");

        $conn->query("CREATE TABLE IF NOT EXISTS order_items (
            order_item_id INT AUTO_INCREMENT PRIMARY KEY,
            order_id INT NOT NULL,
            product_code VARCHAR(50) NOT NULL,
            product_name VARCHAR(255) NOT NULL,
            price DECIMAL(10,2) NOT NULL,
            quantity INT NOT NULL,
            subtotal DECIMAL(10,2) NOT NULL,
            FOREIGN KEY (order_id) REFERENCES orders(order_id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;");
    } catch (Exception $e) {
        // Let normal error handling pick this up later
        error_log('Failed to ensure order tables: ' . $e->getMessage());
    }
}

// Debug: Check if POST was received
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $debugInfo[] = 'POST request received';
    if (isset($_POST['place_order'])) {
        $debugInfo[] = 'place_order button clicked';
    } else {
        $debugInfo[] = 'place_order NOT in POST data';
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['place_order'])) {
    $deliveryAddress = trim($_POST['delivery_address'] ?? '');
    $paymentMethod = $_POST['payment_method'] ?? '';
    $specialInstructions = trim($_POST['special_instructions'] ?? '');
    
    $debugInfo[] = "Delivery Address: " . ($deliveryAddress ? 'Provided' : 'Empty');
    $debugInfo[] = "Payment Method: " . ($paymentMethod ? $paymentMethod : 'Not selected');
    
    if (empty($deliveryAddress)) {
        $orderMessage = 'Please enter delivery address';
    } elseif (!in_array($paymentMethod, ['cash_on_delivery', 'mobile_banking', 'card'])) {
        $orderMessage = 'Please select a payment method';
    } else {
        // Try to auto-create required tables, then verify
        fw_ensure_order_tables($conn);
        // Check if tables exist
        $tablesExist = true;
        $checkTables = ['orders', 'order_items'];
        $missingTables = [];
        
        foreach ($checkTables as $table) {
            $result = $conn->query("SHOW TABLES LIKE '$table'");
            if ($result->num_rows === 0) {
                $missingTables[] = $table;
                $tablesExist = false;
            }
        }
        
        if (!$tablesExist) {
            $orderMessage = 'Database tables missing: ' . implode(', ', $missingTables) . '. Please create these tables first.';
            $debugInfo[] = 'ERROR: Missing tables - ' . implode(', ', $missingTables);
            error_log('Missing tables: ' . implode(', ', $missingTables));
        } else {
            $debugInfo[] = 'All required tables exist';
            
            // Create order
            $conn->begin_transaction();
            
            try {
                // Fail fast on MySQL errors
                mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
                $debugInfo[] = 'Starting transaction';
                
                $orderNumber = 'ORD-' . date('Ymd') . '-' . strtoupper(substr($kitchen, 0, 3)) . '-' . rand(1000, 9999);
                $debugInfo[] = "Generated order number: $orderNumber";
                
                $stmt = $conn->prepare("
                    INSERT INTO orders (order_number, customer_id, kitchen, delivery_address, payment_method, 
                                       special_instructions, subtotal, delivery_fee, total_amount, order_status)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending')
                ");
                $stmt->bind_param("sissssddd", $orderNumber, $customerId, $kitchen, $deliveryAddress, 
                                $paymentMethod, $specialInstructions, $subtotal, $deliveryFee, $total);
                
                if (!$stmt->execute()) {
                    throw new Exception("Failed to insert order: " . $stmt->error);
                }
                
                $orderId = $conn->insert_id;
                $debugInfo[] = "Order inserted with ID: $orderId";
                $stmt->close();
                
                // Insert order items
                foreach ($cartItems as $item) {
                    $itemSubtotal = $item['price'] * $item['quantity'];
                    $stmt = $conn->prepare("
                        INSERT INTO order_items (order_id, product_code, product_name, price, quantity, subtotal)
                        VALUES (?, ?, ?, ?, ?, ?)
                    ");
                    $stmt->bind_param("issdid", $orderId, $item['product_code'], $item['product_name'], 
                                    $item['price'], $item['quantity'], $itemSubtotal);
                    
                    if (!$stmt->execute()) {
                        throw new Exception("Failed to insert order items: " . $stmt->error);
                    }
                    $stmt->close();
                }
                $debugInfo[] = count($cartItems) . ' order items inserted';
                
                // Remove items from cart for this kitchen
                $stmt = $conn->prepare("DELETE FROM cart_items WHERE cart_id = ? AND kitchen = ?");
                $stmt->bind_param("is", $cartId, $kitchen);
                
                if (!$stmt->execute()) {
                    throw new Exception("Failed to remove cart items: " . $stmt->error);
                }
                
                $affectedRows = $stmt->affected_rows;
                $debugInfo[] = "Deleted $affectedRows cart items";
                
                if ($affectedRows === 0) {
                    error_log("Warning: No cart items were deleted for cart_id=$cartId and kitchen=$kitchen");
                    $debugInfo[] = 'WARNING: No cart items were deleted';
                }
                
                $stmt->close();
                
                $conn->commit();
                $debugInfo[] = 'Transaction committed successfully';
                $orderSuccess = true;

                // Redirect to Order Success page
                $_SESSION['order_placed'] = true;
                header("Location: order_success.php?order=" . urlencode($orderNumber));
                exit();
                
            } catch (Exception $e) {
                $conn->rollback();
                $orderMessage = 'Order placement failed: ' . $e->getMessage();
                $debugInfo[] = 'EXCEPTION: ' . $e->getMessage();
                error_log("Order placement error: " . $e->getMessage());
            }
        }
    }
}

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
    <script src="../resources/js/theme.js"></script>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Checkout - <?= ucfirst($kitchen) ?> Kitchen - Campus Cravings</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap');
        
        html, body {
            margin: 0;
            padding: 0;
            min-height: 100vh;
            width: 100%;
        }
        
        body {
            font-family: 'Inter', sans-serif;
            position: relative;
            background: #f5f7fa;
        }
        
        body::before {
            content: '';
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-image: url('../resources/cart.jpg');
            background-size: cover;
            background-position: center;
            background-repeat: no-repeat;
            background-attachment: fixed;
            z-index: -2;
        }
        
        body::after {
            content: '';
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            width: 100vw;
            height: 100vh;
            background: rgba(255, 255, 255, 0.75);
            z-index: -1;
        }
        
        header { position: sticky; top: 0; z-index: 50; background: rgba(10, 10, 12, 0.9) !important; backdrop-filter: blur(12px) !important; -webkit-backdrop-filter: blur(12px) !important; border-bottom: 1px solid rgba(255, 255, 255, 0.1) !important; }
        header a { display: flex; align-items: center; gap: 0.75rem; text-decoration: none; }
        .brand-text { font-size: 1.25rem; font-weight: 800; color: #ffffff; letter-spacing: 0.05em; transition: color 0.3s ease; }
        header a:hover .brand-text { color: #ef4444; }
        .campus-cravings-logo {
            height: 50px;
            width: auto;
            transition: transform 0.3s ease;
        }
        .campus-cravings-logo:hover {
            transform: scale(1.05);
        }
        
        .nav-buttons { display: flex; align-items: center; gap: 0.75rem; }
        .nav-link { padding: 0.5rem 0.9rem; border-radius: 0.5rem; font-weight: 600; color: #ffffff; }
        .nav-link:hover { background-color: rgba(239, 68, 68, 0.2); color: #fca5a5; }
        
        .initials-circle { 
            width: 42px; height: 42px; border-radius: 9999px; 
            background: linear-gradient(135deg,#ef4444,#7f1d1d); 
            display: inline-flex; align-items: center; justify-content: center; 
            color: #fff; font-weight: 700; 
        }
        
        .profile-menu.show { display: block; }
        
        .payment-option {
            border: 3px solid #e5e7eb;
            cursor: pointer;
            transition: all 0.3s;
            background: linear-gradient(135deg, #ffffff 0%, #f9fafb 100%);
        }
        
        .payment-option:hover {
            border-color: #3b82f6;
            transform: translateY(-2px);
            box-shadow: 0 10px 25px -5px rgba(59, 130, 246, 0.3);
            background: linear-gradient(135deg, #eff6ff 0%, #dbeafe 100%);
        }
        
        .payment-option input[type="radio"]:checked + label {
            border-color: #3b82f6;
            background: linear-gradient(135deg, #dbeafe 0%, #bfdbfe 100%);
            box-shadow: 0 10px 30px -5px rgba(59, 130, 246, 0.4);
        }
        
        .payment-icon {
            font-size: 2.5rem;
            margin-bottom: 0.5rem;
        }
        
        .order-summary-card {
            background: linear-gradient(135deg, #ffffff 0%, #f8fafc 100%);
            border: 2px solid #e2e8f0;
        }
        
        .delivery-card {
            background: linear-gradient(135deg, #ffffff 0%, #eff6ff 100%);
            border: 2px solid #dbeafe;
        }
        
        .payment-card {
            background: linear-gradient(135deg, #ffffff 0%, #f0fdf4 100%);
            border: 2px solid #dcfce7;
        }

        /* Outer checkout container styling */
        .checkout-outer {
            position: relative;
            background: linear-gradient(135deg, #fff1f2 0%, #ffe4e6 100%);
            border: 2px solid #fecdd3;
            border-radius: 1.25rem; /* 20px */
            box-shadow: 0 20px 40px -20px rgba(185, 28, 28, 0.25);
            padding: 0.5rem;
        }
        .checkout-outer::before {
            content: '';
            position: absolute;
            left: -10px;
            top: 20px;
            bottom: 20px;
            width: 6px;
            border-radius: 6px;
            background: linear-gradient(180deg, #ef4444, #f97316);
            opacity: 0.6;
        }
        .checkout-inner {
            background: rgba(255,255,255,0.9);
            backdrop-filter: blur(2px);
            border: 2px solid #fee2e2;
            border-radius: 1rem;
            padding: 1rem;
        }

        /* Slightly warmer summary card */
        .order-summary-card {
            background: linear-gradient(135deg, #fff7ed 0%, #fff1f2 100%);
            border: 2px solid #fecaca;
        }
    </style>
</head>

<body class="min-h-screen">
    <!-- Header/Navbar -->
    <header>
        <div class="max-w-6xl mx-auto px-4 h-16 flex items-center justify-between gap-6">
            <a href="./navbar.php">
                <img src="../resources/logo.jpg" alt="Campus Cravings" class="campus-cravings-logo" />
                <span class="brand-text">Campus Cravings</span>
            </a>
            
            <nav class="nav-buttons text-sm">
                <a href="./navbar.php" class="nav-link">Home</a>
                <a href="cart.php" class="nav-link">Back to Cart</a>
            </nav>
            
            <div class="flex items-center gap-4">
                <div class="relative" id="profileRoot">
                    <button id="profileBtn" class="flex items-center gap-2 hover:opacity-80 transition">
                        <?php if ($profilePic): ?>
                            <img src="<?= htmlspecialchars($profilePic) ?>" alt="Profile" class="w-10 h-10 rounded-full object-cover border-2 border-red-600">
                        <?php else: ?>
                            <div class="initials-circle"><?= htmlspecialchars($initials_text) ?></div>
                        <?php endif; ?>
                    </button>
                    <div id="profileMenu" class="profile-menu hidden absolute right-0 mt-2 w-48 bg-white rounded-lg shadow-lg border border-gray-200 py-2 z-50">
                        <div class="px-4 py-2 border-b border-gray-200">
                            <p class="font-semibold text-gray-800"><?= htmlspecialchars($displayName) ?></p>
                        </div>
                        <a href="./logout.php" class="block px-4 py-2 text-sm text-red-600 hover:bg-red-50">Logout</a>
                    </div>
                </div>
            </div>
        </div>
    </header>

    <!-- Main Content -->
    <main class="max-w-6xl mx-auto px-4 py-8">
        <div class="mb-6">
            <h1 class="text-4xl font-bold text-gray-800 flex items-center gap-3">
                <i class="fas fa-credit-card text-red-600 bg-red-100 p-3 rounded-full"></i>
                Checkout - <span class="text-red-600"><?= ucfirst($kitchen) ?></span> Kitchen
            </h1>
            <p class="text-gray-700 mt-3 text-lg">Complete your order details and confirm payment</p>
        </div>

        <?php if ($orderMessage): ?>
            <div class="mb-6 p-4 rounded-lg bg-red-100 text-red-800">
                <i class="fas fa-exclamation-circle mr-2"></i>
                <?= htmlspecialchars($orderMessage) ?>
            </div>
        <?php endif; ?>

        <?php if (!empty($debugInfo) && isset($_GET['debug'])): ?>
            <div class="mb-6 p-4 rounded-lg bg-blue-100 text-blue-900 border border-blue-300">
                <h3 class="font-bold mb-2"><i class="fas fa-bug mr-2"></i>Debug Information:</h3>
                <ul class="list-disc list-inside text-sm space-y-1">
                    <?php foreach ($debugInfo as $info): ?>
                        <li><?= htmlspecialchars($info) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <form method="POST" id="checkoutForm" onsubmit="return validateCheckoutForm(event)">
            <input type="hidden" name="kitchen" value="<?= htmlspecialchars($kitchen) ?>">
            <div class="checkout-outer">
                <div class="checkout-inner">
                    <div class="grid lg:grid-cols-3 gap-6">
                <!-- Left Column - Order Details -->
                <div class="lg:col-span-2 space-y-6">
                    <!-- Delivery Address -->
                    <div class="delivery-card rounded-2xl shadow-xl p-6">
                        <h2 class="text-2xl font-bold text-gray-800 mb-4 flex items-center gap-3">
                            <i class="fas fa-map-marker-alt text-blue-600 bg-blue-100 p-3 rounded-full"></i>
                            Delivery Address
                        </h2>
                        
                        <div class="space-y-4">
                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-2">Full Name</label>
                                <input type="text" 
                                       value="<?= htmlspecialchars($customerInfo['fullname'] ?? $displayName) ?>" 
                                       readonly
                                       class="w-full px-4 py-3 bg-blue-50 border-2 border-blue-200 rounded-lg font-medium text-gray-700">
                            </div>
                            
                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-2">
                                    Phone Number
                                </label>
                                <input type="text" 
                                       name="phone_number"
                                       id="phone_number"
                                       value="<?= htmlspecialchars($customerInfo['phone'] ?? '') ?>" 
                                       placeholder="Enter your contact number (e.g., 01712345678)"
                                       class="w-full px-4 py-3 border-2 border-blue-300 rounded-lg focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-200">
                                <p class="text-xs text-gray-600 mt-1">Optional - We'll use this number to contact you about your order</p>
                            </div>
                            
                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-2">
                                    Delivery Address <span class="text-red-600">*</span>
                                </label>
                                <textarea name="delivery_address" 
                                          id="delivery_address"
                                          rows="3" 
                                          required
                                          placeholder="Enter your complete delivery address (House/Flat, Road, Area, City)"
                                          class="w-full px-4 py-3 border-2 border-blue-300 rounded-lg focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-200"><?= htmlspecialchars($customerInfo['address'] ?? '') ?></textarea>
                            </div>
                            
                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-2">
                                    Special Instructions (Optional)
                                </label>
                                <textarea name="special_instructions" 
                                          rows="2" 
                                          placeholder="Add any special delivery instructions..."
                                          class="w-full px-4 py-3 border-2 border-blue-300 rounded-lg focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-200"></textarea>
                            </div>
                        </div>
                    </div>

                    <!-- Payment Method -->
                    <div class="payment-card rounded-2xl shadow-xl p-6">
                        <h2 class="text-2xl font-bold text-gray-800 mb-4 flex items-center gap-3">
                            <i class="fas fa-wallet text-green-600 bg-green-100 p-3 rounded-full"></i>
                            Payment Method
                        </h2>
                        
                        <div class="grid md:grid-cols-3 gap-4">
                            <!-- Cash on Delivery -->
                            <div>
                                <input type="radio" name="payment_method" value="cash_on_delivery" id="cash" class="hidden" required>
                                <label for="cash" class="payment-option block rounded-xl p-6 text-center">
                                    <div class="payment-icon text-green-600">
                                        <i class="fas fa-money-bill-wave"></i>
                                    </div>
                                    <h3 class="font-bold text-gray-800 mb-1">Cash on Delivery</h3>
                                    <p class="text-xs text-gray-600">Pay when you receive</p>
                                </label>
                            </div>

                            <!-- Mobile Banking -->
                            <div>
                                <input type="radio" name="payment_method" value="mobile_banking" id="mobile" class="hidden">
                                <label for="mobile" class="payment-option block rounded-xl p-6 text-center">
                                    <div class="payment-icon text-purple-600">
                                        <i class="fas fa-mobile-alt"></i>
                                    </div>
                                    <h3 class="font-bold text-gray-800 mb-1">Mobile Banking</h3>
                                    <p class="text-xs text-gray-600">bKash/Nagad/Rocket</p>
                                </label>
                            </div>

                            <!-- Card Payment -->
                            <div>
                                <input type="radio" name="payment_method" value="card" id="card" class="hidden">
                                <label for="card" class="payment-option block rounded-xl p-6 text-center">
                                    <div class="payment-icon text-blue-600">
                                        <i class="fas fa-credit-card"></i>
                                    </div>
                                    <h3 class="font-bold text-gray-800 mb-1">Card Payment</h3>
                                    <p class="text-xs text-gray-600">Credit/Debit Card</p>
                                </label>
                            </div>
                        </div>

                        <div class="mt-4 p-4 bg-yellow-50 rounded-lg border border-yellow-200">
                            <p class="text-sm text-yellow-800">
                                <i class="fas fa-info-circle mr-2"></i>
                                <strong>Note:</strong> This is a demo payment system. All payment methods are currently accepted as Cash on Delivery.
                            </p>
                        </div>
                    </div>
                </div>

                <!-- Right Column - Order Summary -->
                <div class="lg:col-span-1">
                    <div class="order-summary-card rounded-2xl shadow-xl p-6 sticky top-24">
                        <h2 class="text-2xl font-bold text-gray-800 mb-4 flex items-center gap-3">
                            <i class="fas fa-receipt text-red-600 bg-red-100 p-3 rounded-full"></i>
                            Order Summary
                        </h2>
                        
                        <div class="mb-4 pb-4 border-b-2 border-gray-300">
                            <h3 class="font-bold text-lg text-gray-800 mb-3 flex items-center gap-2">
                                <span class="bg-gradient-to-r from-blue-500 to-purple-500 text-white px-3 py-1 rounded-full text-sm">
                                    <?= ucfirst($kitchen) ?> Kitchen
                                </span>
                            </h3>
                            <div class="space-y-3 max-h-64 overflow-y-auto">
                                <?php foreach ($cartItems as $item): ?>
                                    <div class="flex justify-between text-sm bg-gray-50 p-3 rounded-lg">
                                        <div class="flex-1">
                                            <p class="text-gray-800 font-semibold"><?= htmlspecialchars($item['product_name']) ?></p>
                                            <p class="text-gray-600 text-xs mt-1">৳<?= number_format($item['price'], 2) ?> × <?= $item['quantity'] ?></p>
                                        </div>
                                        <span class="font-bold text-gray-800">৳<?= number_format($item['price'] * $item['quantity'], 2) ?></span>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        
                        <div class="space-y-3 mb-4 pb-4 border-b-2 border-gray-300">
                            <div class="flex justify-between text-gray-700 font-medium">
                                <span>Subtotal:</span>
                                <span class="font-bold">৳<?= number_format($subtotal, 2) ?></span>
                            </div>
                            <div class="flex justify-between text-gray-700 font-medium">
                                <span>Delivery Fee:</span>
                                <span class="font-bold text-green-600">FREE</span>
                            </div>
                        </div>
                        
                        <div class="flex justify-between items-center mb-6 p-4 bg-gradient-to-r from-red-50 to-orange-50 rounded-lg border-2 border-red-200">
                            <span class="text-lg font-bold text-gray-800">Total:</span>
                            <span class="text-3xl font-bold text-red-600">৳<?= number_format($total, 2) ?></span>
                        </div>
                        
                        <button type="submit" name="place_order" 
                                class="w-full py-4 bg-gradient-to-r from-red-600 to-red-700 text-white font-bold text-lg rounded-lg hover:shadow-2xl transition transform hover:scale-105 shadow-lg">
                            <i class="fas fa-check-circle mr-2"></i>Place Order
                        </button>
                        
                        <a href="cart.php" class="block w-full mt-3 py-3 bg-gradient-to-r from-gray-200 to-gray-300 text-gray-800 text-center font-semibold rounded-lg hover:from-gray-300 hover:to-gray-400 transition shadow-md">
                            <i class="fas fa-arrow-left mr-2"></i>Back to Cart
                        </a>
                    </div>
                </div>
                    </div>
                </div>
            </div>
        </form>
    </main>

    <script>
        // Form validation on submit
        function validateCheckoutForm(event) {
            const address = document.getElementById('delivery_address').value.trim();
            const paymentMethod = document.querySelector('input[name="payment_method"]:checked');

            if (!address) {
                alert('Please enter a delivery address');
                event.preventDefault();
                return false;
            }
            if (!paymentMethod) {
                alert('Please select a payment method');
                event.preventDefault();
                return false;
            }
            return true;
        }

        // Profile menu toggle
        document.getElementById('profileBtn').addEventListener('click', function(e) {
            e.stopPropagation();
            document.getElementById('profileMenu').classList.toggle('show');
        });
        
        document.addEventListener('click', function() {
            document.getElementById('profileMenu').classList.remove('show');
        });
        
        // Payment method selection visual feedback
        document.querySelectorAll('input[name="payment_method"]').forEach(radio => {
            radio.addEventListener('change', function() {
                document.querySelectorAll('.payment-option').forEach(option => {
                    option.classList.remove('border-blue-500', 'bg-gradient-to-br', 'from-blue-50', 'to-blue-100');
                    option.classList.add('border-gray-200');
                });
                
                if (this.checked) {
                    const label = document.querySelector(`label[for="${this.id}"]`);
                    label.classList.remove('border-gray-200');
                    label.classList.add('border-blue-500', 'bg-gradient-to-br', 'from-blue-50', 'to-blue-100');
                }
            });
        });
    </script>
</body>
</html>
