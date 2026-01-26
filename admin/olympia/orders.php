<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
session_start();
require_once __DIR__ . '/../../config/db_connection.php';

// Session guard
if (!isset($_SESSION['usertype']) || $_SESSION['usertype'] !== 'admin' || ($_SESSION['shop'] ?? '') !== 'Olympia Kitchen') {
    header('Location: ../../login.php');
    exit;
}

// Profile display data
$displayName = $_SESSION['username'] ?? 'Admin';
$username = $_SESSION['username'] ?? null;
$profileImage = $_SESSION['profile_image'] ?? null;
if (!$profileImage && $username) {
    $picDir = __DIR__ . '/../../resources/ProfilePics';
    if (is_dir($picDir)) {
        foreach (['jpg', 'jpeg', 'png', 'webp'] as $ext) {
            $candidateFs = $picDir . '/' . $username . '.' . $ext;
            if (file_exists($candidateFs)) {
                $profileImage = '../../resources/ProfilePics/' . $username . '.' . $ext;
                break;
            }
        }
    }
}

function initials($name) {
    $parts = preg_split('/\s+/', trim($name));
    $letters = '';
    foreach ($parts as $p) {
        if ($p !== '') {
            $letters .= strtoupper(substr($p, 0, 1));
        }
        if (strlen($letters) >= 2) {
            break;
        }
    }
    return $letters ?: 'A';
}
$initials = initials($displayName);

// Handle status update
$message = '';
$messageType = 'info';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    $order_id = intval($_POST['order_id'] ?? 0);
    $new_status = trim($_POST['order_status'] ?? '');
    
    if ($order_id > 0 && !empty($new_status)) {
        $stmt = $conn->prepare("UPDATE orders SET order_status = ? WHERE order_id = ? AND kitchen = 'olympia'");
        if ($stmt) {
            $stmt->bind_param("si", $new_status, $order_id);
            if ($stmt->execute()) {
                $message = "✓ Order status updated successfully!";
                $messageType = 'success';
            } else {
                $message = "✗ Error updating status: " . $stmt->error;
                $messageType = 'error';
            }
            $stmt->close();
        }
    }
}

// Fetch all orders for Olympia Kitchen with customer details
$orders = [];
$sql = "SELECT o.*, c.username, c.fullname, c.email, c.phone AS customer_phone, c.address AS customer_address 
    FROM orders o 
    LEFT JOIN customer c ON o.customer_id = c.customer_id 
    WHERE LOWER(o.kitchen) = 'olympia' AND o.order_status <> 'delivered' 
    ORDER BY o.order_date DESC";
$result = $conn->query($sql);
if (!$result) {
    error_log("Order query error: " . $conn->error);
}
if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        // Fetch order items for each order
        $order_id = $row['order_id'];
        $items_sql = "SELECT * FROM order_items WHERE order_id = ?";
        $items_stmt = $conn->prepare($items_sql);
        $items_stmt->bind_param("i", $order_id);
        $items_stmt->execute();
        $items_result = $items_stmt->get_result();
        
        $items = [];
        while ($item = $items_result->fetch_assoc()) {
            $items[] = $item;
        }
        $items_stmt->close();
        
        $row['items'] = $items;
        $orders[] = $row;
    }
}

