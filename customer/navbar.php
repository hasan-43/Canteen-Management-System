<?php
// customer/navbar.php
// Customer-only homepage (requires session + DB connection)

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();
require_once __DIR__ . '/../config/db_connection.php';

if (!isset($_SESSION['usertype']) || $_SESSION['usertype'] !== 'customer') {
    header('Location: ../login.php');
    exit;
}

$displayName = $_SESSION['fullname'] ?? $_SESSION['username'] ?? 'Customer';
$profilePic = $_SESSION['profile_pic'] ?? null;

if (!empty($_SESSION['user_id'])) {
    $userId = (int) $_SESSION['user_id'];
    $sql = "SELECT fullname, email";
    $hasProfilePic = false;
    $res = $conn->query("SELECT COUNT(*) AS cnt FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'customer' AND COLUMN_NAME = 'profile_pic'");
    if ($res) {
        $row = $res->fetch_assoc();
        if ((int)$row['cnt'] > 0) {
            $hasProfilePic = true;
            $sql .= ", profile_pic";
        }
    }
    $sql .= " FROM customer WHERE customer_id = ? LIMIT 1";
    if ($stmt = $conn->prepare($sql)) {
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result && $row = $result->fetch_assoc()) {
            if (!empty($row['fullname'])) $displayName = $row['fullname'];
            if ($hasProfilePic && !empty($row['profile_pic'])) $profilePic = $row['profile_pic'];
        }
        $stmt->close();
    }
} else {
    if (!empty($_SESSION['username'])) {
        $username = $conn->real_escape_string($_SESSION['username']);
        $sql = "SELECT fullname";
        $hasProfilePic = false;
        $res = $conn->query("SELECT COUNT(*) AS cnt FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'customer' AND COLUMN_NAME = 'profile_pic'");
        if ($res) {
            $row = $res->fetch_assoc();
            if ((int)$row['cnt'] > 0) {
                $hasProfilePic = true;
                $sql .= ", profile_pic";
            }
        }
        $sql .= " FROM customer WHERE username = ? LIMIT 1";
        if ($stmt = $conn->prepare($sql)) {
            $stmt->bind_param("s", $_SESSION['username']);
            $stmt->execute();
            $result = $stmt->get_result();
            if ($result && $row = $result->fetch_assoc()) {
                if (!empty($row['fullname'])) $displayName = $row['fullname'];
                if ($hasProfilePic && !empty($row['profile_pic'])) $profilePic = $row['profile_pic'];
            }
            $stmt->close();
        }
    }
}

function initials($name) {
    $parts = preg_split('/\s+/', trim($name));
    $letters = '';
    foreach ($parts as $p) {
        if ($p !== '') $letters .= mb_strtoupper(mb_substr($p, 0, 1));
        if (mb_strlen($letters) >= 2) break;
    }
    return $letters ?: 'C';
}
$initials = initials($displayName);

// ── Fetch shops for restaurant cards ──
$shops = [];
$shopResult = $conn->query("SELECT shop_id, shop_name FROM shop ORDER BY shop_id");
if ($shopResult) {
    while ($row = $shopResult->fetch_assoc()) {
        $shopName = $row['shop_name'];

        $itemCount = 0;
        $countResult = $conn->query("SELECT COUNT(*) as cnt FROM `" . $conn->real_escape_string($shopName) . "` WHERE is_available = 1");
        if ($countResult) {
            $countRow = $countResult->fetch_assoc();
            $itemCount = (int) $countRow['cnt'];
        }

        $avgRating = 0;
        $reviewCount = 0;
        $ratingResult = $conn->query("SELECT AVG(rating) as avg_rating, COUNT(*) as review_count FROM product_reviews WHERE kitchen = '" . $conn->real_escape_string($shopName) . "'");
        if ($ratingResult) {
            $ratingRow = $ratingResult->fetch_assoc();
            $avgRating = round((float) $ratingRow['avg_rating'], 1);
            $reviewCount = (int) $ratingRow['review_count'];
        }

        $images = [];
        $imgResult = $conn->query("SELECT image FROM `" . $conn->real_escape_string($shopName) . "` WHERE is_available = 1 AND image IS NOT NULL ORDER BY RAND() LIMIT 3");
        if ($imgResult) {
            while ($imgRow = $imgResult->fetch_assoc()) {
                $images[] = $imgRow['image'];
            }
        }

        $shops[] = [
            'id'           => $row['shop_id'],
            'name'         => $shopName,
            'display_name' => ucfirst($shopName) . ' Kitchen',
            'item_count'   => $itemCount,
            'avg_rating'   => $avgRating,
            'review_count' => $reviewCount,
            'images'       => $images,
        ];
    }
}

