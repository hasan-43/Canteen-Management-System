<?php
session_start();
require_once __DIR__ . '/../config/db_connection.php';

if (!isset($_SESSION['usertype']) || $_SESSION['usertype'] !== 'customer' || !isset($_SESSION['user_id'])) {
		header('Location: ../login.php');
		exit;
}

$customerId = (int) $_SESSION['user_id'];
$message = '';
$messageType = 'success';

// Check if profile_pic column exists
$hasProfilePic = false;
$res = $conn->query("SELECT COUNT(*) AS cnt FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'customer' AND COLUMN_NAME = 'profile_pic'");
if ($res) {
		$row = $res->fetch_assoc();
		$hasProfilePic = ((int)$row['cnt'] > 0);
}

// Try to add column automatically if missing (optional, safe)
if (!$hasProfilePic) {
		try {
				if ($conn->query("ALTER TABLE customer ADD COLUMN profile_pic VARCHAR(255) NULL")) {
						$hasProfilePic = true;
				}
		} catch (Throwable $e) {
				// ignore if no permission
		}
}

// Check/Add dob column
$hasDob = false;
$resDob = $conn->query("SELECT COUNT(*) AS cnt FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'customer' AND COLUMN_NAME = 'dob'");
if ($resDob) {
		$rowDob = $resDob->fetch_assoc();
		$hasDob = ((int)$rowDob['cnt'] > 0);
}
if (!$hasDob) {
		try {
				if ($conn->query("ALTER TABLE customer ADD COLUMN dob DATE NULL")) {
						$hasDob = true;
				}
		} catch (Throwable $e) {
				// ignore if no permission
		}
}

// Load current user
$selectCols = 'customer_id, username, fullname, email, phone, address' . ($hasProfilePic ? ', profile_pic' : '') . ($hasDob ? ', dob' : '');
$stmt = $conn->prepare("SELECT $selectCols FROM customer WHERE customer_id = ? LIMIT 1");
$stmt->bind_param('i', $customerId);
$stmt->execute();
$result = $stmt->get_result();
$profile = $result->fetch_assoc();
$stmt->close();

if (!$profile) {
		$message = 'Profile not found.';
		$messageType = 'error';
}

function initials($name) {
		$parts = preg_split('/\s+/', trim($name ?? ''));
		$letters = '';
		foreach ($parts as $p) {
				if ($p !== '') $letters .= strtoupper(substr($p, 0, 1));
				if (strlen($letters) >= 2) break;
		}
		return $letters ?: 'C';
}

function ensure_dir($path) {
		if (!is_dir($path)) {
				@mkdir($path, 0777, true);
		}
		return is_dir($path);
}

