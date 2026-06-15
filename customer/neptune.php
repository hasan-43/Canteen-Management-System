<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
session_start();
require_once __DIR__ . '/../config/db_connection.php';
require_once __DIR__ . '/../config/hd_helper.php';

// Check login status
$isLoggedIn = isset($_SESSION['usertype']) && $_SESSION['usertype'] === 'customer';

// Fetch products from neptune table in database with ratings
$catalog = [];
$dbError = '';
$sql = "SELECT n.neptune_id, n.product_code, n.name, n.price, n.category, n.image, n.stock, n.is_available,
        COALESCE(AVG(pr.rating), 0) as avg_rating,
        COUNT(pr.review_id) as review_count
        FROM neptune n
        LEFT JOIN product_reviews pr ON n.product_code = pr.product_code AND pr.kitchen = 'neptune'
        WHERE n.is_available = 1
        GROUP BY n.neptune_id, n.product_code, n.name, n.price, n.category, n.image, n.stock, n.is_available
        ORDER BY n.category, n.name";
$result = $conn->query($sql);
if (!$result) {
    $dbError = "Database Error: " . $conn->error . "<br><strong>Did you import neptune_table.sql?</strong>";
} elseif ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $catalog[] = [
            'id'           => $row['product_code'],
            'name'         => $row['name'],
            'price'        => (float)$row['price'],
            'category'     => $row['category'],
            'image'        => $row['image'],
            'stock'        => (int)$row['stock'],
            'avg_rating'   => (float)$row['avg_rating'],
            'review_count' => (int)$row['review_count']
        ];
    }
} else {
    $dbError = "No products found in neptune table. Please import neptune_table.sql";
}

$categories = [
    'all'    => 'All',
    'mains'  => 'Main Food',
    'drinks' => 'Drinks',
    'sides'  => 'Snacks & Sides',
];

// Session inventory + cart (always reload from DB for real-time sync with admin changes)
$_SESSION['inventory'] = [];
foreach ($catalog as $item) {
    $_SESSION['inventory'][$item['id']] = $item['stock'];
}
if (!isset($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}

$alert = '';
// Handle AJAX cart addition
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax']) && isset($_POST['add_to_cart'])) {
    header('Content-Type: application/json');
    $response = ['success' => false, 'message' => ''];
    
    $itemId = $_POST['item_id'] ?? '';
    $qty = max(1, (int)($_POST['qty'] ?? 1));
    $customerId = (int)($_SESSION['user_id'] ?? 0);
    
    if (!$customerId) {
        $response['message'] = 'Please log in to add items to cart';
        echo json_encode($response);
        exit;
    }
    
    $item = null;
    foreach ($catalog as $c) {
        if ($c['id'] === $itemId) { $item = $c; break; }
    }
    
    if ($item) {
      $conn->begin_transaction();

      $stmt = $conn->prepare("SELECT stock FROM neptune WHERE product_code = ? FOR UPDATE");
      $stmt->bind_param("s", $itemId);
      $stmt->execute();
      $stmt->bind_result($currentStock);
      $hasRow = $stmt->fetch();
      $stmt->close();

      if (!$hasRow) {
        $conn->rollback();
        $response['message'] = 'Item not found';
        echo json_encode($response);
        exit;
      }

      if ($qty > $currentStock) {
        $conn->rollback();
        $response['message'] = "Only {$currentStock} left for {$item['name']}";
        echo json_encode($response);
        exit;
      }

      // Get or create cart for customer inside the same transaction
      $stmt = $conn->prepare("SELECT cart_id FROM customer_cart WHERE customer_id = ? LIMIT 1");
      $stmt->bind_param("i", $customerId);
      $stmt->execute();
      $result = $stmt->get_result();
        
      if ($result->num_rows > 0) {
        $cart = $result->fetch_assoc();
        $cartId = $cart['cart_id'];
      } else {
        $stmt = $conn->prepare("INSERT INTO customer_cart (customer_id) VALUES (?)");
        $stmt->bind_param("i", $customerId);
        $stmt->execute();
        $cartId = $conn->insert_id;
      }
      $stmt->close();
        
      // Check if item already in cart
      $stmt = $conn->prepare("SELECT item_id, quantity FROM cart_items WHERE cart_id = ? AND product_code = ? AND kitchen = 'neptune'");
      $stmt->bind_param("is", $cartId, $itemId);
      $stmt->execute();
      $result = $stmt->get_result();
        
      if ($result->num_rows > 0) {
        // Update existing item
        $existingItem = $result->fetch_assoc();
        $newQty = $existingItem['quantity'] + $qty;
        $stmt = $conn->prepare("UPDATE cart_items SET quantity = ? WHERE item_id = ?");
        $stmt->bind_param("ii", $newQty, $existingItem['item_id']);
        $stmt->execute();
      } else {
        // Insert new item
        $stmt = $conn->prepare("INSERT INTO cart_items (cart_id, product_code, product_name, kitchen, price, quantity) VALUES (?, ?, ?, 'neptune', ?, ?)");
        $stmt->bind_param("issdi", $cartId, $itemId, $item['name'], $item['price'], $qty);
        $stmt->execute();
      }
      $stmt->close();
        
      // Decrease stock in database with guard to prevent negative stock
      $stmt = $conn->prepare("UPDATE neptune SET stock = stock - ? WHERE product_code = ? AND stock >= ?");
      $stmt->bind_param("isi", $qty, $itemId, $qty);
      $stmt->execute();

      if ($stmt->affected_rows === 0) {
        $stmt->close();
        $conn->rollback();
        $response['message'] = "Only {$currentStock} left for {$item['name']}";
        echo json_encode($response);
        exit;
      }

      $stmt->close();
      $conn->commit();

      $remaining = max(0, $currentStock - $qty);
      $_SESSION['inventory'][$itemId] = $remaining;
      $response['success'] = true;
      $response['message'] = "Added {$qty} × {$item['name']} to cart";
      $response['stock'] = $remaining;
    } else {
      $response['message'] = 'Item not found';
    }
    
    echo json_encode($response);
    exit;
}

