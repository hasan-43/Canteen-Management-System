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
    WHERE LOWER(kitchen) = 'olympia'";
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
               WHERE LOWER(kitchen) = 'olympia' AND order_status = 'delivered'
                 AND order_date >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)
               GROUP BY DATE(order_date)
               ORDER BY od";
$res = $conn->query($sqlRevenue);
if ($res) {
    // Fill missing days with 0
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
           WHERE LOWER(o.kitchen) = 'olympia' AND o.order_status = 'delivered'
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
$sqlStatus = "SELECT order_status, COUNT(*) AS c FROM orders WHERE LOWER(kitchen) = 'olympia' GROUP BY order_status";
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
            WHERE LOWER(o.kitchen) = 'olympia' AND o.order_status = 'delivered'
            GROUP BY o.customer_id, c.fullname, c.username
            ORDER BY spend DESC
            LIMIT 5";
$res = $conn->query($sqlCust);
if ($res) {
    while ($row = $res->fetch_assoc()) {
        $topCustomers[] = $row;
    }
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width,initial-scale=1" />
    <title>Olympia Kitchen - Statistics</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    <style>
        header { position: fixed; top: 0; left: 0; right: 0; z-index: 50; background: rgba(0,0,0,0.7); backdrop-filter: blur(10px); }
        .logo-section { position: absolute; left: 2rem; top: 50%; transform: translateY(-50%); }
        .profile-section { position: absolute; right: 2rem; top: 50%; transform: translateY(-50%); }
        .nav-buttons { display: none; }
        @media (min-width: 768px) { .nav-buttons { display: flex; position: absolute; left: 50%; top: 50%; transform: translate(-50%, -50%); gap: 1rem; } }
        .food-wave { font-weight: 900; letter-spacing: 3px; font-size: 2.4rem; background: linear-gradient(90deg, #ff0000); -webkit-background-clip: text; background-clip: text; -webkit-text-fill-color: transparent; display: inline-block; }
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
    </style>
</head>
<body class="bg-gray-50 text-gray-900">
    <header class="h-16 flex items-center">
        <div class="relative h-full w-full max-w-7xl mx-auto px-4">
            <div class="logo-section">
                <h2 class="food-wave">Food Wave</h2>
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
                        <a href="./profile.php">Profile</a>
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
            <p class="text-lg text-gray-200">Sales performance for Olympia Kitchen</p>
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
        document.addEventListener('click', () => profileMenu.classList.remove('show'));
    </script>
</body>
</html>
