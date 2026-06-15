<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
session_start();
require_once __DIR__ . '/../../config/db_connection.php';

// Session guard
if (!isset($_SESSION['usertype']) || $_SESSION['usertype'] !== 'admin' || !isset($_SESSION['shop_table'])) {
    header('Location: ../../login.php');
    exit;
}

$shopTable = $_SESSION['shop_table'];
$shopDisplayName = $_SESSION['shop'];

// Handle delivery man block/unblock
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['toggle_delivery_status'])) {
    $delivery_id = (int)$_POST['delivery_id'];
    $new_status = $_POST['new_status'] === 'active' ? 'blocked' : 'active';
    
    $stmt = $conn->prepare("UPDATE delivery SET status = ? WHERE delivery_id = ?");
    $stmt->bind_param("si", $new_status, $delivery_id);
    $stmt->execute();
    $stmt->close();
    
    // Redirect to avoid form resubmission
    header("Location: statistics.php");
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

// Dashboard metrics
$metrics = [
    'total_orders' => 0,
    'delivered_orders' => 0,
    'pending_orders' => 0,
    'total_revenue' => 0.0,
    'unique_customers' => 0,
];

// Aggregate metrics
$sqlMetrics = "SELECT 
    COUNT(*) AS total_orders,
    SUM(CASE WHEN order_status = 'delivered' THEN 1 ELSE 0 END) AS delivered_orders,
    SUM(CASE WHEN order_status = 'pending' THEN 1 ELSE 0 END) AS pending_orders,
    SUM(CASE WHEN order_status = 'delivered' THEN total_amount ELSE 0 END) AS total_revenue,
    COUNT(DISTINCT customer_id) AS unique_customers
    FROM orders
    WHERE LOWER(kitchen) = '{$shopTable}'";
$res = $conn->query($sqlMetrics);
if ($res && $row = $res->fetch_assoc()) {
    $metrics['total_orders'] = (int)$row['total_orders'];
    $metrics['delivered_orders'] = (int)$row['delivered_orders'];
    $metrics['pending_orders'] = (int)$row['pending_orders'];
    $metrics['total_revenue'] = (float)$row['total_revenue'];
    $metrics['unique_customers'] = (int)$row['unique_customers'];
}

// Revenue last 7 days
$revenueDates = [];
$revenueValues = [];
$sqlRevenue = "SELECT DATE(order_date) as od, SUM(total_amount) as rev
               FROM orders
               WHERE LOWER(kitchen) = '{$shopTable}' AND order_status = 'delivered'
                 AND order_date >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)
               GROUP BY DATE(order_date)
               ORDER BY od";
$res = $conn->query($sqlRevenue);
if ($res) {
    $days = [];
    for ($i = 6; $i >= 0; $i--) {
        $d = date('Y-m-d', strtotime("-$i day"));
        $days[$d] = 0.0;
    }
    while ($row = $res->fetch_assoc()) {
        $days[$row['od']] = (float)$row['rev'];
    }
    foreach ($days as $d => $v) {
        $revenueDates[] = $d;
        $revenueValues[] = $v;
    }
}

// Top 5 products by quantity
$topProducts = [];
$sqlTop = "SELECT oi.product_name, SUM(oi.quantity) AS qty, SUM(oi.subtotal) AS revenue
           FROM order_items oi
           JOIN orders o ON o.order_id = oi.order_id
           WHERE LOWER(o.kitchen) = '{$shopTable}' AND o.order_status = 'delivered'
           GROUP BY oi.product_name
           ORDER BY qty DESC
           LIMIT 5";
$res = $conn->query($sqlTop);
if ($res) {
    while ($row = $res->fetch_assoc()) {
        $topProducts[] = $row;
    }
}

// Orders per status (for chart)
$statusLabels = ['pending','confirmed','preparing','out_for_delivery','delivered','cancelled'];
$statusCounts = array_fill_keys($statusLabels, 0);
$sqlStatus = "SELECT order_status, COUNT(*) AS c FROM orders WHERE LOWER(kitchen) = '{$shopTable}' GROUP BY order_status";
$res = $conn->query($sqlStatus);
if ($res) {
    while ($row = $res->fetch_assoc()) {
        $statusCounts[$row['order_status']] = (int)$row['c'];
    }
}