?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width,initial-scale=1" />
    <title>Olympia Kitchen - Order Management</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        header { 
            position: fixed; 
            top: 0; 
            left: 0; 
            right: 0; 
            z-index: 50; 
            background: rgba(0, 0, 0, 0.7); 
            backdrop-filter: blur(10px); 
        }
        .logo-section { 
            position: absolute; 
            left: 2rem; 
            top: 50%; 
            transform: translateY(-50%); 
        }
        .profile-section { 
            position: absolute; 
            right: 2rem; 
            top: 50%; 
            transform: translateY(-50%); 
        }
        .nav-buttons { 
            display: none; 
        }
        @media (min-width: 768px) {
            .nav-buttons { 
                display: flex; 
                position: absolute; 
                left: 50%; 
                top: 50%; 
                transform: translate(-50%, -50%); 
                gap: 1rem; 
            }
        }
        .food-wave { 
            font-weight: 900; 
            letter-spacing: 3px; 
            font-size: 2.4rem; 
            background: linear-gradient(90deg, #ff0000); 
            -webkit-background-clip: text; 
            background-clip: text; 
            -webkit-text-fill-color: transparent; 
            display: inline-block; 
        }
        .initials-circle { 
            width: 42px; 
            height: 42px; 
            border-radius:9999px; 
            background: linear-gradient(135deg,#ef4444,#7f1d1d); 
            display:inline-flex; 
            align-items:center; 
            justify-content:center; 
            color:#fff; 
            font-weight:700; 
        }
        .avatar-img { 
            width: 42px; 
            height: 42px; 
            border-radius: 9999px; 
            object-fit: cover; 
        }
        .profile-menu.show { 
            display:block; 
        }
        
        .hero {
            background-image: url('../../resources/sign_up.jpg');
            background-size: cover;
            background-position: center;
            background-attachment: fixed;
            min-height: 60vh;
            position: relative;
            overflow: hidden;
            margin-top: 4rem;
        }
        .hero::before {
            content: '';
            position: absolute;
            top: 0; 
            left: 0; 
            width: 100%; 
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            z-index: 1;
        }
        .hero-overlay { 
            position: absolute; 
            inset: 0; 
            background: transparent; 
            z-index: 1; 
        }

        .message-box { 
            padding: 1rem; 
            border-radius: 0.5rem; 
            margin-bottom: 1.5rem; 
        }
        .success { 
            background: #d1fae5; 
            color: #065f46; 
            border: 1px solid #a7f3d0; 
        }
        .error { 
            background: #fee2e2; 
            color: #7f1d1d; 
            border: 1px solid #fca5a5; 
        }
        .info { 
            background: #dbeafe; 
            color: #1e3a8a; 
            border: 1px solid #93c5fd; 
        }
        .profile-menu { 
            position: absolute; 
            right: 0; 
            margin-top: 0.5rem; 
            width: 11rem; 
            background: #000; 
            background-color: rgba(0,0,0,0.95); 
            border: 1px solid #374151; 
            border-radius: 0.5rem; 
            box-shadow: 0 10px 25px -5px rgba(0,0,0,0.2); 
        }
        .profile-menu a { 
            display: block; 
            padding: 0.5rem 1rem; 
            color: #fff; 
            border-radius: 0.5rem; 
        }
        .profile-menu a:hover { 
            background: #4b5563; 
        }
        .profile-menu a.logout { 
            color: #ef4444; 
        }
        .order-card {
            background: white;
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
            margin-bottom: 1.5rem;
            overflow: hidden;
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }
        .order-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 8px 30px rgba(0,0,0,0.12);
        }
        .order-header {
            background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
            padding: 1.25rem;
            color: white;
        }
        .order-body {
            padding: 1.5rem;
        }
        .status-badge {
            display: inline-block;
            padding: 0.4rem 1rem;
            border-radius: 20px;
            font-size: 0.875rem;
            font-weight: 600;
            text-transform: capitalize;
        }
        .status-pending { background: #fef3c7; color: #92400e; }
        .status-confirmed { background: #dbeafe; color: #1e40af; }
        .status-preparing { background: #e0e7ff; color: #3730a3; }
        .status-out_for_delivery { background: #fce7f3; color: #9f1239; }
        .status-delivered { background: #d1fae5; color: #065f46; }
        .status-cancelled { background: #fee2e2; color: #7f1d1d; }
        .item-row {
            display: flex;
            justify-content: space-between;
            padding: 0.75rem 0;
            border-bottom: 1px solid #f3f4f6;
        }
        .item-row:last-child {
            border-bottom: none;
        }
        .btn {
            padding: 0.5rem 1rem;
            border-radius: 0.375rem;
            font-weight: 600;
            cursor: pointer;
            border: none;
            transition: all 0.2s ease;
        }
        .btn-primary {
            background: #ef4444;
            color: white;
        }
        .btn-primary:hover {
            background: #dc2626;
        }
        .filter-btn {
            padding: 0.6rem 1.2rem;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            border: 2px solid #e5e7eb;
            background: white;
            transition: all 0.3s ease;
        }
        .filter-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        }
        .filter-btn.active {
            background: #ef4444;
            color: white;
            border-color: #ef4444;
        }
    </style>
</head>
<body class="bg-gray-50 text-gray-900">
    
    <header class="h-16 flex items-center">
        <div class="relative h-full w-full max-w-7xl mx-auto px-4">
            <div class="logo-section">
                <h2 class="food-wave">Food Wave</h2>
            </div>

            <nav class="nav-buttons">
                <a href="./navbar.php" class="px-4 py-2 bg-transparent hover:bg-red-600 hover:bg-opacity-80 rounded text-sm">Home</a>
                <a href="./statistics.php" class="px-4 py-2 rounded text-sm hover:bg-red-600 hover:bg-opacity-80">Statistics</a>
                <a href="./product.php" class="px-4 py-2 rounded text-sm hover:bg-red-600 hover:bg-opacity-80">Product</a>
                <a href="./invoice.php" class="px-4 py-2 rounded text-sm hover:bg-red-600 hover:bg-opacity-80">Invoice</a>
                <a href="./orders.php" class="px-4 py-2 rounded text-sm bg-red-600 bg-opacity-80">Order</a>
                <a href="../chat/index.php" class="px-4 py-2 rounded text-sm hover:bg-red-600 hover:bg-opacity-80">Chat</a>
            </nav>

            <div class="profile-section">
                <div class="relative" id="profileRoot">
                    <button id="profileBtn" class="flex items-center gap-2 p-1 rounded focus:outline-none">
                        <?php if (!empty($profileImage)): ?>
                            <img src="<?= htmlspecialchars($profileImage) ?>" alt="Profile" class="avatar-img" />
                        <?php else: ?>
                            <div class="initials-circle"><?= htmlspecialchars($initials) ?></div>
                        <?php endif; ?>
                        <span class="hidden sm:inline-block text-sm text-white"><?= htmlspecialchars($displayName) ?></span>
                    </button>
                    <div id="profileMenu" class="profile-menu hidden">
                        <a href="./profile.php">Profile</a>
                        <a href="../../customer/logout.php" class="logout">Log out</a>
                    </div>
                </div>
            </div>
        </div>
    </header>

    <section id="hero" class="hero">
        <div class="hero-overlay"></div>
        <div class="relative z-10 max-w-4xl mx-auto px-4 py-24 text-center text-white">
            <h1 class="text-4xl sm:text-5xl font-extrabold mb-4">Order Management</h1>
            <p class="text-lg text-gray-200">Olympia Kitchen Orders</p>
        </div>
    </section>

    <main class="max-w-7xl mx-auto px-4 py-10">
        <?php if ($message): ?>
            <div class="message-box <?= $messageType ?>">
                <?= htmlspecialchars($message) ?>
            </div>
        <?php endif; ?>

        <!-- Status Filter -->
        <div class="mb-6 flex gap-3 flex-wrap">
            <button type="button" class="filter-btn active" data-filter="all">All Orders</button>
            <button type="button" class="filter-btn" data-filter="pending">Pending</button>
            <button type="button" class="filter-btn" data-filter="confirmed">Confirmed</button>
            <button type="button" class="filter-btn" data-filter="preparing">Preparing</button>
            <button type="button" class="filter-btn" data-filter="out_for_delivery">Out for Delivery</button>
            <button type="button" class="filter-btn" data-filter="delivered">Delivered</button>
            <button type="button" class="filter-btn" data-filter="cancelled">Cancelled</button>
        </div>

        <!-- Orders Display -->
        <?php if (count($orders) > 0): ?>
            <div id="ordersContainer">
                <?php foreach ($orders as $order): ?>
                    <div class="order-card" data-status="<?= htmlspecialchars($order['order_status']) ?>">
                        <div class="order-header">
                            <div class="flex justify-between items-start flex-wrap gap-3">
                                <div>
                                    <h3 class="text-xl font-bold mb-1">Order #<?= htmlspecialchars($order['order_number']) ?></h3>
                                    <p class="text-sm opacity-90">Customer: <?= htmlspecialchars($order['fullname'] ?? $order['username']) ?></p>
                                    <?php $orderPhone = $order['phone'] ?? $order['phone_number'] ?? $order['customer_phone'] ?? 'N/A'; ?>
                                    <p class="text-sm opacity-90">Phone: <?= htmlspecialchars($orderPhone) ?></p>
                                </div>
                                <div class="text-right">
                                    <span class="status-badge status-<?= htmlspecialchars($order['order_status']) ?>">
                                        <?= str_replace('_', ' ', htmlspecialchars($order['order_status'])) ?>
                                    </span>
                                    <p class="text-sm mt-2 opacity-90"><?= date('M d, Y h:i A', strtotime($order['order_date'])) ?></p>
                                </div>
                            </div>
                        </div>

                        <div class="order-body">
                            <div class="grid md:grid-cols-2 gap-6">
                                <!-- Order Items -->
                                <div>
                                    <h4 class="font-bold text-lg mb-3 text-gray-800">Order Items</h4>
                                    <?php if (!empty($order['items'])): ?>
                                        <?php foreach ($order['items'] as $item): ?>
                                            <div class="item-row">
                                                <div>
                                                    <p class="font-semibold"><?= htmlspecialchars($item['product_name']) ?></p>
                                                    <p class="text-sm text-gray-600">Tk <?= number_format((float)$item['price'], 2) ?> × <?= (int)$item['quantity'] ?></p>
                                                </div>
                                                <div class="font-bold text-right">
                                                    Tk <?= number_format((float)$item['subtotal'], 2) ?>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <p class="text-gray-500">No items found</p>
                                    <?php endif; ?>

                                    <div class="mt-4 pt-4 border-t-2 border-gray-200">
                                        <div class="flex justify-between mb-2">
                                            <span class="text-gray-600">Subtotal:</span>
                                            <span class="font-semibold">Tk <?= number_format((float)$order['subtotal'], 2) ?></span>
                                        </div>
                                        <div class="flex justify-between mb-2">
                                            <span class="text-gray-600">Delivery Fee:</span>
                                            <span class="font-semibold">Tk <?= number_format((float)$order['delivery_fee'], 2) ?></span>
                                        </div>
                                        <div class="flex justify-between text-lg font-bold text-red-600">
                                            <span>Total:</span>
                                            <span>Tk <?= number_format((float)$order['total_amount'], 2) ?></span>
                                        </div>
                                    </div>
                                </div>

                                <!-- Delivery Details & Status Update -->
                                <div>
                                    <h4 class="font-bold text-lg mb-3 text-gray-800">Delivery Details</h4>
                                    <div class="space-y-3">
                                        <div>
                                            <p class="text-sm text-gray-600 font-semibold">Delivery Address:</p>
                                            <p class="text-gray-800"><?= nl2br(htmlspecialchars($order['delivery_address'])) ?></p>
                                        </div>
                                        
                                        <div>
                                            <p class="text-sm text-gray-600 font-semibold">Payment Method:</p>
                                            <p class="text-gray-800 capitalize"><?= str_replace('_', ' ', htmlspecialchars($order['payment_method'])) ?></p>
                                        </div>

                                        <?php if (!empty($order['special_instructions'])): ?>
                                            <div>
                                                <p class="text-sm text-gray-600 font-semibold">Special Instructions:</p>
                                                <p class="text-gray-800"><?= nl2br(htmlspecialchars($order['special_instructions'])) ?></p>
                                            </div>
                                        <?php endif; ?>

                                        <!-- Update Status Form -->
                                        <div class="mt-4 pt-4 border-t border-gray-200">
                                            <form method="POST" class="space-y-3">
                                                <input type="hidden" name="update_status" value="1">
                                                <input type="hidden" name="order_id" value="<?= (int)$order['order_id'] ?>">
                                                
                                                <label class="block">
                                                    <span class="text-sm font-semibold text-gray-700">Update Order Status:</span>
                                                    <select name="order_status" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-red-500 focus:ring focus:ring-red-200 p-2">
                                                        <option value="pending" <?= $order['order_status'] === 'pending' ? 'selected' : '' ?>>Pending</option>
                                                        <option value="confirmed" <?= $order['order_status'] === 'confirmed' ? 'selected' : '' ?>>Confirmed</option>
                                                        <option value="preparing" <?= $order['order_status'] === 'preparing' ? 'selected' : '' ?>>Preparing</option>
                                                        <option value="out_for_delivery" <?= $order['order_status'] === 'out_for_delivery' ? 'selected' : '' ?>>Out for Delivery</option>
                                                        <option value="delivered" <?= $order['order_status'] === 'delivered' ? 'selected' : '' ?>>Delivered</option>
                                                        <option value="cancelled" <?= $order['order_status'] === 'cancelled' ? 'selected' : '' ?>>Cancelled</option>
                                                    </select>
                                                </label>
                                                
                                                <button type="submit" class="btn btn-primary w-full">Update Status</button>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="bg-white rounded-lg shadow p-12 text-center">
                <svg class="mx-auto h-24 w-24 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
                </svg>
                <h3 class="mt-4 text-xl font-semibold text-gray-900">No Orders Yet</h3>
                <p class="mt-2 text-gray-600">Orders from customers will appear here.</p>
            </div>
        <?php endif; ?>
    </main>

    <script>
        // Profile dropdown
        const profileBtn = document.getElementById('profileBtn');
        const profileMenu = document.getElementById('profileMenu');
        profileBtn.addEventListener('click', function(e) {
            e.stopPropagation();
            profileMenu.classList.toggle('show');
        });
        document.addEventListener('click', function() {
            profileMenu.classList.remove('show');
        });

        // Status filter
        const filterBtns = document.querySelectorAll('.filter-btn');
        const orderCards = document.querySelectorAll('.order-card');
        
        filterBtns.forEach(btn => {
            btn.addEventListener('click', function() {
                filterBtns.forEach(b => b.classList.remove('active'));
                this.classList.add('active');
                
                const filterStatus = this.dataset.filter;
                
                orderCards.forEach(card => {
                    if (filterStatus === 'all' || card.dataset.status === filterStatus) {
                        card.style.display = 'block';
                    } else {
                        card.style.display = 'none';
                    }
                });
            });
        });
    </script>
</body>
</html>
