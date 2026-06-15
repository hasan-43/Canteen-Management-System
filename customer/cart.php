<?php
session_start();
require_once __DIR__ . '/../config/db_connection.php';
require_once __DIR__ . '/../config/hd_helper.php';

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

function fw_get_kitchen_theme($kitchen) {
    $themes = [
        'khans' => [
            'badge' => 'background: #dbeafe; color: #1e40af;',
            'item_bg' => 'background: linear-gradient(135deg, #eff6ff 0%, #dbeafe 100%); border-left: 4px solid #3b82f6;',
            'header_gradient' => 'from-blue-500 to-blue-600',
            'border_class' => 'border-blue-500',
            'checkout_gradient' => 'from-blue-600 to-blue-700'
        ],
        'olympia' => [
            'badge' => 'background: #dcfce7; color: #166534;',
            'item_bg' => 'background: linear-gradient(135deg, #f0fdf4 0%, #dcfce7 100%); border-left: 4px solid #10b981;',
            'header_gradient' => 'from-green-500 to-green-600',
            'border_class' => 'border-green-500',
            'checkout_gradient' => 'from-green-600 to-green-700'
        ],
        'neptune' => [
            'badge' => 'background: #fef3c7; color: #92400e;',
            'item_bg' => 'background: linear-gradient(135deg, #fffbeb 0%, #fef3c7 100%); border-left: 4px solid #f59e0b;',
            'header_gradient' => 'from-yellow-500 to-yellow-600',
            'border_class' => 'border-yellow-500',
            'checkout_gradient' => 'from-yellow-600 to-yellow-700'
        ]
    ];
    
    $kitchen_lower = strtolower($kitchen);
    if (isset($themes[$kitchen_lower])) {
        return $themes[$kitchen_lower];
    }
    
    return [
        'badge' => 'background: #f3e8ff; color: #6b21a8;',
        'item_bg' => 'background: linear-gradient(135deg, #faf5ff 0%, #f3e8ff 100%); border-left: 4px solid #a855f7;',
        'header_gradient' => 'from-purple-500 to-purple-600',
        'border_class' => 'border-purple-500',
        'checkout_gradient' => 'from-purple-600 to-purple-700'
    ];
}

