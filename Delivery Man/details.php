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

// Check if viewing specific invoice
$viewInvoice = isset($_GET['invoice']) && isset($_GET['order_id']);
$selectedOrder = null;
$invoiceItems = [];

if ($viewInvoice) {
    $orderId = (int)$_GET['order_id'];
    
    // Fetch order details
    $stmt = $conn->prepare("SELECT o.*, c.fullname, c.phone AS customer_phone, c.address AS customer_address, c.email
        FROM orders o
        LEFT JOIN customer c ON o.customer_id = c.customer_id
        WHERE o.order_id = ? AND o.order_status = 'delivered'");
    if ($stmt) {
        $stmt->bind_param('i', $orderId);
        $stmt->execute();
        $result = $stmt->get_result();
        $selectedOrder = $result->fetch_assoc();
        $stmt->close();
    }
    
    if ($selectedOrder) {
        // Fetch order items
        $stmt = $conn->prepare("SELECT * FROM order_items WHERE order_id = ?");
        if ($stmt) {
            $stmt->bind_param('i', $orderId);
            $stmt->execute();
            $result = $stmt->get_result();
            while ($item = $result->fetch_assoc()) {
                $invoiceItems[] = $item;
            }
            $stmt->close();
        }
    }
}

// Fetch all delivered orders for THIS delivery man only
$orders = [];
$sql = "SELECT o.*, c.fullname, c.phone AS customer_phone, c.address AS customer_address
  FROM orders o
  LEFT JOIN customer c ON o.customer_id = c.customer_id
  WHERE o.order_status = 'delivered'
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
            while ($it = $ir->fetch_assoc()) {
                $items[] = $it;
            }
            $is->close();
        }
        $row['items'] = $items;
        $orders[] = $row;
    }
}

// Calculate statistics
$totalOrders = count($orders);
$totalAmount = 0;
foreach ($orders as $o) {
    foreach ($o['items'] as $it) {
        $totalAmount += ($it['price'] * $it['quantity']);
    }
}