// Top customers by spend
$topCustomers = [];
$sqlCust = "SELECT c.fullname, c.username, SUM(o.total_amount) AS spend, COUNT(*) AS orders_count
            FROM orders o
            LEFT JOIN customer c ON c.customer_id = o.customer_id
            WHERE LOWER(o.kitchen) = '{$shopTable}' AND o.order_status = 'delivered'
            GROUP BY o.customer_id, c.fullname, c.username
            ORDER BY spend DESC
            LIMIT 5";
$res = $conn->query($sqlCust);
if ($res) {
    while ($row = $res->fetch_assoc()) {
        $topCustomers[] = $row;
    }
}

// Review Statistics
$reviewStats = [
    'total_reviews' => 0,
    'avg_rating' => 0,
    'five_star_percent' => 0,
    'recent_reviews' => []
];

$sqlReviewStats = "SELECT 
    COUNT(*) as total_reviews,
    COALESCE(AVG(rating), 0) as avg_rating,
    SUM(CASE WHEN rating = 5 THEN 1 ELSE 0 END) as five_star
    FROM product_reviews 
    WHERE kitchen = '{$shopTable}'";
$res = $conn->query($sqlReviewStats);
if ($res && $row = $res->fetch_assoc()) {
    $reviewStats['total_reviews'] = (int)$row['total_reviews'];
    $reviewStats['avg_rating'] = (float)$row['avg_rating'];
    if ($reviewStats['total_reviews'] > 0) {
        $reviewStats['five_star_percent'] = ($row['five_star'] / $reviewStats['total_reviews']) * 100;
    }
}

// Recent reviews
$sqlRecent = "SELECT pr.*, k.name as product_name, c.fullname, 
              DATE_FORMAT(pr.created_at, '%b %d, %Y') as review_date
              FROM product_reviews pr
              JOIN `{$shopTable}` k ON pr.product_code = k.product_code
              JOIN customer c ON pr.customer_id = c.customer_id
              WHERE pr.kitchen = '{$shopTable}'
              ORDER BY pr.created_at DESC
              LIMIT 5";
$res = $conn->query($sqlRecent);
if ($res) {
    while ($row = $res->fetch_assoc()) {
        $reviewStats['recent_reviews'][] = $row;
    }
}

