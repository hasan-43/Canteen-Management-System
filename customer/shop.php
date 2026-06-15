<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
session_start();
require_once __DIR__ . '/../config/db_connection.php';
require_once __DIR__ . '/../config/hd_helper.php';

// Check login status
$isLoggedIn = isset($_SESSION['usertype']) && $_SESSION['usertype'] === 'customer';

// Get and sanitize shop name
$shopTable = preg_replace('/[^a-zA-Z0-9_]/', '', $_GET['name'] ?? '');

if (empty($shopTable)) {
    header('Location: ../index.php');
    exit;
}

// Validate shop exists in db
$stmt = $conn->prepare("SELECT shop_name FROM shop WHERE shop_name = ? LIMIT 1");
$stmt->bind_param("s", $shopTable);
$stmt->execute();
$result = $stmt->get_result();
if ($result->num_rows === 0) {
    header('Location: ../index.php');
    exit;
}
$shopInfo = $result->fetch_assoc();
$shopDbName = $shopInfo['shop_name'];
$shopDisplayName = ucfirst($shopDbName) . ' Kitchen';

// Fetch all registered shops for navbar dropdown
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

// Fetch products from this shop's table in database with ratings
$catalog = [];
$dbError = '';
$sql = "SELECT k.`{$shopDbName}_id` AS pid, k.product_code, k.name, k.price, k.category, k.image, k.stock, k.is_available,
        COALESCE(AVG(pr.rating), 0) as avg_rating,
        COUNT(pr.review_id) as review_count
        FROM `{$shopDbName}` k
        LEFT JOIN product_reviews pr ON k.product_code = pr.product_code AND pr.kitchen = '{$shopDbName}'
        WHERE k.is_available = 1
        GROUP BY k.`{$shopDbName}_id`, k.product_code, k.name, k.price, k.category, k.image, k.stock, k.is_available
        ORDER BY k.category, k.name";
$result = $conn->query($sql);
if (!$result) {
    $dbError = "Database Error: " . $conn->error;
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
    $dbError = "No products found in this restaurant.";
}

$categories = [
    'all'    => 'All',
    'mains'  => 'Main Food',
    'drinks' => 'Drinks',
    'sides'  => 'Snacks & Sides',
];

