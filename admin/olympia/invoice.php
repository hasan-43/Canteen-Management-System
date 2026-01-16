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

// Pagination setup
$itemsPerPage = 10;
$currentPage = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$offset = ($currentPage - 1) * $itemsPerPage;

// Get total count of delivered orders
$countSql = "SELECT COUNT(*) as total FROM orders WHERE LOWER(kitchen) = 'olympia' AND order_status = 'delivered'";
$countResult = $conn->query($countSql);
$totalOrders = 0;
if ($countResult && $row = $countResult->fetch_assoc()) {
	$totalOrders = (int)$row['total'];
}
$totalPages = ceil($totalOrders / $itemsPerPage);

// Fetch delivered orders for Olympia Kitchen with customer details (paginated)
$orders = [];
$totalRevenue = 0.0;

$sql = "SELECT o.*, c.username, c.fullname, c.email, c.phone 
		FROM orders o 
		LEFT JOIN customer c ON o.customer_id = c.customer_id 
		WHERE LOWER(o.kitchen) = 'olympia' AND o.order_status = 'delivered' 
		ORDER BY o.order_date DESC
		LIMIT ? OFFSET ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("ii", $itemsPerPage, $offset);
$stmt->execute();
$result = $stmt->get_result();
if (!$result) {
	error_log('Invoice query error: ' . $conn->error);
}
if ($result && $result->num_rows > 0) {
	while ($row = $result->fetch_assoc()) {
		$order_id = (int)$row['order_id'];
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

		$totalRevenue += (float)$row['total_amount'];
	}
}
$stmt->close();

