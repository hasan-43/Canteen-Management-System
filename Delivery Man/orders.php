<?php
session_start();
$hideHero = true;
require_once __DIR__ . '/../config/db_connection.php';

// Guard
if (!isset($_SESSION['usertype']) || $_SESSION['usertype'] !== 'delivery') {
    header('Location: ../login.php');
    exit;
}

$deliveryId = (int)($_SESSION['delivery_id'] ?? 0);
$message = '';
// Handle status updates by delivery man
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['order_id'], $_POST['order_status'])) {
    $orderId = (int)$_POST['order_id'];
    $newStatus = $_POST['order_status'];
    if ($orderId > 0 && in_array($newStatus, ['picked_up','out_for_delivery','delivered'], true)) {
        $stmt = $conn->prepare("UPDATE orders SET order_status = ? WHERE order_id = ?");
        if ($stmt) {
            $stmt->bind_param('si', $newStatus, $orderId);
            if ($stmt->execute()) {
                $message = 'Order status updated.';
            } else {
                $message = 'Failed to update status: '.$stmt->error;
            }
            $stmt->close();
        }
    }
}

// Fetch orders that are confirmed (visible to delivery) or already assigned to this delivery
// Exclude delivered orders as they appear in Details page
$orders = [];
$sql = "SELECT o.*, c.fullname, c.phone AS customer_phone, c.address AS customer_address
  FROM orders o
  LEFT JOIN customer c ON o.customer_id = c.customer_id
  WHERE o.order_status IN ('confirmed','out_for_delivery','picked_up')
  ORDER BY o.order_date DESC";