function safe_filename($original) {
		$ext = strtolower(pathinfo($original, PATHINFO_EXTENSION));
		$base = preg_replace('/[^a-zA-Z0-9_-]+/', '-', pathinfo($original, PATHINFO_FILENAME));
		$base = trim($base, '-');
		return $base . '-' . date('YmdHis') . '-' . bin2hex(random_bytes(4)) . ($ext ? ".{$ext}" : '');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_profile'])) {
		$newFullname = trim($_POST['fullname'] ?? '');
		$newUsername = trim($_POST['username'] ?? '');
		$newEmail = trim($_POST['email'] ?? '');
		$newPhone = trim($_POST['phone'] ?? '');
		$newAddress = trim($_POST['address'] ?? '');
		$newDob = null;
		if ($hasDob) {
				$dobInput = trim($_POST['dob'] ?? '');
				if ($dobInput !== '') {
						if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $dobInput)) {
								$message = 'Invalid date format for Date of Birth.';
								$messageType = 'error';
						} else {
								$newDob = $dobInput;
						}
				}
		}

		if ($newFullname === '' || $newUsername === '') {
				$message = 'Full name and username are required.';
				$messageType = 'error';
		} else {
				// Check username uniqueness (other users)
				$stmt = $conn->prepare("SELECT customer_id FROM customer WHERE username = ? AND customer_id <> ? LIMIT 1");
				$stmt->bind_param('si', $newUsername, $customerId);
				$stmt->execute();
				$dupRes = $stmt->get_result();
				$usernameTaken = $dupRes && $dupRes->num_rows > 0;
				$stmt->close();

				if ($usernameTaken) {
						$message = 'This username is already taken.';
						$messageType = 'error';
				} else {
						$uploadFileRel = null;
						if ($hasProfilePic && isset($_FILES['profile_pic']) && is_uploaded_file($_FILES['profile_pic']['tmp_name'])) {
								$allowed = ['jpg','jpeg','png','gif'];
								$sizeLimit = 2 * 1024 * 1024; // 2MB
								$ext = strtolower(pathinfo($_FILES['profile_pic']['name'], PATHINFO_EXTENSION));
								if (!in_array($ext, $allowed)) {
										$message = 'Invalid image type. Allowed: JPG, PNG, GIF.';
										$messageType = 'error';
								} elseif ($_FILES['profile_pic']['size'] > $sizeLimit) {
										$message = 'Image too large. Max 2MB.';
										$messageType = 'error';
								} else {
										$targetDir = realpath(__DIR__ . '/../resources') . DIRECTORY_SEPARATOR . 'ProfilePics';
										if (!ensure_dir($targetDir)) {
												$message = 'Failed to prepare upload directory.';
												$messageType = 'error';
										} else {
												$newName = safe_filename($_FILES['profile_pic']['name']);
												$targetPath = $targetDir . DIRECTORY_SEPARATOR . $newName;
												if (!move_uploaded_file($_FILES['profile_pic']['tmp_name'], $targetPath)) {
														$message = 'Failed to save uploaded image.';
														$messageType = 'error';
												} else {
														// Relative path for web: ../resources/ProfilePics/<file>
														$uploadFileRel = '../resources/ProfilePics/' . $newName;
												}
										}
								}
						}

						if ($messageType !== 'error') {
								if ($hasDob && $hasProfilePic && $uploadFileRel) {
										$stmt = $conn->prepare("UPDATE customer SET fullname=?, username=?, email=?, phone=?, address=?, dob=?, profile_pic=? WHERE customer_id=?");
										$stmt->bind_param('sssssssi', $newFullname, $newUsername, $newEmail, $newPhone, $newAddress, $newDob, $uploadFileRel, $customerId);
								} elseif ($hasDob && (!$hasProfilePic || !$uploadFileRel)) {
										$stmt = $conn->prepare("UPDATE customer SET fullname=?, username=?, email=?, phone=?, address=?, dob=? WHERE customer_id=?");
										$stmt->bind_param('ssssssi', $newFullname, $newUsername, $newEmail, $newPhone, $newAddress, $newDob, $customerId);
								} elseif (!$hasDob && $hasProfilePic && $uploadFileRel) {
										$stmt = $conn->prepare("UPDATE customer SET fullname=?, username=?, email=?, phone=?, address=?, profile_pic=? WHERE customer_id=?");
										$stmt->bind_param('ssssssi', $newFullname, $newUsername, $newEmail, $newPhone, $newAddress, $uploadFileRel, $customerId);
								} else {
										$stmt = $conn->prepare("UPDATE customer SET fullname=?, username=?, email=?, phone=?, address=? WHERE customer_id=?");
										$stmt->bind_param('sssssi', $newFullname, $newUsername, $newEmail, $newPhone, $newAddress, $customerId);
								}

								if ($stmt->execute()) {
										$message = 'Profile updated successfully.';
										$messageType = 'success';
										// Refresh profile data
										$stmt->close();
										$stmt = $conn->prepare("SELECT $selectCols FROM customer WHERE customer_id = ? LIMIT 1");
										$stmt->bind_param('i', $customerId);
										$stmt->execute();
										$result = $stmt->get_result();
										$profile = $result->fetch_assoc();
										$stmt->close();

										// Update session display values
										$_SESSION['fullname'] = $profile['fullname'] ?? $_SESSION['fullname'];
										$_SESSION['username'] = $profile['username'] ?? $_SESSION['username'];
										if ($hasProfilePic && !empty($profile['profile_pic'])) {
												$_SESSION['profile_pic'] = $profile['profile_pic'];
										}
								} else {
										$message = 'Failed to update profile: ' . $stmt->error;
										$messageType = 'error';
										$stmt->close();
								}
						}
				}
		}
}

