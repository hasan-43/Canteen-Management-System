<?php
// Delivery dashboard / navbar (acts as homepage for logged-in delivery users)
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../config/db_connection.php';
if (!isset($_SESSION['usertype']) || $_SESSION['usertype'] !== 'delivery') {
        header('Location: ../login.php');
        exit;
}

$displayName = $_SESSION['delivery_name'] ?? 'Driver';
$profileImg = '../resources/ProfilePics/default.png';

// Pull stored profile picture if available (optional column guard)
if (!empty($_SESSION['delivery_id'])) {
    $deliveryId = (int) $_SESSION['delivery_id'];
    $hasProfilePic = false;
    $res = $conn->query("SELECT COUNT(*) AS cnt FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'delivery' AND COLUMN_NAME = 'profile_pic'");
    if ($res) {
        $row = $res->fetch_assoc();
        if ((int)$row['cnt'] > 0) {
            $hasProfilePic = true;
        }
    }

    if ($hasProfilePic) {
        if ($stmt = $conn->prepare("SELECT profile_pic, fullname FROM delivery WHERE delivery_id = ? LIMIT 1")) {
            $stmt->bind_param("i", $deliveryId);
            $stmt->execute();
            $result = $stmt->get_result();
            if ($result && $row = $result->fetch_assoc()) {
                if (!empty($row['fullname'])) $displayName = $row['fullname'];
                if (!empty($row['profile_pic'])) $profileImg = $row['profile_pic'];
            }
            $stmt->close();
        }
    }
}
$hideHero = isset($hideHero) ? (bool)$hideHero : false;