$res = $conn->query($sql);
if ($res && $res->num_rows > 0) {
    while ($row = $res->fetch_assoc()) {
        // fetch items
        $items = [];
        $is = $conn->prepare("SELECT product_name, price, quantity FROM order_items WHERE order_id = ?");
        if ($is) {
            $is->bind_param('i', $row['order_id']);
            $is->execute();
            $ir = $is->get_result();
            while ($it = $ir->fetch_assoc()) { $items[] = $it; }
            $is->close();
        }
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
  <title>Delivery Orders</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <style>
    .orders-hero { position: relative; background-image: url('../resources/sign_up.jpg'); background-size: cover; background-position: center; min-height: 240px; display:flex; align-items:center; justify-content:center; overflow:hidden; }
    .orders-hero::after { content:''; position:absolute; inset:0; background: rgba(0,0,0,0.55); }
    .orders-hero h1 { position:relative; z-index:1; color:#fff; font-size:2.25rem; font-weight:800; text-shadow: 0 2px 12px rgba(0,0,0,0.35); }
  </style>
</head>
<body class="bg-gray-50">
  <?php include __DIR__ . '/navbar.php'; ?>
  <section class="orders-hero">
    <h1>Orders to Deliver</h1>
  </section>
  <main class="max-w-6xl mx-auto p-4">
    <?php if ($message): ?><div class="mb-4 p-3 bg-green-100 text-green-800 rounded"><?= htmlspecialchars($message) ?></div><?php endif; ?>
    <?php if (empty($orders)): ?>
      <div class="bg-white p-4 rounded shadow">No orders yet.</div>
    <?php else: ?>
      <div class="space-y-4">
        <?php foreach ($orders as $o): ?>
          <?php $orderTotal = 0; foreach ($o['items'] as $it) { $orderTotal += ($it['price'] * $it['quantity']); } ?>
          <?php
            $kitchenName = $o['kitchen_name'] ?? ($o['restaurant_name'] ?? ($o['canteen_name'] ?? ''));
            $paymentMethod = $o['payment_method'] ?? ($o['payment_type'] ?? '');
          ?>
          <div class="bg-white p-5 rounded-2xl shadow-2xl border border-gray-100">
            <div class="flex justify-between items-start gap-4">
              <div>
                <div class="text-xl font-extrabold tracking-tight text-gray-900">Order #<?= htmlspecialchars($o['order_number']) ?></div>
                <div class="text-sm text-gray-700">Ordered: <?= htmlspecialchars($o['order_date']) ?></div>
                <div class="text-sm font-semibold text-gray-900">Total: ৳<?= number_format($orderTotal, 2) ?></div>
              </div>
              <div class="text-right text-sm flex flex-col items-end gap-2">
                <div class="px-3 py-1 rounded-full bg-gray-100 text-gray-800 font-semibold shadow">Status: <?= htmlspecialchars($o['order_status']) ?></div>
                <div class="flex gap-2">
                  <button type="button" data-target="details-<?= (int)$o['order_id'] ?>" class="details-toggle px-3 py-1 rounded bg-gray-900 text-white text-xs shadow hover:opacity-90">Details</button>
                  <form method="post" class="flex gap-2 items-center text-sm">
                  <input type="hidden" name="order_id" value="<?= (int)$o['order_id'] ?>">
                  <select name="order_status" class="border px-2 py-1 rounded">
                    <option value="picked_up">Picked Up</option>
                    <option value="out_for_delivery">Out for Delivery</option>
                    <option value="delivered">Delivered</option>
                  </select>
                  <button class="px-3 py-1 bg-red-600 text-white rounded">Update</button>
                  </form>
                </div>
              </div>
            </div>
              <div id="details-<?= (int)$o['order_id'] ?>" class="mt-4 hidden border-t border-gray-200 pt-4">
              <div class="grid sm:grid-cols-2 gap-4 text-sm text-gray-800">
                <div>
                  <div class="font-semibold text-gray-900 mb-1">Customer Details</div>
                  <div>Name: <?= htmlspecialchars($o['fullname'] ?? '') ?></div>
                  <?php $orderPhone = $o['phone'] ?? $o['phone_number'] ?? $o['customer_phone'] ?? 'N/A'; ?>
                  <div>Phone: <?= htmlspecialchars($orderPhone) ?></div>
                  <?php $orderAddress = $o['address'] ?? $o['delivery_address'] ?? $o['customer_address'] ?? 'N/A'; ?>
                  <div>Address: <?= htmlspecialchars($orderAddress) ?></div>
                  <?php if (!empty($o['kitchen'])): ?><div>Kitchen: <?= htmlspecialchars($o['kitchen']) ?></div><?php endif; ?>
                  <div>Order Date: <?= htmlspecialchars($o['order_date']) ?></div>
                </div>
                <div>
                  <div class="font-semibold text-gray-900 mb-1">Order Summary</div>
                  <div>Status: <?= htmlspecialchars($o['order_status']) ?></div>
                  <?php if (!empty($specialInstructions)): ?><div>Notes: <?= nl2br(htmlspecialchars($specialInstructions)) ?></div><?php endif; ?>
                  <div>Total: ৳<?= number_format($orderTotal, 2) ?></div>
                  <div>Items: <?= count($o['items']) ?></div>
                </div>
              </div>
              <div class="mt-3">
                <div class="font-semibold text-gray-900 mb-2">Items</div>
                <?php foreach ($o['items'] as $it): ?>
                  <div class="flex justify-between text-sm border-b py-1">
                    <span class="font-semibold text-gray-900"><?= htmlspecialchars($it['product_name']) ?></span>
                    <span class="text-gray-700">× <?= (int)$it['quantity'] ?> • ৳<?= number_format($it['price'], 2) ?></span>
                  </div>
                <?php endforeach; ?>
              </div>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </main>
  <script>
    // Toggle detail sections
    document.addEventListener('click', function(e){
      const btn = e.target.closest('.details-toggle');
      if (!btn) return;
      const targetId = btn.getAttribute('data-target');
      const panel = document.getElementById(targetId);
      if (panel) {
        panel.classList.toggle('hidden');
      }
    });
  </script>
</body>
</html>