// If viewing invoice, display invoice layout
if ($viewInvoice && $selectedOrder) {
    $orderPhone = $selectedOrder['phone'] ?? $selectedOrder['phone_number'] ?? $selectedOrder['customer_phone'] ?? 'N/A';
    $orderAddress = $selectedOrder['address'] ?? $selectedOrder['delivery_address'] ?? $selectedOrder['customer_address'] ?? 'N/A';
    $subtotal = 0;
    foreach ($invoiceItems as $item) {
        $subtotal += ($item['price'] * $item['quantity']);
    }
    $deliveryFee = $selectedOrder['delivery_fee'] ?? 0;
    $total = $subtotal + $deliveryFee;
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Invoice - Order #<?= htmlspecialchars($selectedOrder['order_number']) ?></title>
        <script src="https://cdn.tailwindcss.com"></script>
        <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
        <style>
            @media print {
                .no-print { display: none; }
                body { background: white; }
            }
        </style>
    </head>
    <body class="m-0 p-0">
        <div class="max-w-4xl mx-auto bg-white">
            <!-- Header -->
            <div class="bg-gradient-to-r from-red-600 to-red-700 text-white p-8">
                <div class="flex justify-between items-start">
                    <div>
                        <h1 class="text-4xl font-extrabold mb-2" style="margin: 0;">FOOD WAVE</h1>
                        <p class="text-red-100" style="margin: 5px 0 0 0;">Delivery Invoice</p>
                    </div>
                    <div class="text-right">
                        <div class="text-sm text-red-100 mb-1" style="margin-bottom: 5px;">Invoice Date</div>
                        <div class="text-xl font-bold"><?= date('M d, Y', strtotime($selectedOrder['order_date'])) ?></div>
                    </div>
                </div>
            </div>

            <!-- Order Info -->
            <div class="p-8" style="padding: 2rem;">
                <div class="grid md:grid-cols-2 gap-8 mb-8">
                    <div>
                        <h3 class="text-lg font-bold text-gray-900 mb-3 border-b-2 border-red-600 pb-1">Customer Details</h3>
                        <div class="space-y-2 text-sm">
                            <div><span class="font-semibold text-gray-700">Name:</span> <span class="text-gray-900"><?= htmlspecialchars($selectedOrder['fullname'] ?? 'N/A') ?></span></div>
                            <div><span class="font-semibold text-gray-700">Phone:</span> <span class="text-gray-900"><?= htmlspecialchars($orderPhone) ?></span></div>
                            <div><span class="font-semibold text-gray-700">Email:</span> <span class="text-gray-900"><?= htmlspecialchars($selectedOrder['email'] ?? 'N/A') ?></span></div>
                            <div><span class="font-semibold text-gray-700">Address:</span> <span class="text-gray-900"><?= htmlspecialchars($orderAddress) ?></span></div>
                        </div>
                    </div>
                    <div>
                        <h3 class="text-lg font-bold text-gray-900 mb-3 border-b-2 border-red-600 pb-1">Order Information</h3>
                        <div class="space-y-2 text-sm">
                            <div><span class="font-semibold text-gray-700">Order Number:</span> <span class="bg-blue-100 text-blue-800 px-2 py-1 rounded font-bold"><?= htmlspecialchars($selectedOrder['order_number']) ?></span></div>
                            <div><span class="font-semibold text-gray-700">Order Date:</span> <span class="text-gray-900"><?= date('F j, Y - g:i A', strtotime($selectedOrder['order_date'])) ?></span></div>
                            <?php if (!empty($selectedOrder['kitchen'])): ?>
                            <div><span class="font-semibold text-gray-700">Kitchen:</span> <span class="bg-orange-100 text-orange-800 px-2 py-1 rounded font-bold"><?= htmlspecialchars($selectedOrder['kitchen']) ?> Kitchen</span></div>
                            <?php endif; ?>
                            <div><span class="font-semibold text-gray-700">Status:</span> <span class="bg-green-100 text-green-800 px-2 py-1 rounded font-bold">Delivered</span></div>
                            <?php if (!empty($selectedOrder['payment_method'])): ?>
                            <div><span class="font-semibold text-gray-700">Payment:</span> <span class="text-gray-900"><?= htmlspecialchars($selectedOrder['payment_method']) ?></span></div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Items Table -->
                <div class="mb-8">
                    <h3 class="text-lg font-bold text-gray-900 mb-3 border-b-2 border-red-600 pb-1">Order Items</h3>
                    <table class="w-full">
                        <thead>
                            <tr class="bg-gray-100 border-b-2 border-gray-300">
                                <th class="text-left p-3 font-semibold text-gray-700">Item</th>
                                <th class="text-center p-3 font-semibold text-gray-700">Quantity</th>
                                <th class="text-right p-3 font-semibold text-gray-700">Price</th>
                                <th class="text-right p-3 font-semibold text-gray-700">Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($invoiceItems as $item): ?>
                            <tr class="border-b border-gray-200">
                                <td class="p-3 text-gray-900"><?= htmlspecialchars($item['product_name']) ?></td>
                                <td class="p-3 text-center text-gray-700"><?= (int)$item['quantity'] ?></td>
                                <td class="p-3 text-right text-gray-700">৳<?= number_format($item['price'], 2) ?></td>
                                <td class="p-3 text-right font-semibold text-gray-900">৳<?= number_format($item['price'] * $item['quantity'], 2) ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Totals -->
                <div class="flex justify-end">
                    <div class="w-64 space-y-2">
                        <div class="flex justify-between text-sm">
                            <span class="text-gray-700">Subtotal:</span>
                            <span class="font-semibold text-gray-900">৳<?= number_format($subtotal, 2) ?></span>
                        </div>
                        <?php if ($deliveryFee > 0): ?>
                        <div class="flex justify-between text-sm">
                            <span class="text-gray-700">Delivery Fee:</span>
                            <span class="font-semibold text-gray-900">৳<?= number_format($deliveryFee, 2) ?></span>
                        </div>
                        <?php endif; ?>
                        <div class="flex justify-between text-lg font-bold border-t-2 border-gray-300 pt-2">
                            <span class="text-gray-900">Total:</span>
                            <span class="text-red-600">৳<?= number_format($total, 2) ?></span>
                        </div>
                    </div>
                </div>

                <?php if (!empty($selectedOrder['special_instructions'])): ?>
                <div class="mt-6 p-4 bg-yellow-50 border-l-4 border-yellow-400 rounded">
                    <h4 class="font-semibold text-gray-900 mb-1">Special Instructions:</h4>
                    <p class="text-sm text-gray-700"><?= nl2br(htmlspecialchars($selectedOrder['special_instructions'])) ?></p>
                </div>
                <?php endif; ?>
            </div>

            <!-- Footer -->
            <div class="bg-gray-50 p-6 text-center text-sm text-gray-600 border-t" style="border-top: 1px solid #e5e7eb;">
                <p class="mb-2" style="margin: 0 0 8px 0;">Thank you for choosing Food Wave!</p>
                <p style="margin: 0;">For any queries, please contact us at support@foodwave.com</p>
            </div>

            <!-- Action Buttons -->
            <div class="no-print p-6 bg-gray-100 flex justify-center gap-4">
                <button onclick="downloadPDF()" class="px-6 py-3 bg-red-600 text-white rounded-lg font-semibold hover:bg-red-700 transition-colors flex items-center gap-2">
                    <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M3 17a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zm3.293-7.707a1 1 0 011.414 0L9 10.586V3a1 1 0 112 0v7.586l1.293-1.293a1 1 0 111.414 1.414l-3 3a1 1 0 01-1.414 0l-3-3a1 1 0 010-1.414z" clip-rule="evenodd"/>
                    </svg>
                    Download PDF
                </button>
                <a href="details.php" class="px-6 py-3 bg-gray-600 text-white rounded-lg font-semibold hover:bg-gray-700 transition-colors inline-block">
                    Back to Details
                </a>
            </div>

            <script>
                function downloadPDF() {
                    const element = document.querySelector('.max-w-4xl');
                    const opt = {
                        margin: 10,
                        filename: 'Invoice_Order_<?= htmlspecialchars($selectedOrder['order_number']) ?>.pdf',
                        image: { type: 'jpeg', quality: 0.98 },
                        html2canvas: { scale: 2 },
                        jsPDF: { orientation: 'portrait', unit: 'mm', format: 'a4' }
                    };
                    html2pdf().set(opt).from(element).save();
                }
            </script>
        </div>
    </body>
    </html>
    <?php
    exit;
}