$displayName = $_SESSION['fullname'] ?? $_SESSION['username'] ?? 'Customer';
$profilePic = $_SESSION['profile_pic'] ?? ($profile['profile_pic'] ?? null);
$initials = initials($displayName);
?>
<!DOCTYPE html>
<html lang="en">
<head>
		<meta charset="UTF-8">
		<meta name="viewport" content="width=device-width, initial-scale=1.0">
		<title>My Profile - Food Wave</title>
		<script src="https://cdn.tailwindcss.com"></script>
		<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
		<style>
				@import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap');
				:root {
					--dark-bg: #0b0b0d;
					--card-bg: #111318;
					--input-bg: #1b1e26;
					--input-border: #2b2f3a;
					--accent: #c20f1f;
				}
				body {
					font-family: 'Inter', sans-serif;
					background: var(--dark-bg);
					color: #e5e7eb;
					overflow-x: hidden;
				}
				.page-bg {
					position: fixed;
					inset: 0;
					z-index: -2;
					overflow: hidden;
				}
				.page-bg img {
					width: 100%; height: 100%; object-fit: cover; filter: brightness(0.35);
				}
				.page-overlay {
					position: fixed;
					inset: 0;
					background: radial-gradient(circle at 25% 25%, rgba(194,15,31,0.20), transparent 40%), rgba(0,0,0,0.55);
					z-index: -1;
				}
				.initials-circle { width: 64px; height: 64px; border-radius: 9999px; background: linear-gradient(135deg,#ef4444,#7f1d1d); display: inline-flex; align-items:center; justify-content:center; color:#fff; font-weight:800; font-size:20px; }
				.food-wave { font-weight:900; letter-spacing:2px; font-size:1.5rem; color:#ef4444; }
				/* Navbar styles to match main */
				header { position: fixed; top: 0; left: 0; right: 0; z-index: 50; background: rgba(0,0,0,0.7); backdrop-filter: blur(10px); }
				.logo-section { position: absolute; left: 2rem; top: 50%; transform: translateY(-50%); }
				.profile-section { position: absolute; right: 2rem; top: 50%; transform: translateY(-50%); }
				.nav-buttons { display: none; }
				@media (min-width: 768px) { .nav-buttons { display: flex; position: absolute; left: 50%; top: 50%; transform: translate(-50%, -50%); gap: 1rem; } }
				.profile-menu.show { display:block; }
				.input-dark { background: var(--input-bg); border: 1px solid var(--input-border); color: #e5e7eb; }
				.input-dark:focus { border-color: var(--accent); outline: none; box-shadow: 0 0 0 2px rgba(194,15,31,0.25); }
				.card-dark { background: var(--card-bg); border: 1px solid rgba(255,255,255,0.05); }
		</style>
		<script>
			function previewImage(input){
				const node = document.getElementById('avatarPreview');
				if(input.files && input.files[0]){
					const reader = new FileReader();
					reader.onload = e => {
						if (node.tagName === 'IMG') {
							node.src = e.target.result;
						} else {
							const img = document.createElement('img');
							img.id = 'avatarPreview';
							img.src = e.target.result;
							img.alt = 'Avatar';
							img.className = 'w-56 h-56 rounded-full object-cover border border-white/10 shadow-xl';
							node.replaceWith(img);
						}
					};
					reader.readAsDataURL(input.files[0]);
				}
			}
		</script>
	</head>
	<body>
		<div class="page-bg" aria-hidden="true"><img src="../resources/profile.jpg" alt="Background"></div>
		<div class="page-overlay" aria-hidden="true"></div>
		<!-- Header/Navbar (matching main) -->
		<header>
			<div class="relative h-16 max-w-7xl mx-auto px-4">
				<!-- Logo left -->
				<div class="logo-section">
					<h2 class="food-wave">Food Wave</h2>
				</div>

				<!-- Nav center (desktop) -->
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

				<!-- Profile right -->
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

		<main class="max-w-6xl mx-auto px-4 py-20">
			<h1 class="text-3xl font-extrabold text-white mb-8 flex items-center gap-3">
				<i class="fas fa-user text-red-500"></i>
				My Profile
			</h1>

			<?php if ($message): ?>
				<div id="flash" class="mb-6 p-4 rounded-xl <?= $messageType === 'success' ? 'bg-green-900/60 text-green-200' : 'bg-red-900/70 text-red-100' ?> border border-white/10 transition-opacity">
					<i class="fas fa-<?= $messageType === 'success' ? 'check-circle' : 'exclamation-circle' ?> mr-2"></i>
					<?= htmlspecialchars($message) ?>
				</div>
			<?php endif; ?>

			<form action="" method="POST" enctype="multipart/form-data">
				<div class="grid grid-cols-1 lg:grid-cols-2 gap-10 items-start">
					<!-- Left: Large image + upload -->
					<div class="card-dark rounded-2xl shadow-2xl p-6 flex flex-col items-center gap-6">
						<?php if (!empty($profilePic)): ?>
							<img id="avatarPreview" src="<?= htmlspecialchars($profilePic) ?>" alt="Avatar" class="w-56 h-56 rounded-full object-cover border border-white/10 shadow-xl">
						<?php else: ?>
							<div id="avatarPreview" class="w-56 h-56 rounded-full bg-gradient-to-br from-gray-800 to-gray-900 border border-white/10 shadow-xl flex items-center justify-center text-5xl font-extrabold text-gray-200"><?= htmlspecialchars($initials) ?></div>
						<?php endif; ?>
						<div class="w-full flex flex-col gap-2">
							<label class="text-sm font-semibold text-gray-200">Change Profile Picture</label>
							<input type="file" name="profile_pic" accept="image/*" onchange="previewImage(this)" class="block w-full text-sm file:mr-4 file:py-2 file:px-4 file:rounded file:border-0 file:text-sm file:font-semibold file:bg-[var(--accent)] file:text-white hover:file:brightness-110 input-dark" <?= $hasProfilePic ? '' : 'disabled' ?>>
							<?php if (!$hasProfilePic): ?>
								<p class="text-xs text-gray-400">Profile image column missing; upload disabled.</p>
							<?php endif; ?>
						</div>
					</div>

					<!-- Right: Form (single column) -->
					<div class="card-dark rounded-2xl shadow-2xl p-8 border border-white/10 space-y-5">
						<div>
							<label class="block text-sm font-semibold text-gray-200 mb-2">Username *</label>
							<input type="text" name="username" value="<?= htmlspecialchars($profile['username'] ?? '') ?>" required class="w-full px-4 py-3 rounded-lg input-dark">
						</div>
						<div>
							<label class="block text-sm font-semibold text-gray-200 mb-2">Full Name *</label>
							<input type="text" name="fullname" value="<?= htmlspecialchars($profile['fullname'] ?? '') ?>" required class="w-full px-4 py-3 rounded-lg input-dark">
						</div>
						<div>
							<label class="block text-sm font-semibold text-gray-200 mb-2">Contact No</label>
							<input type="text" name="phone" value="<?= htmlspecialchars($profile['phone'] ?? '') ?>" class="w-full px-4 py-3 rounded-lg input-dark">
						</div>
						<div>
							<label class="block text-sm font-semibold text-gray-200 mb-2">Email</label>
							<input type="email" name="email" value="<?= htmlspecialchars($profile['email'] ?? '') ?>" class="w-full px-4 py-3 rounded-lg input-dark">
						</div>
						<div>
							<label class="block text-sm font-semibold text-gray-200 mb-2">Date of Birth</label>
							<input type="date" name="dob" max="<?= date('Y-m-d') ?>" value="<?= $hasDob ? htmlspecialchars($profile['dob'] ?? '') : '' ?>" class="w-full px-4 py-3 rounded-lg input-dark" <?= $hasDob ? '' : 'disabled' ?>>
							<?php if (!$hasDob): ?>
								<p class="text-xs text-gray-400 mt-1">DOB column not found; field disabled.</p>
							<?php endif; ?>
						</div>
						<div>
							<label class="block text-sm font-semibold text-gray-200 mb-2">Address</label>
							<textarea name="address" rows="3" class="w-full px-4 py-3 rounded-lg input-dark"><?= htmlspecialchars($profile['address'] ?? '') ?></textarea>
						</div>

						<div class="flex flex-wrap gap-4 justify-end pt-2">
							<a href="./navbar.php" class="px-5 py-3 rounded-lg bg-gray-700 text-white hover:bg-gray-600">Cancel</a>
							<button type="submit" name="save_profile" class="px-5 py-3 rounded-lg bg-[var(--accent)] text-white font-semibold hover:brightness-110 shadow-lg">
								<i class="fas fa-save mr-2"></i>Update Profile
							</button>
						</div>
					</div>
				</div>
			</form>
		</main>

		<script>
			// Profile menu toggle
			document.getElementById('profileBtn').addEventListener('click', function(e){
				e.stopPropagation();
				document.getElementById('profileMenu').classList.toggle('show');
			});
			document.addEventListener('click', function(){
				document.getElementById('profileMenu').classList.remove('show');
			});

			// Auto-dismiss flash message
			(function(){
				var flash = document.getElementById('flash');
				if (flash) {
					setTimeout(function(){
						flash.style.opacity = '0';
						setTimeout(function(){ flash.remove(); }, 500);
					}, 3000);
				}
			})();
		</script>
	</body>
 </html>