// Session inventory + cart (no DB persistence yet)
// Always refresh inventory from database on page load
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

      $stmt = $conn->prepare("SELECT stock FROM `{$shopDbName}` WHERE product_code = ? FOR UPDATE");
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
      $stmt = $conn->prepare("SELECT item_id, quantity FROM cart_items WHERE cart_id = ? AND product_code = ? AND kitchen = ?");
      $stmt->bind_param("iss", $cartId, $itemId, $shopDbName);
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
        $stmt = $conn->prepare("INSERT INTO cart_items (cart_id, product_code, product_name, kitchen, price, quantity) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("isssdi", $cartId, $itemId, $item['name'], $shopDbName, $item['price'], $qty);
        $stmt->execute();
      }
      $stmt->close();
        
      // Decrease stock in database with guard to prevent negative stock
      $stmt = $conn->prepare("UPDATE `{$shopDbName}` SET stock = stock - ? WHERE product_code = ? AND stock >= ?");
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
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <script src="../resources/js/theme.js"></script>
  <meta name="viewport" content="width=device-width,initial-scale=1" />
  <title><?= htmlspecialchars($shopDisplayName) ?></title>
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
      background-image: url('../resources/sign_up.jpg');
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
      top: 0; left: 0; width: 100%; height: 100%;
      background: rgba(0, 0, 0, 0.5);
      z-index: 1;
    }
    .hero-overlay { position:absolute; inset:0; background:transparent; z-index:1; }

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

  <!-- Toast Container -->
  <div id="toast" class="toast">
    <span id="toastIcon" class="toast-icon"></span>
    <span id="toastMsg"></span>
    <span class="toast-close" onclick="closeToast()">&times;</span>
  </div>

  <!-- Login Modal Overlay (for guests clicking add-to-cart) -->
  <div id="loginPromptModal" class="login-modal-overlay">
    <div class="login-modal">
      <h3>Login Required</h3>
      <p>Please log in to add items to your cart and place orders.</p>
      <div class="btn-row">
        <a href="../login.php" class="btn-login">Log In</a>
        <button onclick="closeLoginModal()" class="btn-cancel">Cancel</button>
      </div>
    </div>
  </div>

  <!-- ═══════════ HEADER ═══════════ -->
  <header>
    <div class="relative h-16 max-w-7xl mx-auto px-4">
      <div class="logo-section">
        <a href="<?= $isLoggedIn ? './navbar.php' : '../index.php' ?>">
          <img src="../resources/logo.jpg" alt="Campus Cravings" class="campus-cravings-logo" />
          <span class="brand-text">Campus Cravings</span>
        </a>
      </div>

      <nav class="nav-buttons">
        <a href="<?= $isLoggedIn ? './navbar.php' : '../index.php' ?>" class="px-4 py-2 bg-transparent hover:bg-red-600 hover:bg-opacity-80 rounded text-sm text-white font-semibold">Home</a>
        <div class="relative group">
          <button class="px-4 py-2 rounded text-sm bg-red-600 bg-opacity-80 text-white font-semibold transition">Shop</button>
          <div class="absolute left-0 mt-2 w-48 bg-black bg-opacity-95 border border-gray-800 rounded shadow-lg opacity-0 group-hover:opacity-100 transform scale-95 group-hover:scale-100 transition-all origin-top z-50">
            <?php foreach ($shops as $s): 
              $sName = htmlspecialchars($s['name']);
              if (in_array(strtolower($sName), ['khans', 'olympia', 'neptune'])) {
                  $shopUrl = "./{$sName}.php";
              } else {
                  $shopUrl = "./shop.php?name=" . urlencode($sName);
              }
            ?>
              <a href="<?= $shopUrl ?>" class="block px-4 py-2 hover:bg-gray-700 text-white"><?= htmlspecialchars($s['display_name']) ?></a>
            <?php endforeach; ?>
          </div>
        </div>
        <?php if ($isLoggedIn): ?>
          <a href="./invoice.php" class="px-4 py-2 rounded text-sm hover:bg-red-600 hover:bg-opacity-80 text-white transition">Invoice</a>
          <a href="./chat.php" class="px-4 py-2 rounded text-sm hover:bg-red-600 hover:bg-opacity-80 text-white transition">Chat</a>
          <a href="./cart.php" class="px-4 py-2 rounded text-sm hover:bg-red-600 hover:bg-opacity-80 text-white transition">Cart</a>
        <?php endif; ?>
      </nav>

      <div class="profile-section">
        <?php if ($isLoggedIn): ?>
          <div class="relative" id="profileRoot">
            <button id="profileBtn" class="flex items-center gap-2 p-1 rounded focus:outline-none">
              <?php if (!empty($profilePic)): ?>
                <img src="<?= htmlspecialchars($profilePic) ?>" alt="Profile" class="w-10 h-10 rounded-full object-cover border border-gray-600" />
              <?php else: ?>
                <div class="initials-circle"><?= htmlspecialchars($initials) ?></div>
              <?php endif; ?>
              <span class="hidden sm:inline-block text-sm text-white"><?= htmlspecialchars($displayName) ?></span>
            </button>
            <div id="profileMenu" class="profile-menu hidden absolute right-0 mt-2 w-48 bg-gray-900 bg-opacity-95 rounded-lg border border-gray-800 text-left">
              <a href="./profile.php" class="block px-4 py-2.5 text-gray-100 hover:bg-gray-800 hover:bg-opacity-70">Profile</a>
              <a href="./logout.php" class="block px-4 py-2.5 text-red-200 hover:bg-gray-800 hover:bg-opacity-70">Log out</a>
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

  <!-- ═══════════ HERO ═══════════ -->
  <section class="hero">
    <div class="hero-overlay"></div>
    <div class="relative z-10 max-w-4xl mx-auto px-4 py-24 text-center text-white">
      <h1 class="text-4xl sm:text-5xl font-extrabold mb-4"><?= htmlspecialchars($shopDisplayName) ?></h1>
      <p class="text-lg text-gray-200">Campus Canteen - Quality Food Delivered Quick</p>
    </div>
  </section>

  <!-- ═══════════ MAIN CONTENT ═══════════ -->
  <main class="max-w-7xl mx-auto px-4 py-12">
    <?php if ($dbError): ?>
      <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded-lg mb-6 shadow-sm">
        <?= $dbError ?>
      </div>
    <?php endif; ?>

    <div class="grid md:grid-cols-4 gap-8">
      <!-- Sidebar Categories -->
      <aside class="md:col-span-1">
        <div class="sticky top-24 bg-gray-50 rounded-2xl p-6 border border-gray-200 shadow-sm">
          <h2 class="text-xl font-bold text-gray-800 mb-4 pb-2 border-b border-gray-200">Categories</h2>
          <div class="flex flex-col gap-2">
            <?php foreach ($categories as $cat => $label): ?>
              <a href="?name=<?= urlencode($shopDbName) ?>&category=<?= $cat ?>" 
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
          ?>
            <div class="bg-white rounded-[2rem] border border-gray-100/90 shadow-sm hover:shadow-2xl hover:-translate-y-1.5 transition duration-500 p-4 flex flex-col justify-between h-full">
              <div class="relative p-2.5 bg-gray-50/60 rounded-[1.5rem] border border-gray-100/80 overflow-hidden flex-shrink-0 mb-4">
                <div class="aspect-[4/3] w-full rounded-[1.25rem] overflow-hidden bg-white relative">
                  <img src="<?= getHDProductImage($item['name'], $shopDbName, $item['image']) ?>" alt="<?= htmlspecialchars($item['name']) ?>" class="w-full h-full object-cover transition-transform duration-500 hover:scale-110" />
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
                    <span class="text-xs font-bold px-2.5 py-1 rounded-full <?= $item['stock'] > 0 ? 'bg-green-50 text-green-700 border border-green-200' : 'bg-red-50 text-red-600 border border-red-200' ?>">
                      <?= $item['stock'] > 0 ? $item['stock'] . ' in stock' : 'Out of stock' ?>
                    </span>
                  </div>
                  
                  <?php if ($item['stock'] > 0): ?>
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

  <!-- Product Reviews Modal -->
  <div id="reviewModal" class="modal">
    <div class="modal-content">
      <div class="modal-header">
        <span class="modal-close">&times;</span>
        <h2 id="modalProductName">Reviews</h2>
      </div>
      <div id="modalBody" class="modal-body">
        <!-- Loaded via AJAX -->
      </div>
    </div>
  </div>


  <!-- ═══════════ SCRIPTS ═══════════ -->
  <script>
    const isLoggedIn = <?= json_encode($isLoggedIn) ?>;
    const shopNameVal = "<?= $shopDbName ?>";

    // Profile menu dropdown toggle
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

    // Modal Control
    const reviewModal = document.getElementById('reviewModal');
    const modalClose = document.querySelector('.modal-close');
    const modalBody = document.getElementById('modalBody');
    const modalProductName = document.getElementById('modalProductName');
    
    document.querySelectorAll('.view-reviews-btn').forEach(btn => {
      btn.addEventListener('click', function() {
        const productCode = this.dataset.productCode;
        const productName = this.dataset.productName;
        
        modalProductName.textContent = `Reviews for ${productName}`;
        reviewModal.classList.add('show');
        
        modalBody.innerHTML = `
          <div class="text-center py-8">
            <div class="inline-block animate-spin rounded-full h-12 w-12 border-b-2 border-red-600"></div>
            <p class="mt-2 text-gray-600">Loading reviews...</p>
          </div>
        `;
        
        fetch(`../api/reviews.php?product_code=${encodeURIComponent(productCode)}&kitchen=${shopNameVal}`)
          .then(res => res.json())
          .then(data => {
            if (data.success) displayReviews(data.reviews, data.stats);
            else modalBody.innerHTML = `<div class="no-reviews">${data.message || 'Failed to load reviews'}</div>`;
          })
          .catch(() => {
            modalBody.innerHTML = '<div class="no-reviews">Error loading reviews. Please try again.</div>';
          });
      });
    });

    modalClose.addEventListener('click', () => reviewModal.classList.remove('show'));
    window.addEventListener('click', (e) => { if (e.target === reviewModal) reviewModal.classList.remove('show'); });

    function displayReviews(reviews, stats) {
      if (!reviews || reviews.length === 0) {
        modalBody.innerHTML = '<div class="no-reviews"><i class="fas fa-star-half-alt" style="font-size:3rem;color:#d1d5db;"></i><p class="mt-3">No reviews yet for this product</p></div>';
        return;
      }
      let html = '';
      if (stats) {
        html += `
          <div class="review-summary">
            <div class="review-summary-grid">
              <div class="review-summary-item">
                <div class="review-summary-label">Average Rating</div>
                <div class="review-summary-value">${parseFloat(stats.average_rating).toFixed(1)} ⭐</div>
              </div>
              <div class="review-summary-item">
                <div class="review-summary-label">Total Reviews</div>
                <div class="review-summary-value">${stats.total_reviews}</div>
              </div>
            </div>
          </div>
        `;
      }
      reviews.forEach(r => {
        let starsHtml = '';
        for (let i = 1; i <= 5; i++) {
          starsHtml += i <= r.rating ? '★' : '☆';
        }
        const author = r.fullname || r.username || 'Anonymous';
        const date = new Date(r.created_at).toLocaleDateString();
        const text = r.review_text ? `<p class="review-text">${r.review_text}</p>` : '';
        html += `
          <div class="review-item">
            <div class="review-header">
              <span class="review-author">${author}</span>
              <span class="review-date">${date}</span>
            </div>
            <div class="review-stars">${starsHtml}</div>
            ${text}
          </div>
        `;
      });
      modalBody.innerHTML = html;
    }

    // Add To Cart AJAX Logic
    function addToCart(itemId, qty) {
      if (!isLoggedIn) {
        document.getElementById('loginPromptModal').classList.add('show');
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

    function closeLoginModal() { document.getElementById('loginPromptModal').classList.remove('show'); }
    
    // Toast Controls
    function showToast(msg, type) {
      const toast = document.getElementById('toast');
      const msgEl = document.getElementById('toastMsg');
      const iconEl = document.getElementById('toastIcon');
      
      toast.className = `toast ${type} show`;
      msgEl.textContent = msg;
      iconEl.textContent = type === 'success' ? '✓' : '✗';
      
      setTimeout(closeToast, 3000);
    }
    
    function closeToast() { document.getElementById('toast').classList.remove('show'); }
  </script>
  <script src="../resources/js/notifications.js"></script>
</body>
</html>