require_once 'navbar.php';
?>
<style>
  body { background: #f3f4f6; color: #1f2937; }
  .stat-card { background: linear-gradient(135deg, #3b82f6 0%, #1d4ed8 100%); }
  .order-card { background: white; transition: transform 0.2s, box-shadow 0.2s; }
  .order-card:hover { transform: translateY(-4px); box-shadow: 0 20px 25px -5px rgba(0,0,0,0.1); }
</style>

<main class="max-w-7xl mx-auto px-4 py-8">
  <div class="mb-8">
    <h1 class="text-4xl font-extrabold text-gray-900 mb-2">Delivery History</h1>
    <p class="text-gray-600">View all your delivered orders and statistics</p>
  </div>

  <!-- Statistics Cards -->
  <div class="grid md:grid-cols-2 gap-6 mb-8">
    <div class="stat-card rounded-2xl p-8 text-white shadow-2xl">
      <div class="flex items-center justify-between">
        <div>
          <div class="text-blue-100 text-sm font-medium mb-1">Total Orders Delivered</div>
          <div class="text-5xl font-extrabold"><?= $totalOrders ?></div>
        </div>
        <div class="bg-white bg-opacity-20 rounded-full p-4">
          <svg class="w-12 h-12" fill="currentColor" viewBox="0 0 20 20">
            <path d="M9 2a1 1 0 000 2h2a1 1 0 100-2H9z"/>
            <path fill-rule="evenodd" d="M4 5a2 2 0 012-2 3 3 0 003 3h2a3 3 0 003-3 2 2 0 012 2v11a2 2 0 01-2 2H6a2 2 0 01-2-2V5zm3 4a1 1 0 000 2h6a1 1 0 100-2H7zm0 4a1 1 0 100 2h6a1 1 0 100-2H7z" clip-rule="evenodd"/>
          </svg>
        </div>
      </div>
    </div>

    <div class="stat-card rounded-2xl p-8 text-white shadow-2xl">
      <div class="flex items-center justify-between">
        <div>
          <div class="text-blue-100 text-sm font-medium mb-1">Total Amount Delivered</div>
          <div class="text-5xl font-extrabold">৳<?= number_format($totalAmount, 2) ?></div>
        </div>
        <div class="bg-white bg-opacity-20 rounded-full p-4">
          <svg class="w-12 h-12" fill="currentColor" viewBox="0 0 20 20">
            <path d="M8.433 7.418c.155-.103.346-.196.567-.267v1.698a2.305 2.305 0 01-.567-.267C8.07 8.34 8 8.114 8 8c0-.114.07-.34.433-.582zM11 12.849v-1.698c.22.071.412.164.567.267.364.243.433.468.433.582 0 .114-.07.34-.433.582a2.305 2.305 0 01-.567.267z"/>
            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-13a1 1 0 10-2 0v.092a4.535 4.535 0 00-1.676.662C6.602 6.234 6 7.009 6 8c0 .99.602 1.765 1.324 2.246.48.32 1.054.545 1.676.662v1.941c-.391-.127-.68-.317-.843-.504a1 1 0 10-1.51 1.31c.562.649 1.413 1.076 2.353 1.253V15a1 1 0 102 0v-.092a4.535 4.535 0 001.676-.662C13.398 13.766 14 12.991 14 12c0-.99-.602-1.765-1.324-2.246A4.535 4.535 0 0011 9.092V7.151c.391.127.68.317.843.504a1 1 0 101.511-1.31c-.563-.649-1.413-1.076-2.354-1.253V5z" clip-rule="evenodd"/>
          </svg>
        </div>
      </div>
    </div>
  </div>

  <!-- Orders List -->
  <div class="mb-4">
    <h2 class="text-2xl font-bold text-gray-900">Delivered Orders</h2>
  </div>

  <?php if (empty($orders)): ?>
    <div class="bg-white p-8 rounded-2xl shadow text-center text-gray-500">
      No delivered orders yet.
    </div>
  <?php else: ?>
    <div class="space-y-4">
      <?php foreach ($orders as $o): ?>
        <?php 
          $orderTotal = 0; 
          foreach ($o['items'] as $it) { 
            $orderTotal += ($it['price'] * $it['quantity']); 
          }
          $orderPhone = $o['phone'] ?? $o['phone_number'] ?? $o['customer_phone'] ?? 'N/A';
          $orderAddress = $o['address'] ?? $o['delivery_address'] ?? $o['customer_address'] ?? 'N/A';
        ?>
        <div class="order-card p-6 rounded-2xl shadow-lg border border-gray-100">
          <div class="flex flex-wrap justify-between items-start gap-4">
            <div class="flex-1 min-w-0">
              <div class="flex items-center gap-3 mb-3">
                <div class="text-2xl font-extrabold text-gray-900">Order #<?= htmlspecialchars($o['order_number']) ?></div>
                <span class="px-3 py-1 rounded-full bg-green-100 text-green-800 text-sm font-semibold">Delivered</span>
              </div>
              <div class="grid sm:grid-cols-2 gap-2 text-sm text-gray-700">
                <div><span class="font-semibold">Customer:</span> <?= htmlspecialchars($o['fullname'] ?? '') ?></div>
                <div><span class="font-semibold">Phone:</span> <?= htmlspecialchars($orderPhone) ?></div>
                <div><span class="font-semibold">Address:</span> <?= htmlspecialchars($orderAddress) ?></div>
                <div><span class="font-semibold">Delivered:</span> <?= htmlspecialchars($o['order_date']) ?></div>
                <?php if (!empty($o['kitchen'])): ?>
                <div><span class="font-semibold">Kitchen:</span> <?= htmlspecialchars($o['kitchen']) ?></div>
                <?php endif; ?>
                <div><span class="font-semibold">Total:</span> <span class="text-lg font-bold text-gray-900">৳<?= number_format($orderTotal, 2) ?></span></div>
              </div>

              <!-- Items List -->
              <div class="mt-4 pt-4 border-t border-gray-200">
                <div class="font-semibold text-gray-900 mb-2 text-sm">Order Items:</div>
                <div class="space-y-1">
                  <?php foreach ($o['items'] as $it): ?>
                    <div class="flex justify-between text-sm">
                      <span class="text-gray-700"><?= htmlspecialchars($it['product_name']) ?> × <?= (int)$it['quantity'] ?></span>
                      <span class="font-semibold text-gray-900">৳<?= number_format($it['price'] * $it['quantity'], 2) ?></span>
                    </div>
                  <?php endforeach; ?>
                </div>
              </div>
            </div>

            <!-- View Invoice Button -->
            <div class="flex flex-col gap-2">
              <a href="details.php?invoice=1&order_id=<?= (int)$o['order_id'] ?>" 
                 class="px-6 py-3 bg-gradient-to-r from-red-600 to-red-700 text-white rounded-lg font-semibold shadow-lg hover:from-red-700 hover:to-red-800 transition-all flex items-center gap-2">
                <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
                  <path d="M9 2a1 1 0 000 2h2a1 1 0 100-2H9z"/>
                  <path fill-rule="evenodd" d="M4 5a2 2 0 012-2 3 3 0 003 3h2a3 3 0 003-3 2 2 0 012 2v11a2 2 0 01-2 2H6a2 2 0 01-2-2V5zm3 4a1 1 0 000 2h6a1 1 0 100-2H7zm0 4a1 1 0 100 2h6a1 1 0 100-2H7z" clip-rule="evenodd"/>
                </svg>
                View Invoice
              </a>
            </div>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</main>

</body>
</html>
