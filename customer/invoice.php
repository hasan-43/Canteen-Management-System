<?php
session_start();
require_once __DIR__ . '/../config/db_connection.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
}

$customerId = (int)$_SESSION['user_id'];

// Fetch all orders for this customer
$orders = [];
$stmt = $conn->prepare("
    SELECT * FROM orders 
    WHERE customer_id = ? 
    ORDER BY order_date DESC
");
$stmt->bind_param("i", $customerId);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $orders[] = $row;
}
$stmt->close();

// If viewing specific invoice
$selectedOrder = null;
$orderItems = [];
if (isset($_GET['order_id'])) {
    $orderId = (int)$_GET['order_id'];
    
    $stmt = $conn->prepare("SELECT * FROM orders WHERE order_id = ? AND customer_id = ?");
    $stmt->bind_param("ii", $orderId, $customerId);
    $stmt->execute();
    $result = $stmt->get_result();
    $selectedOrder = $result->fetch_assoc();
    $stmt->close();
    
    if ($selectedOrder) {
        $stmt = $conn->prepare("SELECT * FROM order_items WHERE order_id = ?");
        $stmt->bind_param("i", $orderId);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $orderItems[] = $row;
        }
        $stmt->close();
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
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Invoice - Food Wave</title>
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
            background-image: url('../resources/Invoice.jpg');
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
        .nav-link { padding: 0.5rem 0.9rem; border-radius: 0.5rem; font-weight: 600; color: #fff; }
        .nav-link:hover { background-color: rgba(248,113,113,0.12); color: #fca5a5; }
        .nav-dropdown { position: relative; }
        .nav-dropdown-menu { 
            position: absolute; left: 0; margin-top: 0.4rem; width: 12rem; 
            background: #1a1a1a; border: 1px solid #333; border-radius: 0.5rem; 
            box-shadow: 0 10px 25px -10px rgba(0,0,0,0.5); 
            opacity: 0; transform: translateY(-4px) scale(0.98); 
            transition: all 0.15s ease; pointer-events: none; 
        }
        .nav-dropdown:hover .nav-dropdown-menu { 
            opacity: 1; transform: translateY(0) scale(1); pointer-events: auto; 
        }
        .nav-dropdown-menu a { 
            display: block; padding: 0.55rem 0.9rem; color: #fff; border-radius: 0.5rem; 
        }
        .nav-dropdown-menu a:hover { background: #333; }
        
        .initials-circle { 
            width: 42px; height: 42px; border-radius: 9999px; 
            background: linear-gradient(135deg,#ef4444,#7f1d1d); 
            display: inline-flex; align-items: center; justify-content: center; 
            color: #fff; font-weight: 700; 
        }
        
        .profile-menu.show { display: block; }
        
        .status-badge {
            display: inline-block;
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 700;
            text-transform: uppercase;
        }
        
        .status-pending { background: #fef3c7; color: #92400e; }
        .status-confirmed { background: #dbeafe; color: #1e40af; }
        .status-preparing { background: #e0e7ff; color: #4338ca; }
        .status-out_for_delivery { background: #fbcfe8; color: #9f1239; }
        .status-delivered { background: #dcfce7; color: #166534; }
        .status-cancelled { background: #fee2e2; color: #991b1b; }

        /* 3D Row Effects */
        .invoice-row {
            perspective: 1000px;
            transition: all 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.275);
        }

        .invoice-row:hover {
            transform: rotateY(2deg) rotateX(-1deg) translateZ(10px);
            box-shadow: 15px 15px 40px rgba(0, 0, 0, 0.2), -5px -5px 20px rgba(255, 255, 255, 0.8);
        }

        /* Row gradient colors cycling - Enhanced visibility */
        .row-color-1 { background: linear-gradient(135deg, #cffafe 0%, #06b6d4 100%); border-left: 8px solid #0891b2; box-shadow: inset 0 2px 4px rgba(0,0,0,0.05); }
        .row-color-2 { background: linear-gradient(135deg, #dcfce7 0%, #10b981 100%); border-left: 8px solid #059669; box-shadow: inset 0 2px 4px rgba(0,0,0,0.05); }
        .row-color-3 { background: linear-gradient(135deg, #e9d5ff 0%, #a855f7 100%); border-left: 8px solid #9333ea; box-shadow: inset 0 2px 4px rgba(0,0,0,0.05); }
        .row-color-4 { background: linear-gradient(135deg, #fef3c7 0%, #f59e0b 100%); border-left: 8px solid #d97706; box-shadow: inset 0 2px 4px rgba(0,0,0,0.05); }
        .row-color-5 { background: linear-gradient(135deg, #fce7f3 0%, #ec4899 100%); border-left: 8px solid #be185d; box-shadow: inset 0 2px 4px rgba(0,0,0,0.05); }

        /* 3D Card Effect for Invoice Detail */
        .invoice-detail-card {
            perspective: 1200px;
            transform-style: preserve-3d;
            box-shadow: 
                0 20px 60px rgba(0, 0, 0, 0.15),
                inset 0 1px 0 rgba(255, 255, 255, 0.6);
            border: 2px solid rgba(255, 255, 255, 0.8);
        }

        /* Invoice table 3D styling */
        .invoice-table-3d {
            transform: perspective(1200px) rotateX(0.5deg);
            box-shadow: 
                0 30px 60px rgba(0, 0, 0, 0.12),
                0 0 1px rgba(0, 0, 0, 0.2);
        }

        /* Print-friendly 3D effect */
        @media print {
            .no-print { display: none !important; }
            body { background: white; }
            body::before, body::after { display: none; }
            
            .print-area { 
                box-shadow: none !important; 
                transform: none !important;
                page-break-inside: avoid;
                margin: 0 !important;
                padding: 0 !important;
            }
            
            .invoice-detail-card {
                box-shadow: none;
                border: 1px solid #ccc;
                margin: 0;
                padding: 0;
                max-width: 100%;
            }
            
            /* Compact print layout */
            .print-area .p-8 { padding: 1rem !important; }
            .print-area h1 { font-size: 1.5rem !important; margin-bottom: 0.25rem !important; }
            .print-area h3 { font-size: 0.9rem !important; margin-bottom: 0.5rem !important; }
            .print-area .grid { gap: 0.75rem !important; margin-bottom: 0.75rem !important; }
            .print-area .space-y-3 > * + * { margin-top: 0.25rem !important; }
            .print-area .space-y-2 > * + * { margin-top: 0.25rem !important; }
            .print-area .mb-8 { margin-bottom: 0.75rem !important; }
            .print-area .rounded-xl { border-radius: 0.5rem !important; }
            .print-area .p-6 { padding: 0.5rem !important; }
            .print-area .px-4 { padding-left: 0.5rem !important; padding-right: 0.5rem !important; }
            .print-area .py-3 { padding-top: 0.35rem !important; padding-bottom: 0.35rem !important; }
            .print-area table { font-size: 0.75rem !important; }
            .print-area .text-sm { font-size: 0.7rem !important; }
            .print-area .text-lg { font-size: 0.85rem !important; }
            .print-area .text-2xl { font-size: 1.1rem !important; }
            .print-area .text-3xl { font-size: 1.3rem !important; }
            .print-area .border-l-4 { border-left-width: 2px !important; }
            
            /* Hide decorative elements */
            .absolute.top-0, .absolute.bottom-0 { display: none !important; }
            
            @page {
                size: A4;
                margin: 10mm;
            }
        }
    </style>
</head>

<body class="min-h-screen">
    <!-- Header/Navbar - Matches navbar.php styling -->
    <header class="fixed top-0 left-0 right-0 z-50 bg-black/70 backdrop-blur-md border-b border-gray-800 no-print">
        <div class="relative h-16 max-w-7xl mx-auto px-4">
            <!-- Logo left -->
            <div class="absolute left-4 top-1/2 -translate-y-1/2">
                <h2 class="food-wave">Food Wave</h2>
            </div>

            <!-- Nav buttons center -->
            <nav class="nav-buttons absolute left-1/2 -translate-x-1/2 top-1/2 -translate-y-1/2 text-sm gap-4">
                <a href="./navbar.php" class="px-4 py-2 rounded text-white hover:bg-red-600 hover:bg-opacity-80 transition">Home</a>
                <div class="relative group">
                    <button class="px-4 py-2 rounded text-white hover:bg-red-600 hover:bg-opacity-80 transition">
                        Shop <i class="fas fa-chevron-down ml-1 text-xs"></i>
                    </button>
                    <div class="absolute left-0 mt-2 w-48 bg-black/95 border border-gray-700 rounded shadow-lg opacity-0 group-hover:opacity-100 transform scale-95 group-hover:scale-100 transition-all origin-top">
                        <a href="./khans.php" class="block px-4 py-2 text-white hover:bg-gray-700">Khans Kitchen</a>
                        <a href="./olympia.php" class="block px-4 py-2 text-white hover:bg-gray-700">Olympia Kitchen</a>
                        <a href="./neptune.php" class="block px-4 py-2 text-white hover:bg-gray-700">Neptune Kitchen</a>
                    </div>
                </div>
                <a href="./invoice.php" class="px-4 py-2 rounded text-white hover:bg-red-600 hover:bg-opacity-80 transition">Invoice</a>
                <a href="./chat.php" class="px-4 py-2 rounded text-sm hover:bg-red-600 hover:bg-opacity-80 transition">
                    <i class="fas fa-comments mr-1"></i> Chat
                </a>
                <a href="cart.php" class="px-4 py-2 rounded text-white hover:bg-red-600 hover:bg-opacity-80 transition">Cart</a>
            </nav>

            <!-- Profile right -->
            <div class="absolute right-4 top-1/2 -translate-y-1/2">
                <div class="relative" id="profileRoot">
                    <button id="profileBtn" class="flex items-center gap-2 p-1 rounded hover:bg-gray-700/50 transition">
                        <?php if ($profilePic): ?>
                            <img src="<?= htmlspecialchars($profilePic) ?>" alt="Profile" class="w-10 h-10 rounded-full object-cover border-2 border-red-600">
                        <?php else: ?>
                            <div class="initials-circle"><?= htmlspecialchars($initials_text) ?></div>
                        <?php endif; ?>
                        <span class="hidden sm:inline-block text-sm text-white"><?= htmlspecialchars($displayName) ?></span>
                    </button>
                    <div id="profileMenu" class="profile-menu hidden absolute right-0 mt-2 w-48 bg-black/95 rounded shadow-lg border border-gray-700 text-left z-50">
                        <a href="../customer/profile.php" class="block px-4 py-2 text-white hover:bg-gray-700">Profile</a>
                        <a href="./logout.php" class="block px-4 py-2 text-red-400 hover:bg-gray-700">Logout</a>
                    </div>
                </div>
            </div>
        </div>
    </header>
    <div class="pt-16"></div>

    <!-- Main Content -->
    <main class="max-w-7xl mx-auto px-4 py-8">
        <?php if ($selectedOrder): ?>
            <!-- Invoice Detail View -->
            <div class="mb-6 no-print">
                <a href="./invoice.php" class="inline-flex items-center px-4 py-2 bg-red-600 text-white font-bold rounded-lg hover:bg-red-700 transition">
                    <i class="fas fa-arrow-left mr-2"></i>Back to All Invoices
                </a>
            </div>
            
            <div class="bg-white rounded-2xl shadow-2xl overflow-hidden print-area invoice-detail-card">
                <!-- Invoice Header -->
                <div class="bg-gradient-to-r from-purple-600 via-indigo-600 to-blue-600 p-8 text-white relative overflow-hidden">
                    <div class="absolute top-0 right-0 w-40 h-40 bg-white/10 rounded-full -mr-20 -mt-20"></div>
                    <div class="absolute bottom-0 left-0 w-32 h-32 bg-white/10 rounded-full -ml-16 -mb-16"></div>
                    <div class="relative flex justify-between items-start">
                        <div>
                            <h1 class="text-4xl font-bold mb-2">INVOICE</h1>
                            <p class="text-red-100">Food Wave Canteen Management</p>
                        </div>
                        <button onclick="window.print()" class="no-print px-6 py-3 bg-white/95 text-purple-600 rounded-lg hover:bg-white transition shadow-lg font-bold">
                            <i class="fas fa-print mr-2"></i>Print Invoice
                        </button>
                    </div>
                </div>
                
                <!-- Invoice Details -->
                <div class="p-8">
                    <div class="grid md:grid-cols-2 gap-6 mb-8">
                        <!-- Order Info Card -->
                        <div class="bg-gradient-to-br from-blue-50 to-blue-100 rounded-xl p-6 border-2 border-blue-200 shadow-lg" style="transform: perspective(1200px) rotateY(-2deg);">
                            <h3 class="font-bold text-gray-800 mb-3 text-lg flex items-center gap-2">
                                <i class="fas fa-receipt text-blue-600 bg-blue-200 p-2 rounded-full"></i>
                                Order Information
                            </h3>
                            <div class="space-y-3 text-sm">
                                <p><strong class="text-gray-700">Order Number:</strong> <span class="bg-blue-200 text-blue-800 px-2 py-1 rounded font-bold"><?= htmlspecialchars($selectedOrder['order_number']) ?></span></p>
                                <p><strong class="text-gray-700">Order Date:</strong> <span class="text-gray-800"><?= date('F j, Y - g:i A', strtotime($selectedOrder['order_date'])) ?></span></p>
                                <p><strong class="text-gray-700">Kitchen:</strong> <span class="bg-orange-100 text-orange-800 px-2 py-1 rounded font-bold"><?= ucfirst($selectedOrder['kitchen']) ?> Kitchen</span></p>
                                <p><strong class="text-gray-700">Status:</strong> 
                                    <span class="status-badge status-<?= $selectedOrder['order_status'] ?> inline-block mt-1">
                                        <?= ucwords(str_replace('_', ' ', $selectedOrder['order_status'])) ?>
                                    </span>
                                </p>
                            </div>
                        </div>
                        
                        <!-- Delivery Info Card -->
                        <div class="bg-gradient-to-br from-green-50 to-green-100 rounded-xl p-6 border-2 border-green-200 shadow-lg" style="transform: perspective(1200px) rotateY(2deg);">
                            <h3 class="font-bold text-gray-800 mb-3 text-lg flex items-center gap-2">
                                <i class="fas fa-map-marker-alt text-green-600 bg-green-200 p-2 rounded-full"></i>
                                Delivery Information
                            </h3>
                            <div class="space-y-3 text-sm">
                                <p><strong class="text-gray-700">Customer:</strong> <span class="text-gray-800 font-semibold"><?= htmlspecialchars($displayName) ?></span></p>
                                <p><strong class="text-gray-700">Delivery Address:</strong><br><span class="text-gray-800 bg-white/50 p-2 rounded mt-1 block"><?= nl2br(htmlspecialchars($selectedOrder['delivery_address'])) ?></span></p>
                                <p><strong class="text-gray-700">Payment Method:</strong> 
                                    <?php
                                    $paymentMethods = [
                                        'cash_on_delivery' => 'Cash on Delivery',
                                        'mobile_banking' => 'Mobile Banking',
                                        'card' => 'Card Payment'
                                    ];
                                    $paymentIcons = [
                                        'cash_on_delivery' => 'fa-money-bill-wave',
                                        'mobile_banking' => 'fa-mobile-alt',
                                        'card' => 'fa-credit-card'
                                    ];
                                    ?>
                                    <span class="bg-purple-100 text-purple-800 px-2 py-1 rounded font-bold">
                                        <i class="fas <?= $paymentIcons[$selectedOrder['payment_method']] ?> mr-1"></i>
                                        <?= $paymentMethods[$selectedOrder['payment_method']] ?>
                                    </span>
                                </p>
                            </div>
                        </div>
                    </div>
                    
                    <?php if ($selectedOrder['special_instructions']): ?>
                        <div class="bg-yellow-50 rounded-xl p-4 mb-8 border-2 border-yellow-300 shadow-md">
                            <p class="text-sm"><i class="fas fa-comment-dots text-yellow-600 mr-2"></i><strong>Special Instructions:</strong> <?= nl2br(htmlspecialchars($selectedOrder['special_instructions'])) ?></p>
                        </div>
                    <?php endif; ?>
                    
                    <!-- Order Items Table -->
                    <div class="mb-8">
                        <h3 class="font-bold text-gray-800 mb-4 text-lg flex items-center gap-2">
                            <i class="fas fa-utensils text-red-600 bg-red-100 p-2 rounded-full"></i>
                            Order Items
                        </h3>
                        <div class="overflow-x-auto">
                            <table class="w-full border-collapse rounded-lg overflow-hidden shadow-lg">
                                <thead>
                                    <tr class="bg-gradient-to-r from-gray-800 to-gray-900 text-white">
                                        <th class="border border-gray-700 px-4 py-3 text-left font-bold">#</th>
                                        <th class="border border-gray-700 px-4 py-3 text-left font-bold">Item Name</th>
                                        <th class="border border-gray-700 px-4 py-3 text-right font-bold">Price</th>
                                        <th class="border border-gray-700 px-4 py-3 text-center font-bold">Quantity</th>
                                        <th class="border border-gray-700 px-4 py-3 text-right font-bold">Subtotal</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($orderItems as $index => $item): 
                                        $rowColors = ['bg-blue-50', 'bg-green-50', 'bg-purple-50', 'bg-orange-50', 'bg-pink-50'];
                                        $borderColors = ['border-blue-300', 'border-green-300', 'border-purple-300', 'border-orange-300', 'border-pink-300'];
                                        $colorClass = $rowColors[$index % 5];
                                        $borderClass = $borderColors[$index % 5];
                                    ?>
                                        <tr class="<?= $colorClass ?> border-l-4 <?= $borderClass ?>">
                                            <td class="border border-gray-300 px-4 py-3 font-bold text-gray-800"><?= $index + 1 ?></td>
                                            <td class="border border-gray-300 px-4 py-3 font-semibold text-gray-800"><?= htmlspecialchars($item['product_name']) ?></td>
                                            <td class="border border-gray-300 px-4 py-3 text-right font-semibold">৳<?= number_format($item['price'], 2) ?></td>
                                            <td class="border border-gray-300 px-4 py-3 text-center font-bold text-gray-800"><?= $item['quantity'] ?></td>
                                            <td class="border border-gray-300 px-4 py-3 text-right font-bold text-gray-900">৳<?= number_format($item['subtotal'], 2) ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    
                    <!-- Totals -->
                    <div class="flex justify-end">
                        <div class="w-full md:w-1/2 lg:w-1/3">
                            <div class="bg-gradient-to-br from-red-50 via-orange-50 to-red-100 rounded-xl p-6 border-2 border-red-300 shadow-lg" style="transform: perspective(1200px) rotateX(2deg);">
                                <div class="space-y-3">
                                    <div class="flex justify-between text-gray-700 font-semibold">
                                        <span>Subtotal:</span>
                                        <span>৳<?= number_format($selectedOrder['subtotal'], 2) ?></span>
                                    </div>
                                    <div class="flex justify-between text-gray-700 font-semibold">
                                        <span>Delivery Fee:</span>
                                        <span>৳<?= number_format($selectedOrder['delivery_fee'], 2) ?></span>
                                    </div>
                                    <div class="flex justify-between pt-3 border-t-2 border-red-300">
                                        <span class="text-lg font-bold text-gray-800">Total Amount:</span>
                                        <span class="text-3xl font-bold text-red-600">৳<?= number_format($selectedOrder['total_amount'], 2) ?></span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mt-8 pt-6 border-t-2 border-gray-300 text-center">
                        <p class="text-gray-700 font-semibold">Thank you for your order!</p>
                        <p class="text-gray-600 mt-2">For any queries, please contact us at <span class="text-red-600 font-bold">support@foodwave.com</span></p>
                    </div>
                </div>
            </div>
            
        <?php else: ?>
            <!-- Order List View -->
            <div class="mb-8">
                <h1 class="text-4xl font-bold text-gray-800 flex items-center gap-3">
                    <i class="fas fa-file-invoice text-red-600 bg-red-100 p-4 rounded-full text-2xl"></i>
                    <span>My Invoices</span>
                </h1>
                <p class="text-gray-600 mt-3 text-lg">View and print your order invoices</p>
            </div>

            <?php if (empty($orders)): ?>
                <div class="bg-white rounded-2xl shadow-xl p-12 text-center">
                    <i class="fas fa-file-invoice text-gray-300 text-6xl mb-4"></i>
                    <h2 class="text-2xl font-bold text-gray-800 mb-2">No Orders Yet</h2>
                    <p class="text-gray-600 mb-6">You haven't placed any orders. Start ordering delicious food!</p>
                    <div class="flex gap-4 justify-center">
                        <a href="./khans.php" class="px-6 py-3 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition">
                            <i class="fas fa-utensils mr-2"></i>Browse Khans
                        </a>
                        <a href="./olympia.php" class="px-6 py-3 bg-green-600 text-white rounded-lg hover:bg-green-700 transition">
                            <i class="fas fa-utensils mr-2"></i>Browse Olympia
                        </a>
                        <a href="./neptune.php" class="px-6 py-3 bg-yellow-600 text-white rounded-lg hover:bg-yellow-700 transition">
                            <i class="fas fa-utensils mr-2"></i>Browse Neptune
                        </a>
                    </div>
                </div>
            <?php else: ?>
            <div class="bg-gradient-to-br from-red-50 via-white to-orange-50 rounded-3xl shadow-2xl overflow-hidden border-2 border-red-200">
                    <div class="overflow-x-auto">
                        <table class="w-full invoice-table-3d">
                            <thead class="bg-gradient-to-r from-red-600 via-red-700 to-red-800 text-white">
                                <tr>
                                    <th class="px-6 py-4 text-left font-bold text-lg">Order #</th>
                                    <th class="px-6 py-4 text-left font-bold text-lg">Date</th>
                                    <th class="px-6 py-4 text-left font-bold text-lg">Kitchen</th>
                                    <th class="px-6 py-4 text-left font-bold text-lg">Payment</th>
                                    <th class="px-6 py-4 text-right font-bold text-lg">Total</th>
                                    <th class="px-6 py-4 text-center font-bold text-lg">Status</th>
                                    <th class="px-6 py-4 text-center font-bold text-lg">Action</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200">
                                <?php foreach ($orders as $index => $order): 
                                    $colorClass = 'row-color-' . (($index % 5) + 1);
                                ?>
                                    <tr class="invoice-row <?= $colorClass ?> transition-all duration-300 hover:shadow-2xl" style="padding: 8px 0;">
                                        <td class="px-6 py-5">
                                            <span class="font-bold text-gray-800 bg-white px-3 py-1 rounded-full inline-block shadow-md"><?= htmlspecialchars($order['order_number']) ?></span>
                                        </td>
                                        <td class="px-6 py-5 text-sm">
                                            <div class="font-bold text-gray-800"><?= date('M j, Y', strtotime($order['order_date'])) ?></div>
                                            <div class="text-xs text-gray-600 mt-1"><?= date('g:i A', strtotime($order['order_date'])) ?></div>
                                        </td>
                                        <td class="px-6 py-5">
                                            <span class="font-bold text-gray-800 bg-white px-3 py-1 rounded-lg inline-block shadow-md"><?= ucfirst($order['kitchen']) ?></span>
                                        </td>
                                        <td class="px-6 py-5 text-sm">
                                            <?php
                                            $icons = [
                                                'cash_on_delivery' => 'fa-money-bill-wave',
                                                'mobile_banking' => 'fa-mobile-alt',
                                                'card' => 'fa-credit-card'
                                            ];
                                            $names = [
                                                'cash_on_delivery' => 'Cash',
                                                'mobile_banking' => 'Mobile',
                                                'card' => 'Card'
                                            ];
                                            $colors = [
                                                'cash_on_delivery' => 'bg-green-100 text-green-600',
                                                'mobile_banking' => 'bg-blue-100 text-blue-600',
                                                'card' => 'bg-purple-100 text-purple-600'
                                            ];
                                            ?>
                                            <div class="flex items-center gap-2">
                                                <div class="w-8 h-8 <?= $colors[$order['payment_method']] ?> rounded-full flex items-center justify-center shadow-md">
                                                    <i class="fas <?= $icons[$order['payment_method']] ?> text-sm"></i>
                                                </div>
                                                <span class="font-bold text-gray-800"><?= $names[$order['payment_method']] ?></span>
                                            </div>
                                        </td>
                                        <td class="px-6 py-5 text-right">
                                            <span class="font-bold text-xl text-gray-800 drop-shadow-lg">৳<?= number_format($order['total_amount'], 2) ?></span>
                                        </td>
                                        <td class="px-6 py-5 text-center">
                                            <span class="status-badge status-<?= $order['order_status'] ?> shadow-md">
                                                <?= ucwords(str_replace('_', ' ', $order['order_status'])) ?>
                                            </span>
                                        </td>
                                        <td class="px-6 py-5 text-center">
                                            <a href="?order_id=<?= $order['order_id'] ?>" 
                                               class="inline-flex items-center px-4 py-2 bg-gradient-to-r from-red-600 to-red-700 text-white text-sm font-bold rounded-lg hover:shadow-lg hover:scale-110 transition-all transform duration-300"
                                               style="box-shadow: 0 10px 20px rgba(220, 38, 38, 0.3);">
                                                <i class="fas fa-file-invoice mr-2"></i>View
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                
                <div class="mt-6 bg-gradient-to-r from-blue-50 to-blue-100 rounded-2xl p-6 border-2 border-blue-300 shadow-lg">
                    <p class="text-sm text-blue-900 font-semibold">
                        <i class="fas fa-info-circle mr-2"></i>
                        <strong>Total Orders:</strong> <?= count($orders) ?> orders | 
                        <strong>Total Spent:</strong> <span class="text-xl text-red-600">৳<?= number_format(array_sum(array_column($orders, 'total_amount')), 2) ?></span>
                    </p>
                </div>
            <?php endif; ?>
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
    </script>
</body>
</html>