// Fetch all shops dynamically
$shops = [];
$allowedTables = [];
$cartItemsByKitchen = [];
$shopResult = $conn->query("SELECT shop_id, shop_name FROM shop ORDER BY shop_id");
if ($shopResult) {
    while ($row = $shopResult->fetch_assoc()) {
        $shopName = $row['shop_name'];
        $shops[] = [
            'name'         => $shopName,
            'display_name' => ucfirst($shopName) . ' Kitchen'
        ];
        $allowedTables[] = $shopName;
        $cartItemsByKitchen[$shopName] = [];
    }
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
    $conn->begin_transaction();

    $stmt = $conn->prepare("SELECT product_code, kitchen, quantity FROM cart_items WHERE item_id = ? AND cart_id IN (SELECT cart_id FROM customer_cart WHERE customer_id = ?) FOR UPDATE");
    $stmt->bind_param("ii", $itemId, $customerId);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $stmt->close();

    if ($row) {
        $kitchen = $row['kitchen'];
        $productCode = $row['product_code'];
        $qty = (int)$row['quantity'];

        // only allow known kitchen tables
        if (in_array($kitchen, $allowedTables, true)) {
            $update = $conn->prepare("UPDATE `{$kitchen}` SET stock = stock + ? WHERE product_code = ?");
            $update->bind_param("is", $qty, $productCode);
            $update->execute();
            $update->close();
        }

        $delete = $conn->prepare("DELETE FROM cart_items WHERE item_id = ? AND cart_id IN (SELECT cart_id FROM customer_cart WHERE customer_id = ?)");
        $delete->bind_param("ii", $itemId, $customerId);
        $delete->execute();
        $delete->close();

        $conn->commit();
        $message = "Item removed from cart";
        $messageType = "success";
    } else {
        $conn->rollback();
    }

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
    <script src="../resources/js/theme.js"></script>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Shopping Cart - Campus Cravings</title>
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
        .nav-dropdown { position: relative; }
        .nav-dropdown-menu { 
            position: absolute; left: 0; margin-top: 0.4rem; width: 12rem; 
            background: rgba(0, 0, 0, 0.9); border: 1px solid #374151; border-radius: 0.5rem; 
            box-shadow: 0 10px 25px -10px rgba(0,0,0,0.5); 
            opacity: 0; transform: translateY(-4px) scale(0.98); 
            transition: all 0.15s ease; pointer-events: none; 
        }
        .nav-dropdown:hover .nav-dropdown-menu { 
            opacity: 1; transform: translateY(0) scale(1); pointer-events: auto; 
        }
        .nav-dropdown-menu a { 
            display: block; padding: 0.55rem 0.9rem; color: #ffffff; border-radius: 0.5rem; 
        }
        .nav-dropdown-menu a:hover { background: #374151; }
        
        header { position: fixed; top: 0; left: 0; right: 0; z-index: 50; background: rgba(10, 10, 12, 0.9) !important; backdrop-filter: blur(12px) !important; -webkit-backdrop-filter: blur(12px) !important; border-bottom: 1px solid rgba(255, 255, 255, 0.1) !important; }
        .logo-section { position: absolute; left: 2rem; top: 50%; transform: translateY(-50%); }
        .logo-section a { display: flex; align-items: center; gap: 0.75rem; text-decoration: none; }
        .brand-text { font-size: 1.25rem; font-weight: 800; color: #ffffff; letter-spacing: 0.05em; transition: color 0.3s ease; }
        .logo-section a:hover .brand-text { color: #ef4444; }
        .profile-section { position: absolute; right: 2rem; top: 50%; transform: translateY(-50%); }
        @media (min-width: 768px) { .nav-buttons { display: flex; position: absolute; left: 50%; top: 50%; transform: translate(-50%, -50%); gap: 1rem; } }
        
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

<body class="bg-gray-900 text-white min-h-screen">
    <!-- Header/Navbar -->
    <header class="h-16">
        <div class="relative h-full max-w-7xl mx-auto px-4">
            <!-- Logo left -->
            <div class="logo-section">
                <a href="./navbar.php">
                    <img src="../resources/logo.jpg" alt="Campus Cravings" class="campus-cravings-logo" />
                    <span class="brand-text">Campus Cravings</span>
                </a>
            </div>
            
            <!-- Nav buttons center -->
            <nav class="nav-buttons">
                <a href="./navbar.php" class="px-4 py-2 bg-transparent hover:bg-red-600 hover:bg-opacity-80 rounded text-sm text-white">Home</a>
                <div class="relative group">
                    <button class="px-4 py-2 rounded text-sm hover:bg-red-600 hover:bg-opacity-80 text-white">Shop</button>
                    <div class="absolute left-0 mt-2 w-48 bg-black bg-opacity-90 border border-gray-800 rounded shadow-lg opacity-0 group-hover:opacity-100 transform scale-95 group-hover:scale-100 transition-all origin-top">
                        <?php foreach ($shops as $shop): 
                            $isLegacy = in_array($shop['name'], ['khans', 'olympia', 'neptune']);
                            $shopUrl = $isLegacy ? "./" . htmlspecialchars($shop['name']) . ".php" : "./shop.php?name=" . urlencode($shop['name']);
                        ?>
                            <a href="<?= $shopUrl ?>" class="block px-4 py-2 hover:bg-gray-700 text-white"><?= htmlspecialchars($shop['display_name']) ?></a>
                        <?php endforeach; ?>
                    </div>
                </div>
                <a href="./invoice.php" class="px-4 py-2 rounded text-sm hover:bg-red-600 hover:bg-opacity-80 text-white">Invoice</a>
                <a href="./chat.php" class="px-4 py-2 rounded text-sm hover:bg-red-600 hover:bg-opacity-80 text-white">
                    <i class="fas fa-comments mr-1"></i> Chat
                </a>
                <a href="cart.php" class="px-4 py-2 rounded text-sm hover:bg-red-600 hover:bg-opacity-80 text-white">Cart</a>
            </nav>
            
            <!-- Profile right -->
            <div class="profile-section">
                <div class="relative" id="profileRoot">
                    <button id="profileBtn" class="flex items-center gap-2 p-1 rounded focus:outline-none hover:opacity-80 transition">
                        <?php if ($profilePic): ?>
                            <img src="<?= htmlspecialchars($profilePic) ?>" alt="Profile" class="w-10 h-10 rounded-full object-cover border-2 border-red-600">
                        <?php else: ?>
                            <div class="initials-circle"><?= htmlspecialchars($initials_text) ?></div>
                        <?php endif; ?>
                        <span class="hidden sm:inline-block text-sm text-white"><?= htmlspecialchars($displayName) ?></span>
                    </button>
                    <div id="profileMenu" class="profile-menu hidden absolute right-0 mt-2 w-48 bg-gray-900 bg-opacity-95 rounded-lg border border-gray-800 z-50">
                        <a href="./profile.php" class="block px-4 py-2 text-gray-100 hover:bg-gray-800">Profile</a>
                        <a href="./logout.php" class="block px-4 py-2 text-red-200 hover:bg-gray-800">Logout</a>
                    </div>
                </div>
            </div>
        </div>
    </header>

    <!-- Main Content -->
    <main class="max-w-7xl mx-auto px-4 py-8 mt-16">
        <div class="mb-6">
            <h1 class="text-3xl font-bold text-white flex items-center gap-3">
                <i class="fas fa-shopping-cart text-red-600"></i>
                Shopping Cart
            </h1>
            <p class="text-gray-300 mt-2">Review your items before checkout</p>
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
                    <?php foreach ($shops as $index => $shop): 
                        $colors = ['from-blue-500 to-blue-600 hover:from-blue-600 hover:to-blue-700', 'from-green-500 to-green-600 hover:from-green-600 hover:to-green-700', 'from-yellow-500 to-yellow-600 hover:from-yellow-600 hover:to-yellow-700', 'from-purple-500 to-purple-600 hover:from-purple-600 hover:to-purple-700'];
                        $btnColor = $colors[$index % count($colors)];
                        $isLegacy = in_array($shop['name'], ['khans', 'olympia', 'neptune']);
                        $shopUrl = $isLegacy ? "./" . htmlspecialchars($shop['name']) . ".php" : "./shop.php?name=" . urlencode($shop['name']);
                    ?>
                        <a href="<?= $shopUrl ?>" class="px-6 py-3 bg-gradient-to-r <?= $btnColor ?> text-white rounded-lg transition shadow-lg">
                            <i class="fas fa-utensils mr-2"></i><?= htmlspecialchars($shop['display_name']) ?>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php else: ?>
            <!-- Cart Items by Kitchen -->
            <div class="grid lg:grid-cols-3 gap-6">
                <div class="lg:col-span-2 space-y-6">
                    <?php foreach ($cartItemsByKitchen as $kitchen => $items): ?>
                        <div class="bg-white rounded-2xl shadow-xl overflow-hidden">
                            <!-- Kitchen Header -->
                            <?php $theme = fw_get_kitchen_theme($kitchen); ?>
                            <div class="bg-gradient-to-r <?= $theme['header_gradient'] ?> p-4">
                                <h2 class="text-xl font-bold text-white flex items-center gap-2">
                                    <i class="fas fa-store"></i>
                                    <?= ucfirst($kitchen) ?> Kitchen
                                    <span class="ml-auto text-sm font-normal"><?= count($items) ?> item(s)</span>
                                </h2>
                            </div>
                            
                            <!-- Items -->
                            <div class="p-4 space-y-4">
                                <?php foreach ($items as $item): 
                                    $theme = fw_get_kitchen_theme($kitchen);
                                ?>
                                    <div class="cart-item rounded-xl p-4 flex gap-4" style="<?= $theme['item_bg'] ?>">
                                        <!-- Product Image -->
                                        <div class="flex-shrink-0">
                                            <img src="<?= getHDProductImage($item['product_name'], $kitchen, $item['image']) ?>" 
                                                 alt="<?= htmlspecialchars($item['product_name']) ?>" 
                                                 class="w-24 h-24 object-cover rounded-lg shadow-md">
                                        </div>
                                        
                                        <!-- Product Details -->
                                        <div class="flex-grow">
                                            <div class="flex justify-between items-start mb-2">
                                                <div>
                                                    <h3 class="font-bold text-gray-800 text-lg"><?= htmlspecialchars($item['product_name']) ?></h3>
                                                    <span class="kitchen-badge" style="<?= $theme['badge'] ?>"><?= ucfirst($kitchen) ?></span>
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
                                                               class="w-16 text-center border-2 border-gray-300 rounded-lg px-2 py-1 font-semibold text-gray-800 bg-white">
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
                            <div class="bg-gray-100 p-4 border-t-2 <?= $theme['border_class'] ?>">
                                <div class="flex justify-between items-center">
                                    <span class="font-semibold text-gray-700"><?= ucfirst($kitchen) ?> Total:</span>
                                    <span class="text-xl font-bold text-gray-800">৳<?= number_format($kitchenTotals[$kitchen], 2) ?></span>
                                </div>
                                <form action="checkout.php" method="POST" class="mt-3">
                                    <input type="hidden" name="kitchen" value="<?= $kitchen ?>">
                                    <button type="submit" class="w-full py-3 bg-gradient-to-r <?= $theme['checkout_gradient'] ?> text-white font-semibold rounded-lg hover:shadow-lg transition">
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
                                <?php if ($total > 0): 
                                    $theme = fw_get_kitchen_theme($kitchen);
                                ?>
                                    <div class="flex justify-between items-center pb-2 border-b border-gray-200">
                                        <span class="kitchen-badge" style="<?= $theme['badge'] ?>"><?= ucfirst($kitchen) ?></span>
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