$shopMeta = [
    'khans' => [
        'desc'     => 'Authentic desi cuisine with a modern twist. From biriyani to burgers — taste the best.',
        'gradient' => 'from-rose-500 to-orange-500',
        'glow'     => 'rgba(244,63,94,0.3)',
        'icon'     => '🔥',
        'bg'       => 'from-rose-500/10 to-orange-500/10',
    ],
    'olympia' => [
        'desc'     => 'Premium meals crafted with care. Fresh ingredients, bold flavors, every single day.',
        'gradient' => 'from-emerald-500 to-teal-500',
        'glow'     => 'rgba(16,185,129,0.3)',
        'icon'     => '🌿',
        'bg'       => 'from-emerald-500/10 to-teal-500/10',
    ],
    'neptune' => [
        'desc'     => 'Wholesome bites and sweet treats. Your go-to for snacks, sweets, and refreshments.',
        'gradient' => 'from-blue-500 to-indigo-500',
        'glow'     => 'rgba(59,130,246,0.3)',
        'icon'     => '🌊',
        'bg'       => 'from-blue-500/10 to-indigo-500/10',
    ],
];
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <script src="../resources/js/theme.js"></script>
  <meta name="viewport" content="width=device-width,initial-scale=1" />
  <title>Home - Customer - Campus Cravings</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
  <script src="https://cdn.tailwindcss.com"></script>
  <style>
    *, *::before, *::after { box-sizing: border-box; }
    body { font-family: 'Inter', sans-serif; margin: 0; }

    /* Hero background with slide transition */
    .hero {
      min-height: 70vh;
      position: relative;
      overflow: hidden;
    }
    .hero-bg {
      position: absolute; inset: 0;
      background-image: url('../resources/Homepage/Home_HD.jpg');
      background-size: cover; background-position: center;
      z-index: 0;
      animation: kenBurns 24s ease-in-out infinite;
    }
    @keyframes kenBurns {
      0%, 100% { transform: scale(1); }
      50% { transform: scale(1.08); }
    }

    /* Dark overlay */
    .hero-overlay {
      position: absolute; inset: 0;
      background: linear-gradient(180deg, rgba(0,0,0,0.6) 0%, rgba(0,0,0,0.4) 40%, rgba(0,0,0,0.75) 100%);
      z-index: 1;
    }

    /* Campus Cravings logo styling */
    .campus-cravings-logo {
      height: 50px; width: auto;
      transition: transform 0.3s ease;
    }
    .campus-cravings-logo:hover { transform: scale(1.05); }

    /* Header fixed positioning */
    header {
      position: fixed; top: 0; left: 0; right: 0; z-index: 50;
      background: rgba(10, 10, 12, 0.9) !important;
      backdrop-filter: blur(12px) !important;
      -webkit-backdrop-filter: blur(12px) !important;
      border-bottom: 1px solid rgba(255, 255, 255, 0.1) !important;
    }

    .logo-section { position: absolute; left: 2rem; top: 50%; transform: translateY(-50%); }
    .logo-section a { display: flex; align-items: center; gap: 0.75rem; text-decoration: none; }
    .brand-text { font-size: 1.25rem; font-weight: 800; color: #ffffff; letter-spacing: 0.05em; transition: color 0.3s ease; }
    .logo-section a:hover .brand-text { color: #ef4444; }
    .profile-section { position: absolute; right: 2rem; top: 50%; transform: translateY(-50%); }

    /* profile dropdown */
    .profile-menu { display: none; z-index: 80; box-shadow: 0 12px 30px rgba(0,0,0,0.35); backdrop-filter: blur(6px); }
    .profile-menu.show { display: block; }

    /* initials circle */
    .initials-circle {
      width: 40px; height: 40px; border-radius: 9999px;
      background: linear-gradient(135deg, #ef4444, #7f1d1d);
      display: inline-flex; align-items: center; justify-content: center;
      color: #fff; font-weight: 700;
    }

    /* Center content */
    .hero-content {
      position: relative; z-index: 2;
      min-height: 70vh; display: flex; align-items: center; justify-content: center;
      padding-top: 5rem;
    }

    /* Nav buttons */
    .nav-buttons { display: none; }
    @media (min-width: 768px) {
      .nav-buttons {
        display: flex; position: absolute; left: 50%; top: 50%;
        transform: translate(-50%, -50%); gap: 1rem;
      }
    }

    /* ── Scroll indicator ── */
    .scroll-indicator {
      position: absolute; bottom: 2rem; left: 50%; transform: translateX(-50%);
      z-index: 3; animation: bounceDown 2s infinite;
    }
    @keyframes bounceDown {
      0%, 100% { transform: translateX(-50%) translateY(0); }
      50% { transform: translateX(-50%) translateY(10px); }
    }

    /* ── Shop Cards ── */
    .shops-section {
      background: linear-gradient(180deg, #0f0f0f 0%, #1a1a2e 50%, #0f0f0f 100%);
      padding: 5rem 1rem;
    }
    .section-title {
      font-size: 2.5rem; font-weight: 800; text-align: center;
      background: linear-gradient(135deg, #fff, #9ca3af);
      -webkit-background-clip: text; -webkit-text-fill-color: transparent;
      background-clip: text;
    }
    .section-subtitle {
      text-align: center; color: #9ca3af; font-size: 1.1rem; margin-top: .75rem;
    }

    .shop-card {
      background: rgba(255,255,255,0.06);
      backdrop-filter: blur(20px);
      -webkit-backdrop-filter: blur(20px);
      border: 1px solid rgba(255,255,255,0.1);
      border-radius: 1.25rem;
      overflow: hidden;
      transition: transform .35s cubic-bezier(.25,.8,.25,1), box-shadow .35s ease;
      cursor: pointer;
    }
    .shop-card:hover { transform: translateY(-8px) scale(1.02); }

    .card-image-grid {
      display: grid; grid-template-columns: 1fr 1fr; grid-template-rows: 1fr 1fr;
      height: 220px; gap: 3px; overflow: hidden;
    }
    .card-image-grid img:first-child { grid-row: 1 / 3; object-fit: cover; width: 100%; height: 100%; }
    .card-image-grid img { object-fit: cover; width: 100%; height: 100%; }

    .card-body { padding: 1.5rem; }

    .star { color: #fbbf24; }
    .star-empty { color: #4b5563; }

    .stat-badge {
      display: inline-flex; align-items: center; gap: .35rem;
      padding: .3rem .7rem; border-radius: 100px;
      font-size: .8rem; font-weight: 600;
      background: rgba(255,255,255,0.08);
      border: 1px solid rgba(255,255,255,0.1);
    }

    .cta-btn {
      display: inline-flex; align-items: center; justify-content: center; gap: .5rem;
      width: 100%; padding: .85rem 1.5rem; border-radius: .75rem;
      font-weight: 700; font-size: .95rem; letter-spacing: .02em;
      color: #fff; border: none; cursor: pointer;
      transition: all .25s ease;
    }
    .cta-btn:hover { filter: brightness(1.15); transform: translateY(-1px); }
    .cta-btn::after { content: '→'; font-size: 1.1rem; transition: transform .2s ease; }
    .cta-btn:hover::after { transform: translateX(4px); }

    /* ── Footer ── */
    footer {
      background: #0a0a0a; border-top: 1px solid rgba(255,255,255,.06);
      padding: 2.5rem 1rem; text-align: center; color: #6b7280;
    }
  </style>
</head>
<body class="bg-[#0f0f0f] text-white">
  <!-- Header with Logo (left) and Profile (right) -->
  <header>
    <div class="relative h-16 max-w-7xl mx-auto px-4">
      <!-- Logo left -->
      <div class="logo-section">
        <a href="./navbar.php">
          <img src="../resources/logo.jpg" alt="Campus Cravings" class="campus-cravings-logo" />
          <span class="brand-text">Campus Cravings</span>
        </a>
      </div>

      <!-- Nav buttons center (hidden on mobile) -->
      <nav class="nav-buttons">
        <a href="./navbar.php" class="px-4 py-2 bg-red-600 bg-opacity-80 rounded text-sm text-white font-semibold">Home</a>
        <div class="relative group">
          <button class="px-4 py-2 rounded text-sm hover:bg-red-600 hover:bg-opacity-80 text-white transition">Shop</button>
          <div class="absolute left-0 mt-2 w-48 bg-black bg-opacity-95 border border-gray-800 rounded shadow-lg opacity-0 group-hover:opacity-100 transform scale-95 group-hover:scale-100 transition-all origin-top z-50">
            <?php foreach ($shops as $shop): 
              $isLegacy = in_array($shop['name'], ['khans', 'olympia', 'neptune']);
              $shopUrl = $isLegacy ? "./" . htmlspecialchars($shop['name']) . ".php" : "./shop.php?name=" . urlencode($shop['name']);
            ?>
              <a href="<?= $shopUrl ?>" class="block px-4 py-2 hover:bg-gray-700 text-white"><?= htmlspecialchars($shop['display_name']) ?></a>
            <?php endforeach; ?>
          </div>
        </div>
        <a href="./invoice.php" class="px-4 py-2 rounded text-sm hover:bg-red-600 hover:bg-opacity-80 text-white transition">Invoice</a>
        <a href="./chat.php" class="px-4 py-2 rounded text-sm hover:bg-red-600 hover:bg-opacity-80 text-white transition">Chat</a>
        <a href="./cart.php" class="px-4 py-2 rounded text-sm hover:bg-red-600 hover:bg-opacity-80 text-white transition">Cart</a>
      </nav>

      <!-- Profile right -->
      <div class="profile-section">
        <div class="relative" id="profileRoot">
          <button id="profileBtn" class="flex items-center gap-2 p-1 rounded profile-btn focus:outline-none">
            <?php if (!empty($profilePic)): ?>
              <img src="<?= htmlspecialchars($profilePic) ?>" alt="Profile" class="w-10 h-10 rounded-full object-cover border border-gray-600" />
            <?php else: ?>
              <div class="initials-circle"><?= htmlspecialchars($initials) ?></div>
            <?php endif; ?>
            <span class="hidden sm:inline-block text-sm"><?= htmlspecialchars($displayName) ?></span>
          </button>

          <div id="profileMenu" class="profile-menu absolute right-0 mt-2 w-48 bg-gray-900 bg-opacity-95 rounded-lg border border-gray-800 text-left">
            <a href="../customer/profile.php" class="block px-4 py-2.5 text-gray-100 hover:bg-gray-800 hover:bg-opacity-70">Profile</a>
            <a href="./logout.php" class="block px-4 py-2.5 text-red-200 hover:bg-gray-800 hover:bg-opacity-70">Log out</a>
          </div>
        </div>
      </div>
    </div>
  </header>

  <!-- Hero section with sliding background -->
  <section id="hero" class="hero" style="margin-top: 4rem;">
    <div class="hero-bg"></div>
    <div class="hero-overlay"></div>
    <div class="hero-content">
      <div class="max-w-4xl mx-auto px-4 text-center">
        <p class="text-red-400 font-semibold text-sm uppercase tracking-widest mb-3">Welcome back</p>
        <h1 class="text-4xl sm:text-5xl font-extrabold mb-4"><?= htmlspecialchars($displayName) ?> 👋</h1>
        <p class="text-gray-200 text-lg mb-8">Order your favourite meals from nearby kitchens — fast delivery.</p>
        <a href="#restaurants" class="inline-flex items-center gap-2 bg-gradient-to-r from-red-600 to-red-500 text-white font-bold py-3 px-8 rounded-xl text-lg hover:from-red-500 hover:to-red-400 transition-all shadow-lg shadow-red-900/30">
          Browse Restaurants ↓
        </a>
      </div>
    </div>
    <div class="scroll-indicator">
      <svg width="30" height="30" viewBox="0 0 30 30" fill="none">
        <path d="M15 5v15M8 15l7 7 7-7" stroke="rgba(255,255,255,0.5)" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
      </svg>
    </div>
  </section>

  <!-- ═══════════ RESTAURANTS ═══════════ -->
  <section id="restaurants" class="shops-section">
    <div class="max-w-6xl mx-auto">
      <h2 class="section-title">Our Restaurants</h2>
      <p class="section-subtitle mb-12">Choose from our campus kitchens and start ordering</p>

      <div class="grid md:grid-cols-2 lg:grid-cols-3 gap-8">
        <?php foreach ($shops as $shop):
          $meta = $shopMeta[$shop['name']] ?? [
              'desc' => 'Great food available here.',
              'gradient' => 'from-gray-500 to-gray-600',
              'glow' => 'rgba(156,163,175,0.25)',
              'icon' => '🍽️',
          ];
          $folderName = ucfirst($shop['name']);
          $isLegacy = in_array($shop['name'], ['khans', 'olympia', 'neptune']);
          $shopUrl = $isLegacy ? "./" . htmlspecialchars($shop['name']) . ".php" : "./shop.php?name=" . urlencode($shop['name']);
        ?>
          <a href="<?= $shopUrl ?>" class="shop-card block" style="box-shadow: 0 8px 40px <?= $meta['glow'] ?>;">
            <!-- Image grid -->
            <div class="card-image-grid">
              <?php if (count($shop['images']) >= 3): ?>
                <img src="../resources/<?= $folderName ?>/<?= rawurlencode($shop['images'][0]) ?>" alt="" loading="lazy" />
                <img src="../resources/<?= $folderName ?>/<?= rawurlencode($shop['images'][1]) ?>" alt="" loading="lazy" />
                <img src="../resources/<?= $folderName ?>/<?= rawurlencode($shop['images'][2]) ?>" alt="" loading="lazy" />
              <?php elseif (count($shop['images']) >= 1): ?>
                <img src="../resources/<?= $folderName ?>/<?= rawurlencode($shop['images'][0]) ?>" alt="" loading="lazy" style="grid-row:1/3; grid-column:1/3;" />
              <?php else: ?>
                <div class="bg-gradient-to-br <?= $meta['gradient'] ?> flex items-center justify-center text-5xl" style="grid-row:1/3; grid-column:1/3;"><?= $meta['icon'] ?></div>
              <?php endif; ?>
            </div>

            <!-- Card body -->
            <div class="card-body">
              <div class="flex items-center gap-2 mb-2">
                <span class="text-2xl"><?= $meta['icon'] ?></span>
                <h3 class="text-xl font-bold text-white"><?= htmlspecialchars($shop['display_name']) ?></h3>
              </div>

              <p class="text-gray-400 text-sm mb-4 leading-relaxed"><?= $meta['desc'] ?></p>

              <!-- Stats -->
              <div class="flex flex-wrap gap-2 mb-5">
                <span class="stat-badge">🍽️ <?= $shop['item_count'] ?> items</span>
                <?php if ($shop['review_count'] > 0): ?>
                  <span class="stat-badge"><span class="star">★</span> <?= $shop['avg_rating'] ?> (<?= $shop['review_count'] ?>)</span>
                <?php else: ?>
                  <span class="stat-badge"><span class="star-empty">★</span> New</span>
                <?php endif; ?>
              </div>

              <!-- CTA -->
              <div class="cta-btn bg-gradient-to-r <?= $meta['gradient'] ?>">View Menu</div>
            </div>
          </a>
        <?php endforeach; ?>
      </div>
    </div>
  </section>

 
  <script>
    // Profile dropdown toggle + click outside to close
    (function() {
      const profileBtn = document.getElementById('profileBtn');
      const profileMenu = document.getElementById('profileMenu');
      profileBtn.addEventListener('click', function(e) {
        e.stopPropagation();
        profileMenu.classList.toggle('show');
      });
      document.addEventListener('click', function() {
        profileMenu.classList.remove('show');
      });
    })();

    // Background slideshow logic disabled in favor of animated HD background CSS
    (function() {
      // Slideshow logic disabled
    })();

    // Smooth scroll
    document.querySelector('a[href="#restaurants"]')?.addEventListener('click', function(e) {
      e.preventDefault();
      document.getElementById('restaurants').scrollIntoView({ behavior: 'smooth' });
    });
  </script>
  <script src="../resources/js/notifications.js"></script>
</body>
</html>