// Get total revenue for all delivered orders (not just current page)
$revSql = "SELECT SUM(total_amount) as total_rev FROM orders WHERE LOWER(kitchen) = 'olympia' AND order_status = 'delivered'";
$revResult = $conn->query($revSql);
if ($revResult && $row = $revResult->fetch_assoc()) {
	$totalRevenue = (float)$row['total_rev'];
}
?>
<!doctype html>
<html lang="en">
<head>
	<meta charset="utf-8" />
	<meta name="viewport" content="width=device-width,initial-scale=1" />
	<title>Olympia Kitchen - Invoices</title>
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
			border-radius: 9999px;
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
		.profile-menu {
			display: none;
			position: absolute;
			right: 0;
			top: 110%;
			background: white;
			border-radius: 8px;
			box-shadow: 0 4px 12px rgba(0,0,0,0.15);
			min-width: 150px;
			overflow: hidden;
		}
		.profile-menu a {
			display: block;
			padding: 10px 16px;
			color: #374151;
			text-decoration: none;
			transition: all 0.2s;
		}
		.profile-menu a:hover {
			background: #f3f4f6;
		}
		.profile-menu a.logout {
			color: #dc2626;
		}
		.profile-menu.show {
			display:block;
		}
		.hero {
			background-image: url('../../resources/sign_up.jpg');
			background-size: cover;
			background-position: center;
			background-attachment: fixed;
			min-height: 50vh;
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
			background: rgba(0, 0, 0, 0.55);
			z-index: 1;
		}
		.hero-overlay {
			position: absolute;
			inset: 0;
			background: transparent;
			z-index: 1;
		}
		.order-card {
			background: #e0f2fe;
			border-radius: 12px;
			box-shadow: 0 4px 20px rgba(0,0,0,0.08);
			margin-bottom: 1.25rem;
			overflow: hidden;
		}
		.order-header {
			background: linear-gradient(135deg, #0ea5e9 0%, #0284c7 100%);
			padding: 1.1rem;
			color: white;
		}
		.order-body {
			padding: 1.25rem 1.5rem;
		}
		.status-badge {
			display: inline-block;
			padding: 0.35rem 0.9rem;
			border-radius: 16px;
			font-size: 0.85rem;
			font-weight: 600;
			background: #d1fae5;
			color: #065f46;
		}
		.item-row {
			display: flex;
			justify-content: space-between;
			padding: 0.65rem 0;
			border-bottom: 1px solid #dbeafe;
		}
		.item-row:last-child {
			border-bottom: none;
		}
		.btn {
			padding: 0.55rem 1.1rem;
			border-radius: 0.375rem;
			font-weight: 600;
			cursor: pointer;
			border: none;
			transition: all 0.2s ease;
		}
		.btn-primary {
			background: #0284c7;
			color: white;
		}
		.btn-primary:hover {
			background: #0369a1;
		}
		.summary-card {
			background: white;
			border-radius: 12px;
			padding: 1rem 1.25rem;
			box-shadow: 0 4px 16px rgba(0,0,0,0.08);
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
				<a href="./navbar.php" class="px-4 py-2 rounded text-sm hover:bg-red-600 hover:bg-opacity-80">Home</a>
				<a href="./statistics.php" class="px-4 py-2 rounded text-sm hover:bg-red-600 hover:bg-opacity-80">Statistics</a>
				<a href="./product.php" class="px-4 py-2 rounded text-sm hover:bg-red-600 hover:bg-opacity-80">Product</a>
				<a href="./invoice.php" class="px-4 py-2 rounded text-sm bg-red-600 bg-opacity-80">Invoice</a>
				<a href="./orders.php" class="px-4 py-2 rounded text-sm hover:bg-red-600 hover:bg-opacity-80">Order</a>
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
			<h1 class="text-4xl sm:text-5xl font-extrabold mb-4">Invoices</h1>
			<p class="text-lg text-gray-200">Delivered orders and downloadable receipts</p>
		</div>
	</section>

	<main class="max-w-7xl mx-auto px-4 py-10">
		<div class="grid md:grid-cols-3 gap-4 mb-8">
			<div class="summary-card">
				<p class="text-sm text-gray-500">Delivered Orders</p>
				<p class="text-3xl font-bold text-gray-900 mt-1"><?= (int)$totalOrders ?></p>
			</div>
			<div class="summary-card">
				<p class="text-sm text-gray-500">Total Revenue (Tk)</p>
				<p class="text-3xl font-bold text-gray-900 mt-1"><?= number_format((float)$totalRevenue, 2) ?></p>
			</div>
			<div class="summary-card">
				<p class="text-sm text-gray-500">Last Update</p>
				<p class="text-lg font-semibold text-gray-800 mt-1"><?= date('M d, Y h:i A') ?></p>
			</div>
		</div>

		<?php if (count($orders) > 0): ?>
			<div id="invoicesContainer">
				<?php foreach ($orders as $order): ?>
					<div id="invoice-<?= (int)$order['order_id'] ?>" class="order-card">
						<div class="order-header">
							<div class="flex justify-between items-start flex-wrap gap-3">
								<div>
									<h3 class="text-xl font-bold mb-1">Invoice #<?= htmlspecialchars($order['order_number']) ?></h3>
									<p class="text-sm opacity-90">Customer: <?= htmlspecialchars($order['fullname'] ?? $order['username']) ?></p>
									<?php if (!empty($order['phone_number'])): ?>
										<p class="text-sm opacity-90">Phone: <?= htmlspecialchars($order['phone_number']) ?></p>
									<?php endif; ?>
								</div>
								<div class="text-right">
									<span class="status-badge">Delivered</span>
									<p class="text-sm mt-2 opacity-90"><?= date('M d, Y h:i A', strtotime($order['order_date'])) ?></p>
								</div>
							</div>
						</div>

						<div class="order-body">
							<div class="grid md:grid-cols-2 gap-6">
								<div>
									<h4 class="font-bold text-lg mb-3 text-gray-800">Items</h4>
									<?php if (!empty($order['items'])): ?>
										<?php foreach ($order['items'] as $item): ?>
											<div class="item-row">
												<div>
													<p class="font-semibold text-gray-900"><?= htmlspecialchars($item['product_name']) ?></p>
													<p class="text-sm text-gray-600">Tk <?= number_format((float)$item['price'], 2) ?> × <?= (int)$item['quantity'] ?></p>
												</div>
												<div class="font-bold text-gray-900">
													Tk <?= number_format((float)$item['subtotal'], 2) ?>
												</div>
											</div>
										<?php endforeach; ?>
									<?php else: ?>
										<p class="text-gray-600">No items found</p>
									<?php endif; ?>

									<div class="mt-4 pt-4 border-t-2 border-sky-200">
										<div class="flex justify-between mb-2">
											<span class="text-gray-700">Subtotal:</span>
											<span class="font-semibold text-gray-900">Tk <?= number_format((float)$order['subtotal'], 2) ?></span>
										</div>
										<div class="flex justify-between mb-2">
											<span class="text-gray-700">Delivery Fee:</span>
											<span class="font-semibold text-green-600">
												<?php if ((float)$order['delivery_fee'] == 0): ?>
													FREE
												<?php else: ?>
													Tk <?= number_format((float)$order['delivery_fee'], 2) ?>
												<?php endif; ?>
											</span>
										</div>
										<div class="flex justify-between text-lg font-bold text-sky-700">
											<span>Total:</span>
											<span>Tk <?= number_format((float)$order['total_amount'], 2) ?></span>
										</div>
									</div>
								</div>

								<div>
									<h4 class="font-bold text-lg mb-3 text-gray-800">Delivery & Payment</h4>
									<div class="space-y-3">
										<div>
											<p class="text-sm text-gray-600 font-semibold">Delivery Address:</p>
											<p class="text-gray-900"><?= nl2br(htmlspecialchars($order['delivery_address'])) ?></p>
										</div>
										<div>
											<p class="text-sm text-gray-600 font-semibold">Payment Method:</p>
											<p class="text-gray-900 capitalize"><?= str_replace('_', ' ', htmlspecialchars($order['payment_method'])) ?></p>
										</div>
										<?php if (!empty($order['special_instructions'])): ?>
											<div>
												<p class="text-sm text-gray-600 font-semibold">Special Instructions:</p>
												<p class="text-gray-900"><?= nl2br(htmlspecialchars($order['special_instructions'])) ?></p>
											</div>
										<?php endif; ?>
									</div>

									<div class="mt-6 flex gap-3">
										<button class="btn btn-primary" onclick="printInvoice(<?= (int)$order['order_id'] ?>)">Download Receipt</button>
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
				<h3 class="mt-4 text-xl font-semibold text-gray-900">No Delivered Orders</h3>
				<p class="mt-2 text-gray-600">Delivered orders will appear here with downloadable receipts.</p>
			</div>
		<?php endif; ?>

		<?php if ($totalPages > 1): ?>
			<div class="mt-8 flex justify-center items-center gap-2">
				<?php if ($currentPage > 1): ?>
					<a href="?page=<?= $currentPage - 1 ?>" class="px-4 py-2 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 font-semibold text-gray-700">Previous</a>
				<?php endif; ?>

				<?php
				$startPage = max(1, $currentPage - 2);
				$endPage = min($totalPages, $currentPage + 2);
				
				if ($startPage > 1): ?>
					<a href="?page=1" class="px-4 py-2 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 font-semibold text-gray-700">1</a>
					<?php if ($startPage > 2): ?>
						<span class="px-2 text-gray-500">...</span>
					<?php endif; ?>
				<?php endif; ?>

				<?php for ($i = $startPage; $i <= $endPage; $i++): ?>
					<?php if ($i == $currentPage): ?>
						<span class="px-4 py-2 bg-blue-600 text-white rounded-lg font-bold"><?= $i ?></span>
					<?php else: ?>
						<a href="?page=<?= $i ?>" class="px-4 py-2 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 font-semibold text-gray-700"><?= $i ?></a>
					<?php endif; ?>
				<?php endfor; ?>

				<?php if ($endPage < $totalPages): ?>
					<?php if ($endPage < $totalPages - 1): ?>
						<span class="px-2 text-gray-500">...</span>
					<?php endif; ?>
					<a href="?page=<?= $totalPages ?>" class="px-4 py-2 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 font-semibold text-gray-700"><?= $totalPages ?></a>
				<?php endif; ?>

				<?php if ($currentPage < $totalPages): ?>
					<a href="?page=<?= $currentPage + 1 ?>" class="px-4 py-2 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 font-semibold text-gray-700">Next</a>
				<?php endif; ?>
			</div>
			<div class="text-center mt-4 text-gray-600">
				Showing page <?= $currentPage ?> of <?= $totalPages ?> (<?= $totalOrders ?> total invoices)
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

		// Print single invoice
		function printInvoice(orderId) {
			const card = document.getElementById('invoice-' + orderId);
			if (!card) return;

			const printWindow = window.open('', 'PRINT', 'height=800,width=800');
			printWindow.document.write('<html><head><title>Invoice #' + orderId + '</title>');
			printWindow.document.write('<style>body{font-family:Arial,sans-serif;padding:20px;background:#f8fafc;color:#0f172a;}h2{margin:0 0 10px;} .section{margin-bottom:16px;} .row{display:flex;justify-content:space-between;border-bottom:1px solid #e2e8f0;padding:6px 0;} .row:last-child{border-bottom:none;} .badge{background:#22c55e;color:#fff;padding:4px 10px;border-radius:12px;font-weight:600;} .totals{font-weight:700;} </style>');
			printWindow.document.write('</head><body>');
			printWindow.document.write(card.innerHTML);
			printWindow.document.write('</body></html>');
			printWindow.document.close();
			printWindow.focus();
			printWindow.print();
			printWindow.close();
		}
	</script>
</body>
</html>
