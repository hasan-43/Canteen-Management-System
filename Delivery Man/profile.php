<?php
session_start();
require_once __DIR__ . '/../config/db_connection.php';

if (!isset($_SESSION['usertype']) || $_SESSION['usertype'] !== 'delivery') {
    header('Location: ../login.php');
    exit;
}

$delivery_id = intval($_SESSION['delivery_id']);
$message = '';
$messageType = 'success';

// Check if profile_pic column exists
$hasProfilePic = false;
$res = $conn->query("SELECT COUNT(*) AS cnt FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'delivery' AND COLUMN_NAME = 'profile_pic'");
if ($res) {
    $row = $res->fetch_assoc();
    $hasProfilePic = ((int)$row['cnt'] > 0);
}

// Try to add column automatically if missing
if (!$hasProfilePic) {
    try {
        if ($conn->query("ALTER TABLE delivery ADD COLUMN profile_pic VARCHAR(255) NULL")) {
            $hasProfilePic = true;
        }
    } catch (Throwable $e) {
        // ignore if no permission
    }
}

// Check/Add dob column
$hasDob = false;
$resDob = $conn->query("SELECT COUNT(*) AS cnt FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'delivery' AND COLUMN_NAME = 'dob'");
if ($resDob) {
    $rowDob = $resDob->fetch_assoc();
    $hasDob = ((int)$rowDob['cnt'] > 0);
}
if (!$hasDob) {
    try {
        if ($conn->query("ALTER TABLE delivery ADD COLUMN dob DATE NULL")) {
            $hasDob = true;
        }
    } catch (Throwable $e) {
        // ignore if no permission
    }
}

// Check/Add address column
$hasAddress = false;
$resAddress = $conn->query("SELECT COUNT(*) AS cnt FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'delivery' AND COLUMN_NAME = 'address'");
if ($resAddress) {
    $rowAddress = $resAddress->fetch_assoc();
    $hasAddress = ((int)$rowAddress['cnt'] > 0);
}
if (!$hasAddress) {
    try {
        if ($conn->query("ALTER TABLE delivery ADD COLUMN address TEXT NULL")) {
            $hasAddress = true;
        }
    } catch (Throwable $e) {
        // ignore if no permission
    }
}

// Load current delivery person
$selectCols = 'delivery_id, username, fullname, email, phone' . ($hasProfilePic ? ', profile_pic' : '') . ($hasDob ? ', dob' : '') . ($hasAddress ? ', address' : '');
$stmt = $conn->prepare("SELECT $selectCols FROM delivery WHERE delivery_id = ? LIMIT 1");
$stmt->bind_param('i', $delivery_id);
$stmt->execute();
$result = $stmt->get_result();
$profile = $result->fetch_assoc();
$stmt->close();

if (!$profile) {
    $message = 'Profile not found.';
    $messageType = 'error';
}

$profilePic = '';
if ($hasProfilePic && !empty($profile['profile_pic'])) {
    $profilePic = $profile['profile_pic'];
}

$displayName = $profile['fullname'] ?? 'Driver';

