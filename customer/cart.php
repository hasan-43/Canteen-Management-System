<?php
session_start();
require_once __DIR__ . '/../config/db_connection.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
}

$customerId = (int)$_SESSION['user_id'];
$message = '';
$messageType = '';

// Flash message from previous actions
if (isset($_SESSION['flash_message'])) {
    $message = $_SESSION['flash_message'];
    $messageType = $_SESSION['flash_type'] ?? 'success';
    unset($_SESSION['flash_message'], $_SESSION['flash_type']);
}

// Handle quantity update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_quantity'])) {
    $itemId = (int)$_POST['item_id'];
    $newQty = max(1, (int)$_POST['quantity']);
    
    $stmt = $conn->prepare("UPDATE cart_items SET quantity = ? WHERE item_id = ? AND cart_id IN (SELECT cart_id FROM customer_cart WHERE customer_id = ?)");
    $stmt->bind_param("iii", $newQty, $itemId, $customerId);
    if ($stmt->execute()) {
        $message = "Quantity updated successfully";
        $messageType = "success";
    }
    $stmt->close();
}

// Handle item removal
if (isset($_GET['remove'])) {
    $itemId = (int)$_GET['remove'];
    $stmt = $conn->prepare("DELETE FROM cart_items WHERE item_id = ? AND cart_id IN (SELECT cart_id FROM customer_cart WHERE customer_id = ?)");
    $stmt->bind_param("ii", $itemId, $customerId);
    if ($stmt->execute()) {
        $message = "Item removed from cart";
        $messageType = "success";
    }
    $stmt->close();
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

// Fetch cart items grouped by kitchen
$cartItemsByKitchen = [
    'khans' => [],
    'olympia' => [],
    'neptune' => []
];

if ($cartId) {
    $stmt = $conn->prepare("
        SELECT item_id, product_code, product_name, kitchen, price, quantity 
        FROM cart_items 
        WHERE cart_id = ? 
        ORDER BY kitchen, product_name
    ");
    $stmt->bind_param("i", $cartId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    while ($row = $result->fetch_assoc()) {
        // Get product image from respective kitchen table
        $image = '';
        $kitchenTable = $row['kitchen'];
        $productCode = $row['product_code'];
        
        $imgStmt = $conn->prepare("SELECT image FROM $kitchenTable WHERE product_code = ? LIMIT 1");
        $imgStmt->bind_param("s", $productCode);
        $imgStmt->execute();
        $imgResult = $imgStmt->get_result();
        if ($imgRow = $imgResult->fetch_assoc()) {
            $image = $imgRow['image'];
        }
        $imgStmt->close();
        
        $row['image'] = $image;
        $cartItemsByKitchen[$row['kitchen']][] = $row;
    }
    $stmt->close();
}

// Calculate totals for each kitchen
$kitchenTotals = [];
$grandTotal = 0;
foreach ($cartItemsByKitchen as $kitchen => $items) {
    $total = 0;
    foreach ($items as $item) {
        $total += $item['price'] * $item['quantity'];
    }
    $kitchenTotals[$kitchen] = $total;
    $grandTotal += $total;
}

// Remove empty kitchens
$cartItemsByKitchen = array_filter($cartItemsByKitchen, function($items) {
    return count($items) > 0;
});

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
    <title>Shopping Cart - Food Wave</title>
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
            background: rgba(255, 255, 255, 0.7);
            z-index: -1;
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
        
        .nav-buttons { display: flex; align-items: center; gap: 0.75rem; }
        .nav-link { padding: 0.5rem 0.9rem; border-radius: 0.5rem; font-weight: 600; color: #111827; }
        .nav-link:hover { background-color: rgba(248,113,113,0.12); color: #b91c1c; }
        .nav-dropdown { position: relative; }
        .nav-dropdown-menu { 
            position: absolute; left: 0; margin-top: 0.4rem; width: 12rem; 
            background: #fff; border: 1px solid #e5e7eb; border-radius: 0.5rem; 
            box-shadow: 0 10px 25px -10px rgba(0,0,0,0.15); 
            opacity: 0; transform: translateY(-4px) scale(0.98); 
            transition: all 0.15s ease; pointer-events: none; 
        }
        .nav-dropdown:hover .nav-dropdown-menu { 
            opacity: 1; transform: translateY(0) scale(1); pointer-events: auto; 
        }
        .nav-dropdown-menu a { 
            display: block; padding: 0.55rem 0.9rem; color: #111827; border-radius: 0.5rem; 
        }
        .nav-dropdown-menu a:hover { background: #f3f4f6; }
        
        .initials-circle { 
            width: 42px; height: 42px; border-radius: 9999px; 
            background: linear-gradient(135deg,#ef4444,#7f1d1d); 
            display: inline-flex; align-items: center; justify-content: center; 
            color: #fff; font-weight: 700; 
        }
        
        .profile-menu.show { display: block; }
        
        .kitchen-badge {
            display: inline-block;
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 700;
            text-transform: uppercase;
        }
        
        .badge-khans { background: #dbeafe; color: #1e40af; }
        .badge-olympia { background: #dcfce7; color: #166534; }
        .badge-neptune { background: #fef3c7; color: #92400e; }
        
        .cart-item-khans { background: linear-gradient(135deg, #eff6ff 0%, #dbeafe 100%); border-left: 4px solid #3b82f6; }
        .cart-item-olympia { background: linear-gradient(135deg, #f0fdf4 0%, #dcfce7 100%); border-left: 4px solid #10b981; }
        .cart-item-neptune { background: linear-gradient(135deg, #fffbeb 0%, #fef3c7 100%); border-left: 4px solid #f59e0b; }
        
        .quantity-btn {
            width: 32px;
            height: 32px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 0.375rem;
            font-weight: 600;
            transition: all 0.2s;
        }
        
        .quantity-btn:hover {
            transform: scale(1.1);
        }
        
        .cart-item {
            transition: all 0.3s ease;
        }
        
        .cart-item:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px -5px rgba(0,0,0,0.1);
        }
    </style>
</head>

<body class="min-h-screen">
    <!-- Header/Navbar -->
    <header class="sticky top-0 z-40 bg-white/95 backdrop-blur border-b border-gray-200">
        <div class="max-w-6xl mx-auto px-4 h-16 flex items-center justify-between gap-6">
            <div class="food-wave">Food Wave</div>
            
            <nav class="nav-buttons text-sm">
                <a href="./navbar.php" class="nav-link">Home</a>
                <div class="nav-dropdown">
                    <button class="nav-link">
                        Shop <i class="fas fa-chevron-down ml-1 text-xs"></i>
                    </button>
                    <div class="nav-dropdown-menu">
                        <a href="./khans.php">Khans Kitchen</a>
                        <a href="./olympia.php">Olympia Kitchen</a>
                        <a href="./neptune.php">Neptune Kitchen</a>
                    </div>
                </div>
                <a href="./invoice.php" class="nav-link">Invoice</a>
                <a href="cart.php" class="nav-link">Cart</a>
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
    <main class="max-w-7xl mx-auto px-4 py-8">
        <div class="mb-6">
            <h1 class="text-3xl font-bold text-gray-800 flex items-center gap-3">
                <i class="fas fa-shopping-cart text-red-600"></i>
                Shopping Cart
            </h1>
            <p class="text-gray-600 mt-2">Review your items before checkout</p>
        </div>

        <?php if ($message): ?>
            <div class="mb-6 p-4 rounded-lg <?= $messageType === 'success' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' ?>">
                <i class="fas fa-<?= $messageType === 'success' ? 'check-circle' : 'exclamation-circle' ?> mr-2"></i>
                <?= htmlspecialchars($message) ?>
            </div>
        <?php endif; ?>

        <?php if (empty($cartItemsByKitchen)): ?>
            <!-- Empty Cart -->
            <div class="bg-white rounded-2xl shadow-xl p-12 text-center border-4 border-red-200">
                <i class="fas fa-shopping-cart text-red-300 text-6xl mb-4"></i>
                <h2 class="text-2xl font-bold text-gray-800 mb-2">Your cart is empty</h2>
                <p class="text-gray-600 mb-6">Add some delicious items from our kitchens!</p>
                <div class="flex gap-4 justify-center flex-wrap">
                    <a href="./khans.php" class="px-6 py-3 bg-gradient-to-r from-blue-500 to-blue-600 text-white rounded-lg hover:from-blue-600 hover:to-blue-700 transition shadow-lg">
                        <i class="fas fa-utensils mr-2"></i>Khans Kitchen
                    </a>
                    <a href="./olympia.php" class="px-6 py-3 bg-gradient-to-r from-green-500 to-green-600 text-white rounded-lg hover:from-green-600 hover:to-green-700 transition shadow-lg">
                        <i class="fas fa-utensils mr-2"></i>Olympia Kitchen
                    </a>
                    <a href="./neptune.php" class="px-6 py-3 bg-gradient-to-r from-yellow-500 to-yellow-600 text-white rounded-lg hover:from-yellow-600 hover:to-yellow-700 transition shadow-lg">
                        <i class="fas fa-utensils mr-2"></i>Neptune Kitchen
                    </a>
                </div>
            </div>
        <?php else: ?>
            <!-- Cart Items by Kitchen -->
            <div class="grid lg:grid-cols-3 gap-6">
                <div class="lg:col-span-2 space-y-6">
                    <?php foreach ($cartItemsByKitchen as $kitchen => $items): ?>
                        <div class="bg-white rounded-2xl shadow-xl overflow-hidden">
                            <!-- Kitchen Header -->
                            <div class="bg-gradient-to-r <?php 
                                echo $kitchen === 'khans' ? 'from-blue-500 to-blue-600' : 
                                     ($kitchen === 'olympia' ? 'from-green-500 to-green-600' : 'from-yellow-500 to-yellow-600'); 
                            ?> p-4">
                                <h2 class="text-xl font-bold text-white flex items-center gap-2">
                                    <i class="fas fa-store"></i>
                                    <?= ucfirst($kitchen) ?> Kitchen
                                    <span class="ml-auto text-sm font-normal"><?= count($items) ?> item(s)</span>
                                </h2>
                            </div>
                            
                            <!-- Items -->
                            <div class="p-4 space-y-4">
                                <?php foreach ($items as $item): ?>
                                    <div class="cart-item cart-item-<?= $kitchen ?> rounded-xl p-4 flex gap-4">
                                        <!-- Product Image -->
                                        <div class="flex-shrink-0">
                                            <?php if ($item['image']): ?>
                                                <img src="../resources/<?= ucfirst($kitchen) ?>/<?= htmlspecialchars($item['image']) ?>" 
                                                     alt="<?= htmlspecialchars($item['product_name']) ?>" 
                                                     class="w-24 h-24 object-cover rounded-lg shadow-md">
                                            <?php else: ?>
                                                <div class="w-24 h-24 bg-gray-300 rounded-lg flex items-center justify-center">
                                                    <i class="fas fa-utensils text-gray-500 text-2xl"></i>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                        
                                        <!-- Product Details -->
                                        <div class="flex-grow">
                                            <div class="flex justify-between items-start mb-2">
                                                <div>
                                                    <h3 class="font-bold text-gray-800 text-lg"><?= htmlspecialchars($item['product_name']) ?></h3>
                                                    <span class="kitchen-badge badge-<?= $kitchen ?>"><?= ucfirst($kitchen) ?></span>
                                                </div>
                                                <a href="?remove=<?= $item['item_id'] ?>" 
                                                   class="text-red-600 hover:text-red-800 transition"
                                                   onclick="return confirm('Remove this item from cart?')">
                                                    <i class="fas fa-trash"></i>
                                                </a>
                                            </div>
                                            
                                            <div class="flex items-center justify-between">
                                                <div class="flex items-center gap-3">
                                                    <span class="text-gray-600 font-semibold">৳<?= number_format($item['price'], 2) ?></span>
                                                    
                                                    <!-- Quantity Controls -->
                                                    <form method="POST" class="flex items-center gap-2" onsubmit="return confirmUpdate()">
                                                        <input type="hidden" name="item_id" value="<?= $item['item_id'] ?>">
                                                        <button type="button" onclick="decreaseQty(this)" 
                                                                class="quantity-btn bg-red-100 text-red-600 hover:bg-red-200">
                                                            <i class="fas fa-minus"></i>
                                                        </button>
                                                        <input type="number" name="quantity" 
                                                               value="<?= $item['quantity'] ?>" 
                                                               min="1" max="99"
                                                               class="w-16 text-center border-2 border-gray-300 rounded-lg px-2 py-1 font-semibold">
                                                        <button type="button" onclick="increaseQty(this)" 
                                                                class="quantity-btn bg-green-100 text-green-600 hover:bg-green-200">
                                                            <i class="fas fa-plus"></i>
                                                        </button>
                                                        <button type="submit" name="update_quantity" 
                                                                class="ml-2 px-3 py-1 bg-blue-600 text-white text-sm rounded-lg hover:bg-blue-700 transition">
                                                            <i class="fas fa-check"></i> Update
                                                        </button>
                                                    </form>
                                                </div>
                                                
                                                <div class="text-right">
                                                    <p class="text-sm text-gray-600">Subtotal</p>
                                                    <p class="text-lg font-bold text-gray-800">৳<?= number_format($item['price'] * $item['quantity'], 2) ?></p>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                            
                            <!-- Kitchen Total -->
                            <div class="bg-gray-100 p-4 border-t-2 <?php 
                                echo $kitchen === 'khans' ? 'border-blue-500' : 
                                     ($kitchen === 'olympia' ? 'border-green-500' : 'border-yellow-500'); 
                            ?>">
                                <div class="flex justify-between items-center">
                                    <span class="font-semibold text-gray-700"><?= ucfirst($kitchen) ?> Total:</span>
                                    <span class="text-xl font-bold text-gray-800">৳<?= number_format($kitchenTotals[$kitchen], 2) ?></span>
                                </div>
                                <form action="checkout.php" method="POST" class="mt-3">
                                    <input type="hidden" name="kitchen" value="<?= $kitchen ?>">
                                    <button type="submit" class="w-full py-3 bg-gradient-to-r <?php 
                                        echo $kitchen === 'khans' ? 'from-blue-600 to-blue-700' : 
                                             ($kitchen === 'olympia' ? 'from-green-600 to-green-700' : 'from-yellow-600 to-yellow-700'); 
                                    ?> text-white font-semibold rounded-lg hover:shadow-lg transition">
                                        <i class="fas fa-credit-card mr-2"></i>Checkout <?= ucfirst($kitchen) ?> Items
                                    </button>
                                </form>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                
                <!-- Cart Summary Sidebar -->
                <div class="lg:col-span-1">
                    <div class="bg-gradient-to-br from-white to-gray-50 rounded-2xl shadow-xl p-6 sticky top-24 border-2 border-red-200">
                        <h2 class="text-xl font-bold text-gray-800 mb-4 flex items-center gap-2">
                            <i class="fas fa-receipt text-red-600"></i>
                            Order Summary
                        </h2>
                        
                        <div class="space-y-3 mb-6">
                            <?php foreach ($kitchenTotals as $kitchen => $total): ?>
                                <?php if ($total > 0): ?>
                                    <div class="flex justify-between items-center pb-2 border-b border-gray-200">
                                        <span class="kitchen-badge badge-<?= $kitchen ?>"><?= ucfirst($kitchen) ?></span>
                                        <span class="font-semibold text-gray-700">৳<?= number_format($total, 2) ?></span>
                                    </div>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </div>
                        
                        <div class="border-t-2 border-gray-300 pt-4 mb-6">
                            <div class="flex justify-between items-center">
                                <span class="text-lg font-bold text-gray-800">Grand Total:</span>
                                <span class="text-2xl font-bold text-red-600">৳<?= number_format($grandTotal, 2) ?></span>
                            </div>
                        </div>
                        
                        <div class="bg-blue-50 rounded-lg p-4 mb-4">
                            <p class="text-sm text-blue-800">
                                <i class="fas fa-info-circle mr-2"></i>
                                <strong>Note:</strong> Items from different kitchens require separate checkouts.
                            </p>
                        </div>
                        
                        <div class="space-y-2">
                            <a href="./navbar.php" class="block w-full py-3 bg-gradient-to-r from-gray-600 to-gray-700 text-white text-center font-semibold rounded-lg hover:from-gray-700 hover:to-gray-800 transition shadow-lg">
                                <i class="fas fa-arrow-left mr-2"></i>Continue Shopping
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </main>

    <script>
        // Profile menu toggle
        document.getElementById('profileBtn').addEventListener('click', function(e) {
            e.stopPropagation();
            document.getElementById('profileMenu').classList.toggle('show');
        });
        
        document.addEventListener('click', function() {
            document.getElementById('profileMenu').classList.remove('show');
        });
        
        // Quantity controls
        function decreaseQty(btn) {
            const input = btn.parentElement.querySelector('input[name="quantity"]');
            const currentVal = parseInt(input.value) || 1;
            if (currentVal > 1) {
                input.value = currentVal - 1;
            }
        }
        
        function increaseQty(btn) {
            const input = btn.parentElement.querySelector('input[name="quantity"]');
            const currentVal = parseInt(input.value) || 1;
            if (currentVal < 99) {
                input.value = currentVal + 1;
            }
        }
        
        function confirmUpdate() {
            return confirm('Update quantity for this item?');
        }
    </script>
</body>
</html>
