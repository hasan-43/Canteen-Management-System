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
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width,initial-scale=1" />
    <title>Delivery Home</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        .hero { min-height: 100vh; position: relative; overflow: hidden; }
        .hero-slides { position:absolute; inset:0; z-index:0; }
        .hero-slide { position:absolute; inset:0; background-size:cover; background-position:center; opacity:0; transition: opacity 1.2s ease-in-out; }
        .hero-slide.active { opacity:1; }
        .hero-overlay { position:absolute; inset:0; background: rgba(0,0,0,0.5); z-index:10; }
        .initials-circle{width:40px;height:40px;border-radius:9999px;background:linear-gradient(135deg,#ef4444,#7f1d1d);display:inline-flex;align-items:center;justify-content:center;color:#fff;font-weight:700}
        .profile-icon{width:40px;height:40px;border-radius:9999px;object-fit:cover}
    </style>
</head>
<body class="bg-gray-900 text-white">
    <header class="h-16 flex items-center">
        <div class="relative h-full w-full max-w-7xl mx-auto px-4">
            <div class="logo-section">
                <h2 class="food-wave">Food Wave</h2>
            </div>
            <nav class="nav-buttons hidden md:flex left-1/2 top-1/2 transform -translate-x-1/2 -translate-y-1/2 gap-4 absolute">
                <a href="navbar.php" class="px-4 py-2 bg-transparent hover:bg-red-600 hover:bg-opacity-80 rounded text-sm">Home</a>
                <a href="orders.php" class="px-4 py-2 rounded text-sm hover:bg-red-600 hover:bg-opacity-80">Orders</a>
                <a href="details.php" class="px-4 py-2 rounded text-sm hover:bg-red-600 hover:bg-opacity-80">Details</a>
                <a href="chat.php" class="px-4 py-2 rounded text-sm hover:bg-red-600 hover:bg-opacity-80">Chat</a>
            </nav>
            <div class="profile-section absolute right-8 top-1/2 transform -translate-y-1/2">
                <div class="relative" id="profileRoot">
                    <button id="profileBtn" class="flex items-center gap-2 p-1 rounded focus:outline-none">
                        <img src="<?= htmlspecialchars($profileImg) ?>" alt="Profile" class="profile-icon" />
                        <span class="hidden sm:inline-block text-sm"><?= htmlspecialchars($displayName) ?></span>
                    </button>
                    <div id="profileMenu" class="profile-menu hidden absolute right-0 mt-2 w-44 bg-black bg-opacity-95 rounded shadow-lg border border-gray-800 text-left">
                        <a href="profile.php" class="block px-4 py-2 hover:bg-gray-700">Profile</a>
                        <a href="logout.php" class="block px-4 py-2 text-red-400 hover:bg-gray-700">Log out</a>
                    </div>
                </div>
            </div>
        </div>
    </header>

    <?php if (!$hideHero): ?>
    <section id="hero" class="hero">
        <div id="heroSlides" class="hero-slides"></div>
        <div class="hero-overlay"></div>
    </section>
    <?php endif; ?>

    <script>
        (function(){
            const profileBtn = document.getElementById('profileBtn');
            const profileMenu = document.getElementById('profileMenu');
            profileBtn && profileBtn.addEventListener('click', function(e){ e.stopPropagation(); profileMenu.classList.toggle('hidden'); });
            document.addEventListener('click', () => { profileMenu && profileMenu.classList.add('hidden'); });
        })();

        <?php if (!$hideHero): ?>
        // Background slideshow sourced from resources/Homepage
        (function(){
            const images = <?php echo json_encode(array_values($heroImages)); ?>;
            const slidesRoot = document.getElementById('heroSlides');
            if (!slidesRoot || !images.length) return;

            const slides = images.map((src, idx) => {
                const div = document.createElement('div');
                div.className = 'hero-slide' + (idx === 0 ? ' active' : '');
                div.style.backgroundImage = `url('${src}')`;
                slidesRoot.appendChild(div);
                return div;
            });

            if (slides.length < 2) return;
            let current = 0;
            setInterval(() => {
                slides[current].classList.remove('active');
                current = (current + 1) % slides.length;
                slides[current].classList.add('active');
            }, 3200);
        })();
        <?php endif; ?>
    </script>
</body>
</html>