function initials($name) {
    $parts = preg_split('/\s+/', trim($name ?? ''));
    $letters = '';
    foreach ($parts as $p) {
        if ($p !== '') $letters .= strtoupper(substr($p, 0, 1));
        if (strlen($letters) >= 2) break;
    }
    return $letters ?: 'D';
}
$initials = initials($displayName);

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

    if (empty($newFullname) || empty($newUsername)) {
        $message = 'Username and Full Name are required.';
        $messageType = 'error';
    } elseif ($messageType !== 'error') {
        // Handle profile picture upload
        $newProfilePic = $profile['profile_pic'] ?? null;
        if ($hasProfilePic && isset($_FILES['profile_pic']) && $_FILES['profile_pic']['error'] === UPLOAD_ERR_OK) {
            $tmpName = $_FILES['profile_pic']['tmp_name'];
            $origName = $_FILES['profile_pic']['name'];
            $allowedExts = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
            $ext = strtolower(pathinfo($origName, PATHINFO_EXTENSION));
            if (!in_array($ext, $allowedExts)) {
                $message = 'Invalid image format. Allowed: jpg, jpeg, png, gif, webp.';
                $messageType = 'error';
            } else {
                $uploadDir = __DIR__ . '/../resources/ProfilePics';
                if (ensure_dir($uploadDir)) {
                    $safeName = safe_filename($origName);
                    $destPath = $uploadDir . '/' . $safeName;
                    if (move_uploaded_file($tmpName, $destPath)) {
                        $newProfilePic = '../resources/ProfilePics/' . $safeName;
                        // Delete old pic if exists
                        if (!empty($profile['profile_pic'])) {
                            $oldPath = __DIR__ . '/../' . ltrim($profile['profile_pic'], './');
                            if (file_exists($oldPath)) @unlink($oldPath);
                        }
                    }
                }
            }
        }

        if ($messageType !== 'error') {
            $updateCols = 'fullname=?, username=?, email=?, phone=?';
            $types = 'ssss';
            $params = [$newFullname, $newUsername, $newEmail, $newPhone];
            
            if ($hasAddress) {
                $updateCols .= ', address=?';
                $types .= 's';
                $params[] = $newAddress;
            }
            
            if ($hasDob) {
                $updateCols .= ', dob=?';
                $types .= 's';
                $params[] = $newDob;
            }
            
            if ($hasProfilePic) {
                $updateCols .= ', profile_pic=?';
                $types .= 's';
                $params[] = $newProfilePic;
            }
            
            $params[] = $delivery_id;
            $types .= 'i';

            $stmt = $conn->prepare("UPDATE delivery SET $updateCols WHERE delivery_id=?");
            $stmt->bind_param($types, ...$params);
            
            if ($stmt->execute()) {
                $_SESSION['delivery_name'] = $newFullname;
                $message = 'Profile updated successfully!';
                $messageType = 'success';
                
                // Reload profile
                $stmt2 = $conn->prepare("SELECT $selectCols FROM delivery WHERE delivery_id = ? LIMIT 1");
                $stmt2->bind_param('i', $delivery_id);
                $stmt2->execute();
                $result2 = $stmt2->get_result();
                $profile = $result2->fetch_assoc();
                $stmt2->close();
                
                // Update display variables
                $profilePic = '';
                if ($hasProfilePic && !empty($profile['profile_pic'])) {
                    $profilePic = $profile['profile_pic'];
                }
                $displayName = $profile['fullname'] ?? 'Driver';
                $initials = initials($displayName);
            } else {
                $message = 'Failed to update profile.';
                $messageType = 'error';
            }
            $stmt->close();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<HEAD>
    <script src="../resources/js/theme.js"></script>
    <script src="../resources/js/theme.js"></script>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile - Campus Cravings</title>
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
        .campus-cravings { font-weight:900; letter-spacing:2px; font-size:1.5rem; color:#ef4444; }
        header { position: fixed; top: 0; left: 0; right: 0; z-index: 50; background: rgba(0,0,0,0.7); backdrop-filter: blur(10px); }
        .logo-section { position: absolute; left: 2rem; top: 50%; transform: translateY(-50%); }
        .profile-section { position: absolute; right: 2rem; top: 50%; transform: translateY(-50%); }
        .nav-buttons { display: none; }
        @media (min-width: 768px) { .nav-buttons { display: flex; position: absolute; left: 50%; top: 50%; transform: translate(-50%, -50%); gap: 1rem; } }
        .profile-menu.show { display:block; }
        .profile-icon{width:40px;height:40px;border-radius:9999px;object-fit:cover;border:2px solid #ef4444;}
        .profile-btn{cursor:pointer;transition:opacity 0.2s;}
        .profile-btn:hover{opacity:0.8;}
        .profile-menu{position:absolute;right:0;margin-top:0.75rem;width:13rem;background:#0f172a;background:rgba(15,23,42,0.95);border-radius:0.5rem;border:1px solid #374151;box-shadow:0 10px 40px rgba(0,0,0,0.6);backdrop-filter:blur(8px);display:none;z-index:80;}
        .profile-menu a{display:block;padding:0.625rem 1rem;color:#f9fafb;font-weight:600;letter-spacing:0.01em;transition:background 0.2s;}
        .profile-menu a:hover{background:rgba(55,65,81,0.7);border-radius:0.375rem;}
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
    
    <!-- Header/Navbar -->
    <header>
        <div class="relative h-16 max-w-7xl mx-auto px-4">
            <div class="logo-section">
                <h2 class="campus-cravings">Campus Cravings</h2>
            </div>

            <nav class="nav-buttons">
                <a href="navbar.php" class="px-4 py-2 bg-transparent hover:bg-red-600 hover:bg-opacity-80 rounded text-sm">Home</a>
                <a href="orders.php" class="px-4 py-2 rounded text-sm hover:bg-red-600 hover:bg-opacity-80">Orders</a>
                <a href="details.php" class="px-4 py-2 rounded text-sm hover:bg-red-600 hover:bg-opacity-80">Details</a>
                <a href="chat.php" class="px-4 py-2 rounded text-sm hover:bg-red-600 hover:bg-opacity-80">Chat</a>
            </nav>

            <div class="profile-section">
                <div class="relative" id="profileRoot">
                    <button id="profileBtn" type="button" class="flex items-center gap-2 p-1 rounded focus:outline-none profile-btn">
                        <?php if (!empty($profilePic)): ?>
                            <img src="<?= htmlspecialchars($profilePic) ?>" alt="Profile" class="profile-icon" />
                        <?php else: ?>
                            <div class="initials-circle" style="width:40px;height:40px;font-size:16px;"><?= htmlspecialchars($initials) ?></div>
                        <?php endif; ?>
                        <span class="hidden sm:inline-block text-sm text-white"><?= htmlspecialchars($displayName) ?></span>
                    </button>

                    <div id="profileMenu" class="profile-menu">
                        <a href="profile.php">Profile</a>
                        <a href="logout.php" class="text-red-300">Log out</a>
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

                <!-- Right: Form -->
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
                        <textarea name="address" rows="3" class="w-full px-4 py-3 rounded-lg input-dark" <?= $hasAddress ? '' : 'disabled' ?>><?= $hasAddress ? htmlspecialchars($profile['address'] ?? '') : '' ?></textarea>
                        <?php if (!$hasAddress): ?>
                            <p class="text-xs text-gray-400 mt-1">Address column not found; field disabled.</p>
                        <?php endif; ?>
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