// Get all delivery men
$deliveryMen = [];
$sqlDelivery = "SELECT delivery_id, username, fullname, email, phone, status FROM delivery ORDER BY fullname";
$res = $conn->query($sqlDelivery);
if ($res) {
    while ($row = $res->fetch_assoc()) {
        $deliveryMen[] = $row;
    }
}
?>
<!doctype html>
<html lang="en">
<HEAD>
    <script src="../../resources/js/theme.js"></script>
    <script src="../../resources/js/theme.js"></script>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width,initial-scale=1" />
    <title>Statistics - <?= htmlspecialchars($shopDisplayName) ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    <style>
        header { position: fixed; top: 0; left: 0; right: 0; z-index: 50; background: rgba(0,0,0,0.7); backdrop-filter: blur(10px); }
        .logo-section { position: absolute; left: 2rem; top: 50%; transform: translateY(-50%); }
        .profile-section { position: absolute; right: 2rem; top: 50%; transform: translateY(-50%); }
        .nav-buttons { display: none; }
        @media (min-width: 768px) { .nav-buttons { display: flex; position: absolute; left: 50%; top: 50%; transform: translate(-50%, -50%); gap: 1rem; } }
        .campus-cravings-logo { height: 50px; width: auto; transition: transform 0.3s ease; }
        .campus-cravings-logo:hover { transform: scale(1.05); }
        .initials-circle { width: 42px; height: 42px; border-radius:9999px; background: linear-gradient(135deg,#ef4444,#7f1d1d); display:inline-flex; align-items:center; justify-content:center; color:#fff; font-weight:700; }
        .avatar-img { width: 42px; height: 42px; border-radius: 9999px; object-fit: cover; }
        .profile-menu.show { display:block; }
        .hero { background-image: url('../../resources/sign_up.jpg'); background-size: cover; background-position: center; background-attachment: fixed; min-height: 50vh; position: relative; overflow: hidden; margin-top: 4rem; }
        .hero::before { content: ''; position: absolute; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0, 0, 0, 0.55); z-index: 1; }
        .hero-overlay { position: absolute; inset: 0; background: transparent; z-index: 1; }
        .card { background: white; border-radius: 12px; box-shadow: 0 4px 16px rgba(0,0,0,0.08); padding: 1.25rem; }
        .mini { background: linear-gradient(135deg,#0ea5e9,#0284c7); color: white; }
        .mini h3 { font-size: 0.9rem; font-weight: 600; }
        .mini p { font-size: 1.8rem; font-weight: 800; }
        .table { width: 100%; border-collapse: collapse; }
        .table th, .table td { padding: 0.6rem 0.5rem; border-bottom: 1px solid #e5e7eb; text-align: left; }
        .table th { font-size: 0.85rem; text-transform: uppercase; letter-spacing: 0.04em; color: #6b7280; }
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
    
        /* Standardized header dark glassmorphism and text logo */
        header { position: fixed; top: 0; left: 0; right: 0; z-index: 50; background: rgba(10, 10, 12, 0.9) !important; backdrop-filter: blur(12px) !important; -webkit-backdrop-filter: blur(12px) !important; border-bottom: 1px solid rgba(255, 255, 255, 0.1) !important; }
        .logo-section a { display: flex; align-items: center; gap: 0.75rem; text-decoration: none; }
        .brand-text { font-size: 1.25rem; font-weight: 800; color: #ffffff; letter-spacing: 0.05em; transition: color 0.3s ease; }
        .logo-section a:hover .brand-text { color: #ef4444; }
    </style>
</head>
<body class="bg-gray-50 text-gray-900">
    <header class="h-16 flex items-center">
        <div class="relative h-full w-full max-w-7xl mx-auto px-4">
            <div class="logo-section">
                <a href="./navbar.php">
                    <img src="../../resources/logo.jpg" alt="Campus Cravings" class="campus-cravings-logo" />
                    <span class="brand-text">Campus Cravings</span>
                </a>
            </div>

            <nav class="nav-buttons">
                <a href="./navbar.php" class="px-4 py-2 rounded text-sm hover:bg-red-600 hover:bg-opacity-80">Home</a>
                <a href="./statistics.php" class="px-4 py-2 rounded text-sm bg-red-600 bg-opacity-80">Statistics</a>
                <a href="./product.php" class="px-4 py-2 rounded text-sm hover:bg-red-600 hover:bg-opacity-80">Product</a>
                <a href="./invoice.php" class="px-4 py-2 rounded text-sm hover:bg-red-600 hover:bg-opacity-80">Invoice</a>
                <a href="./orders.php" class="px-4 py-2 rounded text-sm hover:bg-red-600 hover:bg-opacity-80">Order</a>
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
                        <a href="../../customer/logout.php" class="logout">Log out</a>
                    </div>
                </div>
            </div>
        </div>
    </header>

    <section id="hero" class="hero">
        <div class="hero-overlay"></div>
        <div class="relative z-10 max-w-4xl mx-auto px-4 py-20 text-center text-white">
            <h1 class="text-4xl sm:text-5xl font-extrabold mb-4">Statistics</h1>
            <p class="text-lg text-gray-200">Sales performance for <?= htmlspecialchars($shopDisplayName) ?></p>
        </div>
    </section>

    <main class="max-w-7xl mx-auto px-4 py-10">
        <div class="grid md:grid-cols-5 gap-4 mb-8">
            <div class="card mini md:col-span-1"><h3>Total Orders</h3><p><?= (int)$metrics['total_orders'] ?></p></div>
            <div class="card mini md:col-span-1"><h3>Delivered</h3><p><?= (int)$metrics['delivered_orders'] ?></p></div>
            <div class="card mini md:col-span-1"><h3>Pending</h3><p><?= (int)$metrics['pending_orders'] ?></p></div>
            <div class="card mini md:col-span-1"><h3>Revenue (Tk)</h3><p><?= number_format((float)$metrics['total_revenue'], 2) ?></p></div>
            <div class="card mini md:col-span-1"><h3>Customers</h3><p><?= (int)$metrics['unique_customers'] ?></p></div>
        </div>
        
        <!-- Review Statistics -->
        <div class="grid md:grid-cols-3 gap-4 mb-8">
            <div class="card mini bg-gradient-to-br from-amber-400 to-amber-600 text-white">
                <h3 class="flex items-center gap-2 text-white">
                    <span class="text-white text-2xl">★</span> Avg Rating
                </h3>
                <p class="text-white"><?= number_format($reviewStats['avg_rating'], 1) ?> / 5.0</p>
            </div>
            <div class="card mini bg-gradient-to-br from-indigo-500 to-indigo-700 text-white">
                <h3 class="text-white">Total Reviews</h3>
                <p class="text-white"><?= $reviewStats['total_reviews'] ?></p>
            </div>
            <div class="card mini bg-gradient-to-br from-emerald-500 to-emerald-700 text-white">
                <h3 class="text-white">5-Star Reviews</h3>
                <p class="text-white"><?= number_format($reviewStats['five_star_percent'], 1) ?>%</p>
            </div>
        </div>

        <div class="grid lg:grid-cols-2 gap-6 mb-8">
            <div class="card">
                <h3 class="text-lg font-semibold mb-2 text-gray-800">Revenue (Last 7 days)</h3>
                <canvas id="chartRevenue" height="220"></canvas>
            </div>
            <div class="card">
                <h3 class="text-lg font-semibold mb-2 text-gray-800">Orders by Status</h3>
                <canvas id="chartStatus" height="220"></canvas>
            </div>
        </div>

        <div class="grid lg:grid-cols-2 gap-6 mb-8">
            <div class="card">
                <h3 class="text-lg font-semibold mb-3 text-gray-800">Top Products (by quantity)</h3>
                <canvas id="chartProducts" height="220"></canvas>
            </div>
            <div class="card">
                <h3 class="text-lg font-semibold mb-3 text-gray-800">Top Customers (by spend)</h3>
                <table class="table">
                    <thead>
                        <tr><th>Customer</th><th>Orders</th><th>Spend (Tk)</th></tr>
                    </thead>
                    <tbody>
                        <?php if (count($topCustomers) > 0): ?>
                            <?php foreach ($topCustomers as $c): ?>
                                <tr>
                                    <td><?= htmlspecialchars($c['fullname'] ?: $c['username'] ?: 'N/A') ?></td>
                                    <td><?= (int)$c['orders_count'] ?></td>
                                    <td><?= number_format((float)$c['spend'], 2) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr><td colspan="3" class="text-gray-500">No delivered orders yet</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        
        <!-- Recent Reviews Section -->
        <div class="card mb-8 bg-gradient-to-br from-amber-50 to-orange-50 border-2 border-amber-300">
            <h3 class="text-lg font-semibold mb-3 text-amber-900 flex items-center gap-2 pb-3 border-b-2 border-amber-300">
                <span class="text-amber-600 text-2xl">★</span> Recent Customer Reviews
            </h3>
            <?php if (count($reviewStats['recent_reviews']) > 0): ?>
                <div class="space-y-3">
                    <?php foreach ($reviewStats['recent_reviews'] as $review): ?>
                        <div class="border-l-4 border-amber-500 pl-4 py-3 bg-white rounded-lg shadow-sm hover:shadow-md transition-shadow">
                            <div class="flex items-center justify-between mb-2">
                                <div>
                                    <span class="font-semibold text-amber-900"><?= htmlspecialchars($review['fullname']) ?></span>
                                    <span class="text-sm text-amber-700"> - <?= htmlspecialchars($review['product_name']) ?></span>
                                </div>
                                <div class="flex items-center gap-2">
                                    <span class="text-amber-500 text-lg">
                                        <?php for ($i = 1; $i <= 5; $i++): ?>
                                            <?= $i <= $review['rating'] ? '★' : '☆' ?>
                                        <?php endfor; ?>
                                    </span>
                                    <span class="text-xs text-amber-600"><?= $review['review_date'] ?></span>
                                </div>
                            </div>
                            <?php if ($review['review_text']): ?>
                                <p class="text-sm text-amber-800 mt-1 italic"><?= htmlspecialchars($review['review_text']) ?></p>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <p class="text-amber-600 font-medium">No reviews yet</p>
            <?php endif; ?>
        </div>

        <!-- Delivery Men Management -->
        <div class="card">
            <h3 class="text-lg font-semibold mb-3 text-gray-800">Delivery Men Management</h3>
            <table class="table">
                <thead>
                    <tr><th>Name</th><th>Username</th><th>Email</th><th>Phone</th><th>Status</th><th>Action</th></tr>
                </thead>
                <tbody>
                    <?php if (count($deliveryMen) > 0): ?>
                        <?php foreach ($deliveryMen as $dm): ?>
                            <tr>
                                <td><?= htmlspecialchars($dm['fullname']) ?></td>
                                <td><?= htmlspecialchars($dm['username']) ?></td>
                                <td><?= htmlspecialchars($dm['email'] ?? 'N/A') ?></td>
                                <td><?= htmlspecialchars($dm['phone'] ?? 'N/A') ?></td>
                                <td>
                                    <span class="px-2 py-1 rounded text-sm font-semibold <?= $dm['status'] === 'active' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' ?>">
                                        <?= ucfirst($dm['status']) ?>
                                    </span>
                                </td>
                                <td>
                                    <form method="POST" style="display:inline;">
                                        <input type="hidden" name="delivery_id" value="<?= $dm['delivery_id'] ?>">
                                        <input type="hidden" name="new_status" value="<?= $dm['status'] ?>">
                                        <input type="hidden" name="toggle_delivery_status" value="1">
                                        <button type="submit" class="px-3 py-1 rounded text-sm font-semibold <?= $dm['status'] === 'active' ? 'bg-red-500 hover:bg-red-600 text-white' : 'bg-green-500 hover:bg-green-600 text-white' ?>">
                                            <?= $dm['status'] === 'active' ? 'Block' : 'Unblock' ?>
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="6" class="text-gray-500 text-center">No delivery men found</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </main>

    <script>
        const revenueLabels = <?= json_encode($revenueDates) ?>;
        const revenueData = <?= json_encode($revenueValues) ?>;
        const statusLabels = <?= json_encode(array_keys($statusCounts)) ?>;
        const statusData = <?= json_encode(array_values($statusCounts)) ?>;
        const productLabels = <?= json_encode(array_column($topProducts, 'product_name')) ?>;
        const productQty = <?= json_encode(array_map('intval', array_column($topProducts, 'qty'))) ?>;

        // Revenue line chart
        new Chart(document.getElementById('chartRevenue'), {
            type: 'line',
            data: {
                labels: revenueLabels,
                datasets: [{
                    label: 'Revenue (Tk)',
                    data: revenueData,
                    borderColor: '#0ea5e9',
                    backgroundColor: 'rgba(14,165,233,0.15)',
                    tension: 0.35,
                    fill: true,
                    pointRadius: 4,
                    pointBackgroundColor: '#0284c7'
                }]
            },
            options: { scales: { y: { beginAtZero: true } }, plugins: { legend: { display: false } } }
        });

        // Status doughnut
        new Chart(document.getElementById('chartStatus'), {
            type: 'doughnut',
            data: {
                labels: statusLabels,
                datasets: [{
                    data: statusData,
                    backgroundColor: ['#fbbf24','#60a5fa','#a78bfa','#f472b6','#34d399','#f87171']
                }]
            },
            options: { plugins: { legend: { position: 'bottom' } } }
        });

        // Top products bar chart
        new Chart(document.getElementById('chartProducts'), {
            type: 'bar',
            data: {
                labels: productLabels,
                datasets: [{
                    label: 'Quantity sold',
                    data: productQty,
                    backgroundColor: '#0ea5e9'
                }]
            },
            options: { scales: { y: { beginAtZero: true } }, plugins: { legend: { display: false } } }
        });

        // Profile dropdown
        const profileBtn = document.getElementById('profileBtn');
        const profileMenu = document.getElementById('profileMenu');
        profileBtn?.addEventListener('click', (e) => { e.stopPropagation(); profileMenu.classList.toggle('show'); });
        document.addEventListener('click', () => profileMenu?.classList.remove('show'));
    </script>
</body>
</html>
