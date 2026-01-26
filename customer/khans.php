<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
session_start();
require_once __DIR__ . '/../config/db_connection.php';

// Fetch products from khans table in database
$catalog = [];
$dbError = '';
$sql = "SELECT khans_id, product_code, name, price, category, image, stock, is_available 
        FROM khans 
        WHERE is_available = 1 
        ORDER BY category, name";
$result = $conn->query($sql);
if (!$result) {
    $dbError = "Database Error: " . $conn->error . "<br><strong>Did you import khans_table.sql?</strong>";
} elseif ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $catalog[] = [
            'id'       => $row['product_code'],
            'name'     => $row['name'],
            'price'    => (float)$row['price'],
            'category' => $row['category'],
            'image'    => $row['image'],
            'stock'    => (int)$row['stock']
        ];
    }
} else {
    $dbError = "No products found in khans table. Please import khans_table.sql";
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
    
    if ($item && isset($_SESSION['inventory'][$itemId])) {
        $available = $_SESSION['inventory'][$itemId];
        if ($qty <= $available) {
            // Get or create cart for customer
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
            $stmt = $conn->prepare("SELECT item_id, quantity FROM cart_items WHERE cart_id = ? AND product_code = ? AND kitchen = 'khans'");
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
                $stmt = $conn->prepare("INSERT INTO cart_items (cart_id, product_code, product_name, kitchen, price, quantity) VALUES (?, ?, ?, 'khans', ?, ?)");
                $stmt->bind_param("issdi", $cartId, $itemId, $item['name'], $item['price'], $qty);
                $stmt->execute();
            }
            $stmt->close();
            
            $_SESSION['inventory'][$itemId] -= $qty;
            $response['success'] = true;
            $response['message'] = "Added {$qty} × {$item['name']} to cart";
        } else {
            $response['message'] = "Only {$available} left for {$item['name']}";
        }
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
$displayName = $_SESSION['fullname'] ?? $_SESSION['username'] ?? 'Customer';
$profilePic = $_SESSION['profile_pic'] ?? null;
$initials = initials($displayName);
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width,initial-scale=1" />
  <title>Khans Kitchen</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <style>
    header { position: fixed; top: 0; left: 0; right: 0; z-index: 50; background: rgba(0, 0, 0, 0.7); backdrop-filter: blur(10px); }
    .logo-section { position: absolute; left: 2rem; top: 50%; transform: translateY(-50%); }
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
    .nav-dropdown-menu { position:absolute; left:0; margin-top:0.4rem; width:12rem; background:#fff; border:1px solid #e5e7eb; border-radius:0.5rem; box-shadow:0 10px 25px -10px rgba(0,0,0,0.15); opacity:0; transform:translateY(-4px) scale(0.98); transition: all 0.15s ease; pointer-events:none; }
    .nav-dropdown:hover .nav-dropdown-menu { opacity:1; transform:translateY(0) scale(1); pointer-events:auto; }
    .nav-dropdown-menu a { display:block; padding:0.55rem 0.9rem; color:#111827; border-radius:0.5rem; }
    .nav-dropdown-menu a:hover { background:#f3f4f6; }
    @media (max-width: 768px) {
      .nav-buttons { flex-wrap:wrap; justify-content:flex-start; }
    }
  </style>
</head>
<body class="bg-white text-gray-900">
  <div id="toast" class="toast"></div>
  <header>
    <div class="relative h-16 max-w-7xl mx-auto px-4">
      <div class="logo-section">
        <h2 class="food-wave">Food Wave</h2>
      </div>
      <nav class="nav-buttons">
        <a href="./navbar.php" class="px-4 py-2 bg-transparent hover:bg-red-600 hover:bg-opacity-80 rounded text-sm">Home</a>
        <div class="relative group">
          <button type="button" class="px-4 py-2 rounded text-sm hover:bg-red-600 hover:bg-opacity-80">Shop</button>
          <div class="absolute left-0 mt-2 w-48 bg-black bg-opacity-90 border border-gray-800 rounded shadow-lg opacity-0 group-hover:opacity-100 transform scale-95 group-hover:scale-100 transition-all origin-top">
            <a href="./khans.php" class="block px-4 py-2 hover:bg-gray-700">Khans Kitchen</a>
            <a href="./olympia.php" class="block px-4 py-2 hover:bg-gray-700">Olympia Kitchen</a>
            <a href="./neptune.php" class="block px-4 py-2 hover:bg-gray-700">Neptune Kitchen</a>
          </div>
        </div>
        <a href="./invoice.php" class="px-4 py-2 rounded text-sm hover:bg-red-600 hover:bg-opacity-80">Invoice</a>
        <a href="./cart.php" class="px-4 py-2 rounded text-sm hover:bg-red-600 hover:bg-opacity-80">Cart</a>
      </nav>
      <div class="profile-section">
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
            <a href="./profile.php" class="block px-4 py-2 hover:bg-gray-700">Profile</a>
            <a href="./logout.php" class="block px-4 py-2 text-red-400 hover:bg-gray-700">Log out</a>
          </div>
        </div>
      </div>
    </div>
  </header>

  <section id="hero" class="hero">
    <div class="hero-overlay"></div>
    <div class="relative z-10 max-w-4xl mx-auto px-4 py-24 text-center text-white">
      <h1 class="text-4xl sm:text-5xl font-extrabold mb-4">Khans Kitchen</h1>
      <p class="text-lg text-gray-200 mb-6">Fresh, quick, and tasty meals — pick your favorites below.</p>
      <?php if ($dbError): ?>
        <div class="max-w-xl mx-auto bg-red-600 text-white font-semibold px-4 py-3 rounded shadow"><?= $dbError ?></div>
      <?php elseif ($alert): ?>
        <div class="max-w-xl mx-auto bg-white/80 text-gray-900 font-semibold px-4 py-3 rounded shadow"><?= htmlspecialchars($alert) ?></div>
      <?php endif; ?>
    </div>
  </section>

  <main class="max-w-6xl mx-auto px-4 py-10">
    <div class="mb-8">
      <div class="flex flex-wrap gap-2 text-sm font-semibold text-gray-700 bg-white rounded-lg shadow border border-gray-100 p-3">
        <?php foreach ($categories as $key => $label): ?>
          <a href="?category=<?= urlencode($key) ?>" class="px-3 py-2 rounded <?= $activeCategory === $key ? 'bg-red-600 text-white' : 'bg-gray-100 hover:bg-gray-200' ?>"><?= htmlspecialchars($label) ?></a>
        <?php endforeach; ?>
      </div>
    </div>
    <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-6">
      <?php foreach ($catalog as $item): ?>
        <?php
          if ($activeCategory !== 'all' && $item['category'] !== $activeCategory) continue;
          $stock = $_SESSION['inventory'][$item['id']] ?? 0;
        ?>
        <div class="bg-white rounded-xl shadow hover:shadow-md transition overflow-hidden border border-gray-100">
          <div class="h-48 bg-gray-200 overflow-hidden">
            <img src="<?= '../resources/Khans/' . rawurlencode($item['image']) ?>" alt="<?= htmlspecialchars($item['name']) ?>" class="w-full h-full object-cover" />
          </div>
          <div class="p-4 space-y-2">
            <div class="flex items-center justify-between">
              <h3 class="text-lg font-bold text-gray-900"><?= htmlspecialchars($item['name']) ?></h3>
              <span class="text-red-600 font-semibold">Tk <?= number_format($item['price'], 0) ?></span>
            </div>
            <p class="text-sm text-gray-600">Stock: <?= $stock ?></p>
            <form class="cart-form flex items-center gap-3 pt-2" data-item-id="<?= htmlspecialchars($item['id']) ?>" data-item-name="<?= htmlspecialchars($item['name']) ?>">
              <input type="number" name="qty" min="1" max="<?= $stock ?>" value="1" class="qty-input w-20 px-2 py-2 border rounded" />
              <button type="submit" class="add-to-cart-btn flex-1 bg-red-600 hover:bg-red-700 text-white font-semibold py-2 px-3 rounded <?= $stock <= 0 ? 'opacity-50 cursor-not-allowed' : '' ?>" <?= $stock <= 0 ? 'disabled' : '' ?>>Add to cart</button>
            </form>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  </main>

  <script>
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
    
    // Handle cart form submission
    document.querySelectorAll('.cart-form').forEach(form => {
      form.addEventListener('submit', function(e) {
        e.preventDefault();
        const itemId = this.dataset.itemId;
        const itemName = this.dataset.itemName;
        const qty = parseInt(this.querySelector('.qty-input').value) || 1;
        const btn = this.querySelector('.add-to-cart-btn');
        
        btn.disabled = true;
        btn.textContent = 'Adding...';
        
        fetch(window.location.href, {
          method: 'POST',
          headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
          body: `ajax=1&add_to_cart=1&item_id=${encodeURIComponent(itemId)}&qty=${qty}`
        })
        .then(res => res.json())
        .then(data => {
          showToast(data.message, data.success ? 'success' : 'error');
          if (data.success) {
            this.querySelector('.qty-input').value = 1;
          }
        })
        .catch(err => {
          showToast('Error adding to cart', 'error');
        })
        .finally(() => {
          btn.disabled = false;
          btn.textContent = 'Add to cart';
        });
      });
    });
    
    (function() {
      const profileBtn = document.getElementById('profileBtn');
      const profileMenu = document.getElementById('profileMenu');
      profileBtn.addEventListener('click', function(e) {
        e.stopPropagation();
        profileMenu.classList.toggle('show');
      });
      document.addEventListener('click', function() { profileMenu.classList.remove('show'); });
    })();
  </script>
</body>
</html>