$activeCategory = $_GET['category'] ?? 'all';

function initials($name) {
    $parts = preg_split('/\s+/', trim($name));
    $letters = '';
    foreach ($parts as $p) {
        if ($p !== '') $letters .= strtoupper(substr($p, 0, 1));
        if (strlen($letters) >= 2) break;
    }
    return $letters ?: 'C';
}
$displayName = $_SESSION['fullname'] ?? $_SESSION['username'] ?? 'Guest';
$profilePic = $_SESSION['profile_pic'] ?? null;
$initials = initials($displayName);

$shops = [];
$shopResult = $conn->query("SELECT shop_id, shop_name FROM shop ORDER BY shop_id");
if ($shopResult) {
    while ($row = $shopResult->fetch_assoc()) {
        $shops[] = [
            'name'         => $row['shop_name'],
            'display_name' => ucfirst($row['shop_name']) . ' Kitchen',
        ];
    }
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <script src="../resources/js/theme.js"></script>
  <meta name="viewport" content="width=device-width,initial-scale=1" />
  <title>Neptune Kitchen</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <style>
    header { position: fixed; top: 0; left: 0; right: 0; z-index: 50; background: rgba(10, 10, 12, 0.9) !important; backdrop-filter: blur(12px) !important; -webkit-backdrop-filter: blur(12px) !important; border-bottom: 1px solid rgba(255, 255, 255, 0.1) !important; }
    .logo-section { position: absolute; left: 2rem; top: 50%; transform: translateY(-50%); }
    .logo-section a { display: flex; align-items: center; gap: 0.75rem; text-decoration: none; }
    .brand-text { font-size: 1.25rem; font-weight: 800; color: #ffffff; letter-spacing: 0.05em; transition: color 0.3s ease; }
    .logo-section a:hover .brand-text { color: #ef4444; }
    .profile-section { position: absolute; right: 2rem; top: 50%; transform: translateY(-50%); }
    .nav-buttons { display: none; }
    @media (min-width: 768px) { .nav-buttons { display: flex; position: absolute; left: 50%; top: 50%; transform: translate(-50%, -50%); gap: 1rem; } }
    .hero {
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
      top: 0; left: -100%; width: 100%; height: 100%;
      background: inherit;
      background-size: cover;
      background-position: center;
      animation: slideInLeft 1.5s ease-in-out forwards;
      z-index: -1;
    }
    @keyframes slideInLeft { from { left: -100%; } to { left: 0%; } }
    .hero-overlay { position:absolute; inset:0; background:rgba(0,0,0,0.55); z-index:1; }

    .campus-cravings-logo {
      height: 50px;
      width: auto;
      transition: transform 0.3s ease;
    }
    .campus-cravings-logo:hover {
      transform: scale(1.05);
    }

    .initials-circle { width: 42px; height: 42px; border-radius:9999px; background: linear-gradient(135deg,#ef4444,#7f1d1d); display:inline-flex; align-items:center; justify-content:center; color:#fff; font-weight:700; }
    .profile-menu.show { display:block; }
    
    /* Toast notification */
    .toast { position:fixed; top:5rem; right:1.5rem; z-index:9999; min-width:300px; max-width:400px; padding:1rem 1.25rem; border-radius:0.5rem; box-shadow:0 10px 25px -5px rgba(0,0,0,0.2); display:flex; align-items:center; gap:0.75rem; transform:translateX(500px); transition:transform 0.3s ease-in-out; }
    .toast.show { transform:translateX(0); }
    .toast.success { background:#10b981; color:#fff; }
    .toast.error { background:#ef4444; color:#fff; }
    .toast-icon { font-size:1.5rem; }
    .toast-close { margin-left:auto; cursor:pointer; opacity:0.8; font-size:1.5rem; line-height:1; }
    .toast-close:hover { opacity:1; }

    /* Shared navbar styles (match dashboard/navbar) */
    .nav-buttons { display:flex; align-items:center; gap:0.75rem; }
    .nav-link { padding: 0.5rem 0.9rem; border-radius: 0.5rem; font-weight: 600; color: #111827; }
    .nav-link:hover { background-color: rgba(248,113,113,0.12); color: #b91c1c; }
    .nav-dropdown { position: relative; }
    .nav-dropdown-menu { position:absolute; left:0; margin-top:0.4rem; width:12rem; background:rgba(0,0,0,0.9); border:1px solid #374151; border-radius:0.5rem; box-shadow:0 10px 25px -10px rgba(0,0,0,0.5); opacity:0; transform:translateY(-4px) scale(0.98); transition: all 0.15s ease; pointer-events:none; }
    .nav-dropdown:hover .nav-dropdown-menu { opacity:1; transform:translateY(0) scale(1); pointer-events:auto; }
    .nav-dropdown-menu a { display:block; padding:0.55rem 0.9rem; color:#ffffff; border-radius:0.5rem; }
    .nav-dropdown-menu a:hover { background:#374151; }
    @media (max-width: 768px) {
      .nav-buttons { flex-wrap:wrap; justify-content:flex-start; }
    }

    /* Star rating styles */
    .star-rating { display: flex; align-items: center; gap: 0.25rem; font-size: 0.875rem; }
    .star-rating .stars { color: #fbbf24; display: flex; }
    .star-rating .star { font-size: 1rem; }
    .star-rating .count { color: #6b7280; margin-left: 0.25rem; }
    
    /* Review Modal */
    .modal { display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.7); overflow: auto; }
    .modal.show { display: block; }
    .modal-content { background-color: #fff; margin: 3% auto; padding: 0; border-radius: 12px; width: 90%; max-width: 700px; box-shadow: 0 20px 60px rgba(0,0,0,0.3); max-height: 85vh; overflow: hidden; display: flex; flex-direction: column; }
    .modal-header { padding: 1.5rem; border-bottom: 2px solid #f3f4f6; background: linear-gradient(135deg, #dc2626, #991b1b); color: white; }
    .modal-header h2 { margin: 0; font-size: 1.5rem; font-weight: 700; }
    .modal-close { color: white; float: right; font-size: 28px; font-weight: bold; cursor: pointer; line-height: 1; }
    .modal-close:hover { opacity: 0.7; }
    .modal-body { padding: 1.5rem; overflow-y: auto; flex: 1; }
    .review-item { border-bottom: 1px solid #e5e7eb; padding: 1rem 0; }
    .review-item:last-child { border-bottom: none; }
    .review-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.5rem; }
    .review-author { font-weight: 600; color: #111827; }
    .review-date { color: #6b7280; font-size: 0.875rem; }
    .review-stars { color: #fbbf24; font-size: 1.1rem; margin-bottom: 0.5rem; }
    .review-text { color: #374151; line-height: 1.6; }
    .no-reviews { text-align: center; padding: 3rem 1rem; color: #9ca3af; }
    .review-summary { background: #fef3c7; border-left: 4px solid #fbbf24; padding: 1rem; margin-bottom: 1rem; border-radius: 0.5rem; }
    .review-summary-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 1rem; }
    .review-summary-item { text-align: center; }
    .review-summary-label { font-size: 0.875rem; color: #92400e; margin-bottom: 0.25rem; }
    .review-summary-value { font-size: 1.5rem; font-weight: 700; color: #92400e; }

    /* Auth buttons for guest users */
    .auth-btn { padding: .5rem 1.1rem; border-radius: .5rem; font-weight: 600; font-size: .85rem; transition: all .2s ease; text-decoration: none; display: inline-block; }
    .auth-btn-outline { border: 2px solid rgba(255,255,255,.4); color: #fff; background: transparent; }
    .auth-btn-outline:hover { border-color: #fff; background: rgba(255,255,255,.1); }
    .auth-btn-solid { background: linear-gradient(135deg, #ef4444, #dc2626); color: #fff; border: none; }
    .auth-btn-solid:hover { filter: brightness(1.15); }

    /* Login prompt modal */
    .login-modal-overlay { display:none; position:fixed; inset:0; background:rgba(0,0,0,.7); z-index:9999; align-items:center; justify-content:center; }
    .login-modal-overlay.show { display:flex; }
    .login-modal { background:#1f2937; border:1px solid #374151; border-radius:1rem; padding:2.5rem; max-width:400px; width:90%; text-align:center; box-shadow:0 25px 50px rgba(0,0,0,.5); }
    .login-modal h3 { color:#fff; font-size:1.4rem; font-weight:700; margin-bottom:.75rem; }
    .login-modal p { color:#9ca3af; margin-bottom:1.5rem; font-size:.95rem; }
    .login-modal .btn-row { display:flex; gap:.75rem; justify-content:center; }
    .login-modal .btn-row a { padding:.7rem 1.5rem; border-radius:.5rem; font-weight:600; text-decoration:none; transition:all .2s; }
    .login-modal .btn-login { background:linear-gradient(135deg,#ef4444,#dc2626); color:#fff; }
    .login-modal .btn-login:hover { filter:brightness(1.15); }
    .login-modal .btn-cancel { background:#374151; color:#d1d5db; }
    .login-modal .btn-cancel:hover { background:#4b5563; }
  </style>
</head>
<body class="bg-white text-gray-900">
  <div id="toast" class="toast"></div>
  <header>
    <div class="relative h-16 max-w-7xl mx-auto px-4">
      <div class="logo-section">
        <a href="./navbar.php">
          <img src="../resources/logo.jpg" alt="Campus Cravings" class="campus-cravings-logo" />
          <span class="brand-text">Campus Cravings</span>
        </a>
      </div>
      <nav class="nav-buttons">
        <a href="<?= $isLoggedIn ? './navbar.php' : '../index.php' ?>" class="px-4 py-2 bg-transparent hover:bg-red-600 hover:bg-opacity-80 rounded text-sm text-white">Home</a>
        <div class="relative group">
          <button type="button" class="px-4 py-2 rounded text-sm hover:bg-red-600 hover:bg-opacity-80 text-white">Shop</button>
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
        <a href="./cart.php" class="px-4 py-2 rounded text-sm hover:bg-red-600 hover:bg-opacity-80 text-white">Cart</a>
      </nav>
      <div class="profile-section">
        <?php if ($isLoggedIn): ?>
        <div class="relative" id="profileRoot">
          <button id="profileBtn" type="button" class="flex items-center gap-2 p-1 rounded profile-btn focus:outline-none">
            <?php if (!empty($profilePic)): ?>
              <img src="<?= htmlspecialchars($profilePic) ?>" alt="Profile" class="w-10 h-10 rounded-full object-cover border border-gray-600" />
            <?php else: ?>
              <div class="initials-circle"><?= htmlspecialchars($initials) ?></div>
            <?php endif; ?>
            <span class="hidden sm:inline-block text-sm text-white"><?= htmlspecialchars($displayName) ?></span>
          </button>
          <div id="profileMenu" class="profile-menu hidden absolute right-0 mt-2 w-44 bg-black bg-opacity-95 rounded shadow-lg border border-gray-800 text-left">
            <a href="./profile.php" class="block px-4 py-2 hover:bg-gray-700 text-white">Profile</a>
            <a href="./logout.php" class="block px-4 py-2 text-red-400 hover:bg-gray-700">Log out</a>
          </div>
        </div>
        <?php else: ?>
        <div class="flex items-center gap-3">
          <a href="../login.php" class="auth-btn auth-btn-outline">Log In</a>
          <a href="../sign_up.php" class="auth-btn auth-btn-solid">Sign Up</a>
        </div>
        <?php endif; ?>
      </div>
    </div>
  </header>

  <section id="hero" class="hero" style="background-image: url('../resources/sign_up.jpg');">
    <div class="hero-overlay"></div>
    <div class="relative z-10 max-w-4xl mx-auto px-4 py-24 text-center text-white">
      <h1 class="text-4xl sm:text-5xl font-extrabold mb-4">Neptune Kitchen</h1>
      <p class="text-lg text-gray-200 mb-6">Fresh, quick, and tasty meals — pick your favorites below.</p>
      <?php if ($dbError): ?>
        <div class="max-w-xl mx-auto bg-red-600 text-white font-semibold px-4 py-3 rounded shadow"><?= $dbError ?></div>
      <?php elseif ($alert): ?>
        <div class="max-w-xl mx-auto bg-white/80 text-gray-900 font-semibold px-4 py-3 rounded shadow"><?= htmlspecialchars($alert) ?></div>
      <?php endif; ?>
    </div>
  </section>

  <main class="max-w-7xl mx-auto px-4 py-12">
    <div class="grid md:grid-cols-4 gap-8">
      <!-- Sidebar Categories -->
      <aside class="md:col-span-1">
        <div class="sticky top-24 bg-gray-50 rounded-2xl p-6 border border-gray-200 shadow-sm">
          <h2 class="text-xl font-bold text-gray-800 mb-4 pb-2 border-b border-gray-200">Categories</h2>
          <div class="flex flex-col gap-2">
            <?php foreach ($categories as $cat => $label): ?>
              <a href="?category=<?= urlencode($cat) ?>" 
                 class="px-4 py-3 rounded-xl font-semibold transition text-left <?= $activeCategory === $cat ? 'bg-red-500 text-white shadow-md shadow-red-500/20' : 'text-gray-600 hover:bg-red-50 hover:text-red-500' ?>">
                <?= $label ?>
              </a>
            <?php endforeach; ?>
          </div>
        </div>
      </aside>

      <!-- Products Grid -->
      <div class="md:col-span-3">
        <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-6">
          <?php 
          $hasItems = false;
          foreach ($catalog as $item): 
            if ($activeCategory !== 'all' && $item['category'] !== $activeCategory) continue;
            $hasItems = true;
            $stock = $_SESSION['inventory'][$item['id']] ?? 0;
          ?>
            <div class="bg-white rounded-[2rem] border border-gray-100/90 shadow-sm hover:shadow-2xl hover:-translate-y-1.5 transition duration-500 p-4 flex flex-col justify-between h-full">
              <div class="relative p-2.5 bg-gray-50/60 rounded-[1.5rem] border border-gray-100/80 overflow-hidden flex-shrink-0 mb-4">
                <div class="aspect-[4/3] w-full rounded-[1.25rem] overflow-hidden bg-white relative">
                  <img src="<?= getHDProductImage($item['name'], 'neptune', $item['image']) ?>" alt="<?= htmlspecialchars($item['name']) ?>" class="w-full h-full object-cover transition-transform duration-500 hover:scale-110" />
                </div>
                <span class="absolute top-5 right-5 bg-white/90 backdrop-blur px-3 py-1.5 rounded-full text-xs font-extrabold text-gray-800 shadow-sm capitalize tracking-wider">
                  <?= $item['category'] ?>
                </span>
              </div>
              
              <div class="px-2 flex-grow flex flex-col justify-between">
                <div>
                  <h3 class="font-extrabold text-gray-950 text-xl tracking-tight mb-1"><?= htmlspecialchars($item['name']) ?></h3>
                  <!-- Star Ratings -->
                  <div class="mb-4 flex items-center gap-2">
                    <div class="star-rating">
                      <span class="stars">
                        <?php 
                        $stars = round($item['avg_rating']); 
                        for ($i = 1; $i <= 5; $i++) {
                          echo $i <= $stars ? '<span class="star">★</span>' : '<span class="star text-gray-300">★</span>';
                        }
                        ?>
                      </span>
                      <?php if ($item['review_count'] > 0): ?>
                        <span class="count text-xs font-semibold text-gray-500">(<?= $item['review_count'] ?>)</span>
                      <?php else: ?>
                        <span class="count text-xs font-semibold text-red-500">New</span>
                      <?php endif; ?>
                    </div>
                    
                    <button class="view-reviews-btn text-xs text-blue-500 hover:text-blue-700 underline font-semibold ml-2" 
                            data-product-code="<?= htmlspecialchars($item['id']) ?>" 
                            data-product-name="<?= htmlspecialchars($item['name']) ?>">
                      Reviews
                    </button>
                  </div>
                </div>
                
                <div>
                  <div class="flex items-center justify-between mb-4 pt-3 border-t border-gray-100">
                    <span class="text-2xl font-black text-gray-900">৳<?= number_format($item['price'], 0) ?></span>
                    <span class="text-xs font-bold px-2.5 py-1 rounded-full <?= $stock > 0 ? 'bg-green-50 text-green-700 border border-green-200' : 'bg-red-50 text-red-600 border border-red-200' ?>">
                      <?= $stock > 0 ? $stock . ' in stock' : 'Out of stock' ?>
                    </span>
                  </div>
                  
                  <?php if ($stock > 0): ?>
                    <button onclick="addToCart('<?= htmlspecialchars($item['id']) ?>', 1)" 
                            class="w-full py-3.5 bg-gradient-to-r from-red-600 to-red-500 hover:from-red-500 hover:to-red-400 text-white font-extrabold rounded-2xl transition duration-300 shadow-lg shadow-red-500/20 text-sm tracking-wider uppercase">
                      Add to Cart
                    </button>
                  <?php else: ?>
                    <button disabled class="w-full py-3.5 bg-gray-100 text-gray-400 font-extrabold rounded-2xl cursor-not-allowed text-sm tracking-wider uppercase">
                      Sold Out
                    </button>
                  <?php endif; ?>
                </div>
              </div>
            </div>
          <?php endforeach; ?>

          <?php if (!$hasItems): ?>
            <div class="col-span-full py-16 text-center text-gray-500 font-semibold bg-gray-50 rounded-2xl border border-dashed border-gray-300">
              No items available in this category.
            </div>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </main>

  <!-- Review Modal -->
  <div id="reviewModal" class="modal">
    <div class="modal-content">
      <div class="modal-header">
        <span class="modal-close">&times;</span>
        <h2 id="modalProductName">Product Reviews</h2>
      </div>
      <div class="modal-body" id="modalBody">
        <div class="text-center py-8">
          <div class="inline-block animate-spin rounded-full h-12 w-12 border-b-2 border-red-600"></div>
          <p class="mt-2 text-gray-600">Loading reviews...</p>
        </div>
      </div>
    </div>
  </div>

  <!-- Login Prompt Modal for guests -->
  <div id="loginModal" class="login-modal-overlay">
    <div class="login-modal">
      <h3>🔒 Login Required</h3>
      <p>You need to log in or create an account to add items to your cart.</p>
      <div class="btn-row">
        <a href="../login.php" class="btn-login">Log In</a>
        <a href="../sign_up.php" class="btn-login" style="background:linear-gradient(135deg,#10b981,#059669);">Sign Up</a>
        <button onclick="document.getElementById('loginModal').classList.remove('show')" class="btn-cancel">Cancel</button>
      </div>
    </div>
  </div>

  <script>
    const isLoggedIn = <?= $isLoggedIn ? 'true' : 'false' ?>;

    // Toast notification
    function showToast(message, type) {
      const toast = document.getElementById('toast');
      toast.className = 'toast ' + type;
      toast.innerHTML = `
        <span class="toast-icon">${type === 'success' ? '✓' : '✗'}</span>
        <span>${message}</span>
        <span class="toast-close" onclick="hideToast()">×</span>
      `;
      toast.classList.add('show');
      setTimeout(hideToast, 3500);
    }
    
    function hideToast() {
      document.getElementById('toast').classList.remove('show');
    }
    
    // Add To Cart AJAX Logic
    function addToCart(itemId, qty) {
      if (!isLoggedIn) {
        document.getElementById('loginModal').classList.add('show');
        return;
      }
      
      const formData = new FormData();
      formData.append('ajax', '1');
      formData.append('add_to_cart', '1');
      formData.append('item_id', itemId);
      formData.append('qty', qty);
      
      fetch(window.location.href, {
        method: 'POST',
        body: formData
      })
      .then(res => res.json())
      .then(data => {
        if (data.success) {
          showToast(data.message, 'success');
          // Reload page to refresh inventory stock states
          setTimeout(() => window.location.reload(), 1200);
        } else {
          showToast(data.message, 'error');
        }
      })
      .catch(() => showToast('Error adding to cart', 'error'));
    }
    
    (function() {
      const profileBtn = document.getElementById('profileBtn');
      const profileMenu = document.getElementById('profileMenu');
      if (profileBtn && profileMenu) {
        profileBtn.addEventListener('click', function(e) {
          e.stopPropagation();
          profileMenu.classList.toggle('show');
        });
        document.addEventListener('click', function() { profileMenu.classList.remove('show'); });
      }
    })();
    
    // Review Modal functionality
    const reviewModal = document.getElementById('reviewModal');
    const modalClose = document.querySelector('.modal-close');
    const modalBody = document.getElementById('modalBody');
    const modalProductName = document.getElementById('modalProductName');
    
    // Open modal when "View Reviews" button is clicked
    document.querySelectorAll('.view-reviews-btn').forEach(btn => {
      btn.addEventListener('click', function() {
        const productCode = this.dataset.productCode;
        const productName = this.dataset.productName;
        
        modalProductName.textContent = `Reviews for ${productName}`;
        reviewModal.classList.add('show');
        
        // Show loading state
        modalBody.innerHTML = `
          <div class="text-center py-8">
            <div class="inline-block animate-spin rounded-full h-12 w-12 border-b-2 border-red-600"></div>
            <p class="mt-2 text-gray-600">Loading reviews...</p>
          </div>
        `;
        
        // Fetch reviews
        fetch(`../api/reviews.php?product_code=${encodeURIComponent(productCode)}&kitchen=neptune`)
          .then(res => res.json())
          .then(data => {
            if (data.success) {
              displayReviews(data.reviews, data.stats);
            } else {
              modalBody.innerHTML = `<div class="no-reviews">${data.message || 'Failed to load reviews'}</div>`;
            }
          })
          .catch(err => {
            modalBody.innerHTML = '<div class="no-reviews">Error loading reviews. Please try again.</div>';
          });
      });
    });
    
    // Close modal
    modalClose.addEventListener('click', function() {
      reviewModal.classList.remove('show');
    });
    
    window.addEventListener('click', function(e) {
      if (e.target === reviewModal) {
        reviewModal.classList.remove('show');
      }
    });
    
    // Display reviews in modal
    function displayReviews(reviews, stats) {
      if (!reviews || reviews.length === 0) {
        modalBody.innerHTML = '<div class="no-reviews"><i class="fas fa-star-half-alt" style="font-size:3rem;color:#d1d5db;"></i><p class="mt-3">No reviews yet for this product</p></div>';
        return;
      }
      
      let html = '';
      
      // Add summary
      if (stats) {
        html += `
          <div class="review-summary">
            <div class="review-summary-grid">
              <div class="review-summary-item">
                <div class="review-summary-label">Average Rating</div>
                <div class="review-summary-value">${parseFloat(stats.avg_rating).toFixed(1)} ★</div>
              </div>
              <div class="review-summary-item">
                <div class="review-summary-label">Total Reviews</div>
                <div class="review-summary-value">${stats.total_reviews}</div>
              </div>
            </div>
          </div>
        `;
      }
      
      // Add individual reviews
      reviews.forEach(review => {
        const stars = '★'.repeat(review.rating) + '☆'.repeat(5 - review.rating);
        html += `
          <div class="review-item">
            <div class="review-header">
              <span class="review-author">${escapeHtml(review.customer_name)}</span>
              <span class="review-date">${review.review_date}</span>
            </div>
            <div class="review-stars">${stars}</div>
            ${review.review_text ? `<div class="review-text">${escapeHtml(review.review_text)}</div>` : ''}
          </div>
        `;
      });
      
      modalBody.innerHTML = html;
    }
    
    function escapeHtml(text) {
      const div = document.createElement('div');
      div.textContent = text;
      return div.innerHTML;
    }

    (function() {
      const hero = document.getElementById('hero');
      const images = [
        '../resources/sign_up.jpg'
      ];
      images.forEach(src => { const img = new Image(); img.src = src; });
      let i = 0;
      hero.style.backgroundImage = `url('${images[0]}')`;
      setInterval(() => {
        i = (i + 1) % images.length;
        hero.style.backgroundImage = `url('${images[i]}')`;
      }, 2000);
    })();
  </script>
  <script src="../resources/js/notifications.js"></script>
</body>
</html>