// Build hero slideshow images from resources/Homepage
$heroImages = [];
$imageDir = __DIR__ . '/../resources/Homepage';
if (is_dir($imageDir)) {
    foreach (['jpg', 'jpeg', 'png', 'gif'] as $ext) {
        foreach (glob($imageDir . '/*.' . $ext) as $file) {
            $heroImages[] = '../resources/Homepage/' . basename($file);
        }
    }
}
if (!$heroImages) {
    $heroImages[] = '../resources/Homepage/Home.jpg';
}
?>
<!doctype html>
<html lang="en">
<HEAD>
    <script src="../resources/js/theme.js"></script>
    <script src="../resources/js/theme.js"></script>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width,initial-scale=1" />
    <title>Delivery Home</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        header { position: fixed; top: 0; left: 0; right: 0; z-index: 50; background: rgba(10, 10, 12, 0.9) !important; backdrop-filter: blur(12px) !important; -webkit-backdrop-filter: blur(12px) !important; border-bottom: 1px solid rgba(255, 255, 255, 0.1) !important; }
        .logo-section { position: absolute; left: 2rem; top: 50%; transform: translateY(-50%); }
        .logo-section a { display: flex; align-items: center; gap: 0.75rem; text-decoration: none; }
        .brand-text { font-size: 1.25rem; font-weight: 800; color: #ffffff; letter-spacing: 0.05em; transition: color 0.3s ease; }
        .logo-section a:hover .brand-text { color: #ef4444; }
        .profile-section { position: absolute; right: 2rem; top: 50%; transform: translateY(-50%); }
        .campus-cravings-logo { height: 50px; width: auto; transition: transform 0.3s ease; }
        .campus-cravings-logo:hover { transform: scale(1.05); }
        .hero { min-height: 100vh; position: relative; overflow: hidden; margin-top: 4rem; display: flex; align-items: center; justify-content: center; }
        .hero-bg { position: absolute; inset: 0; background-image: url('../resources/Homepage/Home_HD.jpg'); background-size: cover; background-position: center; z-index: 0; animation: kenBurns 24s ease-in-out infinite; }
        @keyframes kenBurns { 0%, 100% { transform: scale(1); } 50% { transform: scale(1.08); } }
        .hero-overlay { position:absolute; inset:0; background: rgba(0,0,0,0.55); z-index:10; }
        .initials-circle{width:40px;height:40px;border-radius:9999px;background:linear-gradient(135deg,#ef4444,#7f1d1d);display:inline-flex;align-items:center;justify-content:center;color:#fff;font-weight:700}
        .profile-icon{width:40px;height:40px;border-radius:9999px;object-fit:cover;border:2px solid #ef4444;}
        .profile-btn{cursor:pointer;transition:opacity 0.2s;}
        .profile-btn:hover{opacity:0.8;}
        .profile-menu{position:absolute;right:0;margin-top:0.75rem;width:13rem;background:#0f172a;background:rgba(15,23,42,0.95);border-radius:0.5rem;border:1px solid #374151;box-shadow:0 10px 40px rgba(0,0,0,0.6);backdrop-filter:blur(8px);display:none;z-index:80;}
        .profile-menu.show{display:block;}
        .profile-menu a{display:block;padding:0.625rem 1rem;color:#f9fafb;font-weight:600;letter-spacing:0.01em;transition:background 0.2s;}
        .profile-menu a:hover{background:rgba(55,65,81,0.7);border-radius:0.375rem;}
        .profile-menu a:first-child{border-radius:0.5rem 0.5rem 0 0;}
        .profile-menu a:last-child{border-radius:0 0 0.5rem 0.5rem;}
    </style>
</head>
<body class="bg-gray-900 text-white">
    <header class="h-16 flex items-center">
        <div class="relative h-full w-full max-w-7xl mx-auto px-4">
            <div class="logo-section">
                <a href="navbar.php">
                    <img src="../resources/logo.jpg" alt="Campus Cravings" class="campus-cravings-logo" />
                    <span class="brand-text">Campus Cravings</span>
                </a>
            </div>
            <nav class="nav-buttons hidden md:flex left-1/2 top-1/2 transform -translate-x-1/2 -translate-y-1/2 gap-4 absolute">
                <a href="navbar.php" class="px-4 py-2 bg-red-600 bg-opacity-80 rounded text-sm text-white font-semibold">Home</a>
                <a href="orders.php" class="px-4 py-2 rounded text-sm hover:bg-red-600 hover:bg-opacity-80">Orders</a>
                <a href="details.php" class="px-4 py-2 rounded text-sm hover:bg-red-600 hover:bg-opacity-80">Details</a>
                <a href="chat.php" class="px-4 py-2 rounded text-sm hover:bg-red-600 hover:bg-opacity-80">Chat</a>
            </nav>
            <div class="profile-section">
                <div class="relative" id="profileRoot">
                    <button id="profileBtn" type="button" class="flex items-center gap-2 p-1 rounded focus:outline-none profile-btn">
                        <img src="<?= htmlspecialchars($profileImg) ?>" alt="Profile" class="profile-icon" />
                        <span class="hidden sm:inline-block text-sm text-white"><?= htmlspecialchars($displayName) ?></span>
                    </button>
                    <div id="profileMenu" class="profile-menu">
                        <a href="profile.php">Profile</a>
                        <a href="logout.php" class="text-red-300" id="logoutLink">Log out</a>
                    </div>
                </div>
            </div>
        </div>
    </header>
 
    <?php if (!$hideHero): ?>
    <section id="hero" class="hero">
        <div class="hero-bg"></div>
        <div class="hero-overlay text-white"></div>
        <div class="relative z-20 text-center px-4 max-w-2xl mx-auto">
            <span class="text-red-400 font-bold text-xs sm:text-sm uppercase tracking-widest bg-red-600/10 border border-red-500/20 px-4 py-1.5 rounded-full mb-6 inline-block">
                Delivery Dashboard
            </span>
            <h1 class="text-4xl sm:text-5xl font-extrabold mb-4 text-white">
                Welcome back, <?= htmlspecialchars($displayName) ?> 👋
            </h1>
            <p class="text-gray-300 text-base sm:text-lg mb-8 max-w-md mx-auto">
                Ready to deliver delicious meals across campus? Track and manage your orders instantly.
            </p>
            <div class="flex flex-col sm:flex-row justify-center items-center gap-4">
                <a href="orders.php" class="w-full sm:w-auto inline-flex items-center justify-center gap-2 bg-gradient-to-r from-red-600 to-red-500 hover:from-red-500 hover:to-red-400 text-white font-bold py-3 px-6 rounded-xl transition-all shadow-lg shadow-red-950/40 text-sm">
                    View Assigned Orders
                </a>
                <a href="profile.php" class="w-full sm:w-auto inline-flex items-center justify-center gap-2 bg-white/5 hover:bg-white/10 text-white font-bold py-3 px-6 rounded-xl border border-white/10 transition-all text-sm">
                    Manage Profile
                </a>
            </div>
        </div>
    </section>
    <?php endif; ?>

    <script>
        (function(){
            const profileBtn = document.getElementById('profileBtn');
            const profileMenu = document.getElementById('profileMenu');
            if (!profileBtn || !profileMenu) return;
            profileBtn.addEventListener('click', function(e){
                e.stopPropagation();
                profileMenu.classList.toggle('show');
            });
            document.addEventListener('click', () => profileMenu.classList.remove('show'));
        })();

        // Ensure logout works even if cached
        (function(){
            const logoutLink = document.getElementById('logoutLink');
            if (!logoutLink) return;
            logoutLink.addEventListener('click', function(e){
                e.preventDefault();
                window.location.href = this.href;
            });
        })();

        <?php if (!$hideHero): ?>
        // Slideshow JS disabled
        <?php endif; ?>
    </script>
    <script src="../resources/js/notifications.js"></script>
</body>
</html>
