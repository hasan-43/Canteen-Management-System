<?php
// admin/neptune/navbar.php
// Neptune admin homepage with customer-like navbar plus statistics

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();

if (!isset($_SESSION['usertype']) || $_SESSION['usertype'] !== 'admin' || ($_SESSION['shop'] ?? '') !== 'Neptune Kitchen') {
		header('Location: ../../login.php');
		exit;
}

$displayName = $_SESSION['username'] ?? 'Admin';
// Username and profile image detection
$username = $_SESSION['username'] ?? null;
$profileImage = $_SESSION['profile_image'] ?? null;
if (!$profileImage && $username) {
	$picDir = __DIR__ . '/../../resources/ProfilePics';
	if (is_dir($picDir)) {
		foreach (['jpg','jpeg','png','webp'] as $ext) {
			$candidateFs = $picDir . '/' . $username . '.' . $ext;
			if (file_exists($candidateFs)) {
				$profileImage = '../../resources/ProfilePics/' . $username . '.' . $ext;
				break;
			}
		}
	}
}

// Collect all homepage images for slideshow
$bgImagesRel = [];
$homeDir = __DIR__ . '/../../resources/Homepage';
if (is_dir($homeDir)) {
	$files = glob($homeDir . '/*.{jpg,jpeg,png,webp}', GLOB_BRACE);
	if ($files) {
		// Sort for consistent order
		natcasesort($files);
		foreach ($files as $f) {
			$bgImagesRel[] = '../../resources/Homepage/' . basename($f);
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
		return $letters ?: 'A';
}
$initials = initials($displayName);
?>
<!doctype html>
<html lang="en">
<head>
	<meta charset="utf-8" />
	<meta name="viewport" content="width=device-width,initial-scale=1" />
	<title>Home - Neptune Admin - Food Wave</title>
	<script src="https://cdn.tailwindcss.com"></script>
	<style>
		/* Hero background with slide transition */
		.hero {
			background-size: cover;
			background-position: center;
			background-attachment: fixed;
			min-height: 100vh;
			position: relative;
			overflow: hidden;
		}

		/* Slide transition from left to right */
		.hero::before {
			content: '';
			position: absolute;
			top: 0;
			left: -100%;
			width: 100%;
			height: 100%;
			background: inherit;
			background-size: cover;
			background-position: center;
			animation: slideInLeft 2s ease-in-out forwards;
			z-index: -1;
		}

		@keyframes slideInLeft {
			from { left: -100%; }
			to { left: 0%; }
		}

		/* Dark overlay */
		.hero-overlay {
			position: absolute;
			top: 0;
			left: 0;
			right: 0;
			bottom: 0;
			background: rgba(0, 0, 0, 0.5);
			z-index: 1;
		}

		/* Food Wave colorful gradient text */
		.food-wave {
			font-weight: 900;
			letter-spacing: 3px;
			font-size: 2.5rem;
			background: linear-gradient(90deg, #ff0000);
			-webkit-background-clip: text;
			background-clip: text;
			-webkit-text-fill-color: transparent;
			animation: fadeOpenClose 3s ease-in-out infinite;
			display: inline-block;
		}

		/* Fade in and out animation */
		@keyframes fadeOpenClose {
			0% { opacity: 0; transform: scale(0.5); }
			30% { opacity: 1; transform: scale(1); }
			70% { opacity: 1; transform: scale(1); }
			100% { opacity: 0; transform: scale(0.5); }
		}

		/* Header fixed positioning */
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

		/* profile dropdown */
		.profile-btn:focus + .profile-menu,
		.profile-menu.show {
			display: block;
		}

		/* initials circle */
		.initials-circle {
			width: 40px;
			height: 40px;
			border-radius: 9999px;
			background: linear-gradient(135deg, #ef4444, #7f1d1d);
			display: inline-flex;
			align-items: center;
			justify-content: center;
			color: #fff;
			font-weight: 700;
		}

		/* avatar image styling */
		.avatar-img {
			width: 40px;
			height: 40px;
			border-radius: 9999px;
			object-fit: cover;
		}

		/* Center content */
		.hero-content {
			position: relative;
			z-index: 2;
			height: 100vh;
			display: flex;
			align-items: center;
			justify-content: center;
			padding-top: 4rem;
		}

		/* Nav buttons (hide on hero) */
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
	</style>
</head>
<body class="bg-gray-900 text-white">
	<!-- Header with Logo (left) and Profile (right) -->
	<header>
		<div class="relative h-16 max-w-7xl mx-auto px-4">
			<!-- Logo left -->
			<div class="logo-section">
				<h2 class="food-wave">Food Wave</h2>
			</div>

			<!-- Nav buttons center (hidden on mobile) -->
			<nav class="nav-buttons">
				<a href="./navbar.php" class="px-4 py-2 bg-transparent hover:bg-red-600 hover:bg-opacity-80 rounded text-sm">Home</a>
				<a href="./statistics.php" class="px-4 py-2 rounded text-sm hover:bg-red-600 hover:bg-opacity-80">Statistics</a>
				<a href="./product.php" class="px-4 py-2 rounded text-sm hover:bg-red-600 hover:bg-opacity-80">Product</a>
				<a href="./invoice.php" class="px-4 py-2 rounded text-sm hover:bg-red-600 hover:bg-opacity-80">Invoice</a>
			<a href="./orders.php" class="px-4 py-2 rounded text-sm hover:bg-red-600 hover:bg-opacity-80">Order</a>
			</nav>

			<!-- Profile right -->
			<div class="profile-section">
				<div class="relative" id="profileRoot">
					<button id="profileBtn" class="flex items-center gap-2 p-1 rounded profile-btn focus:outline-none">
						<?php if (!empty($profileImage)): ?>
							<img src="<?= htmlspecialchars($profileImage) ?>" alt="Profile" class="avatar-img" />
						<?php else: ?>
							<div class="initials-circle"><?= htmlspecialchars($initials) ?></div>
						<?php endif; ?>
						<span class="hidden sm:inline-block text-sm"><?= htmlspecialchars($displayName) ?></span>
					</button>

					<div id="profileMenu" class="profile-menu hidden absolute right-0 mt-2 w-44 bg-black bg-opacity-95 rounded shadow-lg border border-gray-800 text-left">
						<a href="./profile.php" class="block px-4 py-2 hover:bg-gray-700">Profile</a>
						<a href="../../customer/logout.php" class="block px-4 py-2 text-red-400 hover:bg-gray-700">Log out</a>
					</div>
				</div>
			</div>
		</div>
	</header>

	<!-- Hero section with sliding background -->
	<section id="hero" class="hero">
		<div class="hero-overlay"></div>

		<div class="hero-content">
			<div class="max-w-4xl mx-auto px-4 text-center">
				<h1 class="text-4xl sm:text-5xl font-extrabold mb-4">Welcome back, <?= htmlspecialchars($displayName) ?></h1>
				<p class="text-gray-200 mb-6">Manage Neptune Kitchen — track statistics and orders just like your customers see the site.</p>

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

		// Background slideshow - cycles through all images in Homepage folder
		(function() {
			const hero = document.getElementById('hero');
			const images = <?= json_encode($bgImagesRel ?: [
				'../../resources/Homepage/Home.jpg',
				'../../resources/Homepage/Home1.jpg',
				'../../resources/Homepage/Home2.jpg',
				'../../resources/Homepage/Home3.jpg',
				'../../resources/Homepage/Home4.jpg'
			]) ?>;

			images.forEach(src => { const img = new Image(); img.src = src; });

			let i = 0;
			if (images.length > 0) {
				hero.style.backgroundImage = `url('${images[0]}')`;
			}

			setInterval(() => {
				if (images.length === 0) return;
				i = (i + 1) % images.length;
				hero.style.backgroundImage = `url('${images[i]}')`;
			}, 2000);
		})();
	</script>
</body>
</html>
