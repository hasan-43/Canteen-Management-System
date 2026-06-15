<?php
session_start();
require_once 'config/db_connection.php';

$error_message = '';
$selectedType = isset($_POST['usertype']) ? $_POST['usertype'] : '';

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['login'])) {
    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');
    $usertype = isset($_POST['usertype']) ? $conn->real_escape_string($_POST['usertype']) : '';

    if ($username === '' || $password === '') {
        $error_message = "Username and password are required.";
    } else {
        $usernameEsc = $conn->real_escape_string($username);
        $usernameLower = mb_strtolower(preg_replace('/\s+/', '', $username));

        // 1. Check if user credentials match an Admin/Shopowner (no type select required)
        $isAdminMatch = false;

        // Try admin shortcut
        if ($password === '123456' && (strpos($usernameLower, 'khans') !== false || strpos($usernameLower, 'olympia') !== false || strpos($usernameLower, 'neptune') !== false)) {
            if (!empty($usertype)) {
                $error_message = "Admin credentials cannot be used for customer or delivery man login. Please use your customer or delivery man account.";
            } else {
                if (strpos($usernameLower, 'khans') !== false) {
                    $shopName = 'Khans Kitchen';
                    $shopTable = 'khans';
                    $redirect = 'admin/khans/navbar.php';
                } elseif (strpos($usernameLower, 'olympia') !== false) {
                    $shopName = 'Olympia Kitchen';
                    $shopTable = 'olympia';
                    $redirect = 'admin/olympia/navbar.php';
                } else {
                    $shopName = 'Neptune Kitchen';
                    $shopTable = 'neptune';
                    $redirect = 'admin/neptune/navbar.php';
                }

                $_SESSION['user_id'] = null;
                $_SESSION['username'] = $username;
                $_SESSION['fullname'] = $shopName;
                $_SESSION['usertype'] = 'admin';
                $_SESSION['shop'] = $shopName;
                $_SESSION['shop_table'] = $shopTable;

                header("Location: $redirect");
                exit();
            }
            $isAdminMatch = true;
        }

        // Try shop table check (dynamic shop owners)
        if (!$isAdminMatch && empty($error_message)) {
            $shopCheck = $conn->query("SELECT * FROM shop WHERE shop_name = '$usernameEsc' LIMIT 1");
            if ($shopCheck && $shopCheck->num_rows > 0) {
                if (!empty($usertype)) {
                    $error_message = "Admin credentials cannot be used for customer or delivery man login. Please use your customer or delivery man account.";
                } else {
                    $shopRow = $shopCheck->fetch_assoc();
                    if ($password === $shopRow['password']) {
                        $shop_table = $shopRow['shop_name'];
                        $shopName = ucfirst($shop_table) . ' Kitchen';

                        $_SESSION['user_id'] = null;
                        $_SESSION['username'] = $shop_table;
                        $_SESSION['fullname'] = $shopName;
                        $_SESSION['usertype'] = 'admin';
                        $_SESSION['shop'] = $shopName;
                        $_SESSION['shop_table'] = $shop_table;

                        if (in_array(strtolower($shop_table), ['khans', 'olympia', 'neptune'])) {
                            $redirect = "admin/" . strtolower($shop_table) . "/navbar.php";
                        } else {
                            $redirect = "admin/general/navbar.php";
                        }

                        header("Location: $redirect");
                        exit();
                    } else {
                        $error_message = 'Invalid username or password!';
                    }
                }
                $isAdminMatch = true;
            }
        }

        // 2. Validate Customer or Delivery man logins
        if (empty($error_message)) {
            if (empty($usertype)) {
                // If it wasn't a valid admin/shop and no usertype is selected, they must select a type
                $error_message = "Please select your user type (Customer or Delivery Man) to log in.";
            } else {
                if ($usertype === 'customer') {
                    $q = "SELECT customer_id, password, fullname, status, profile_pic FROM customer WHERE username='$usernameEsc' LIMIT 1";
                    $r = $conn->query($q);
                    if ($r && $r->num_rows > 0) {
                        $row = $r->fetch_assoc();
                        if (isset($row['status']) && $row['status'] === 'inactive') {
                            $error_message = 'Your account is currently inactive.';
                        } else {
                            $passwordMatch = false;
                            if ($password === $row['password'] || password_verify($password, $row['password'])) {
                                $passwordMatch = true;
                            }
                            if ($passwordMatch) {
                                $_SESSION['user_id'] = $row['customer_id'];
                                $_SESSION['username'] = $username;
                                $_SESSION['fullname'] = $row['fullname'];
                                $_SESSION['usertype'] = 'customer';
                                $_SESSION['profile_pic'] = $row['profile_pic'] ?? null;
                                header('Location: customer/navbar.php');
                                exit();
                            } else {
                                $error_message = 'Invalid username or password!';
                            }
                        }
                    } else {
                        $error_message = 'Invalid username or password!';
                    }
                } elseif ($usertype === 'deliveryman' || $usertype === 'delivery') {
                    $q = "SELECT delivery_id, password, fullname, status FROM delivery WHERE username='$usernameEsc' LIMIT 1";
                    $r = $conn->query($q);
                    if ($r && $r->num_rows > 0) {
                        $row = $r->fetch_assoc();
                        if (isset($row['status']) && $row['status'] === 'blocked') {
                            $error_message = 'Your account has been blocked. Please contact the administrator.';
                        } else {
                            $passwordMatch = false;
                            if ($password === $row['password'] || password_verify($password, $row['password'])) {
                                $passwordMatch = true;
                            }
                            if ($passwordMatch) {
                                $_SESSION['delivery_id'] = $row['delivery_id'];
                                $_SESSION['delivery_name'] = $row['fullname'];
                                $_SESSION['usertype'] = 'delivery';
                                header('Location: Delivery Man/navbar.php');
                                exit();
                            } else {
                                $error_message = 'Invalid username or password!';
                            }
                        }
                    } else {
                        $error_message = 'Invalid username or password!';
                    }
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title>Login - Canteen Management System</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&display=swap');
        
        body {
            font-family: 'Outfit', sans-serif;
        }
        
        /* hidden native radio */
        .radio-custom {
            position: absolute;
            opacity: 0;
            pointer-events: none;
        }

        /* premium role button */
        .role-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: .75rem 1rem;
            min-width: 140px;
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(255, 255, 255, 0.1);
            color: rgba(255, 255, 255, 0.8);
            font-weight: 600;
            cursor: pointer;
            user-select: none;
            transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1);
            border-radius: 12px;
            font-size: 15px;
            text-align: center;
        }

        .role-btn:hover {
            background: rgba(255, 255, 255, 0.1);
            border-color: rgba(255, 255, 255, 0.25);
            color: #fff;
            transform: translateY(-2px);
        }
        
        .role-btn:active {
            transform: translateY(0);
        }

        /* checked state: when the input is checked, style its adjacent label */
        .role-option input[type="radio"]:checked + label.role-btn {
            background: #ef4444;
            border-color: #ef4444;
            color: #fff;
            box-shadow: 0 0 20px rgba(239, 68, 68, 0.4);
        }

        /* ensure role options stay side-by-side on mobile if space permits */
        @media (max-width: 420px) {
            .role-btn { min-width: 110px; padding: .6rem .8rem; font-size: 14px; }
        }
        
        .glass-card {
            background: rgba(10, 10, 12, 0.85);
            backdrop-filter: blur(16px);
            -webkit-backdrop-filter: blur(16px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5), 0 0 40px rgba(239, 68, 68, 0.15);
        }
        
        @keyframes shake {
            0%, 100% { transform: translateX(0); }
            25% { transform: translateX(-6px); }
            75% { transform: translateX(6px); }
        }
        .animate-shake {
            animation: shake 0.35s ease-in-out;
        }
    </style>
</head>
<body class="min-h-screen bg-cover bg-center bg-no-repeat flex items-center justify-center" style="background-image: url('resources/Log_IN.jpg');">
    <div class="w-full max-w-md px-4 py-8">
        <div class="glass-card p-8 md:p-10 w-full rounded-2xl">
            <div class="flex flex-col items-center mb-8">
                <img src="resources/logo.jpg" alt="Logo" class="w-20 h-20 rounded-full border-2 border-red-500 shadow-lg object-cover mb-3">
                <h2 class="text-white text-3xl font-black text-center tracking-tight">Campus Cravings</h2>
                <p class="text-gray-400 text-xs mt-1 text-center font-medium">Delicious Food, Delivered Fast</p>
            </div>

            <?php if ($error_message): ?>
                <div class="bg-red-500/10 border border-red-500/30 text-red-200 p-4 rounded-xl mb-6 text-sm font-medium flex items-start gap-3 shadow-lg shadow-red-950/20 animate-shake">
                    <span class="text-red-400 text-lg leading-none">⚠️</span>
                    <div>
                        <span class="font-extrabold text-red-400 block mb-0.5">Login Failed</span>
                        <?= htmlspecialchars($error_message) ?>
                    </div>
                </div>
            <?php endif; ?>

            <form id="loginForm" action="" method="POST" class="space-y-6">
                <!-- Username/Shop Input -->
                <div>
                    <label for="username" class="block text-white text-sm font-medium mb-2">Username</label>
                    <input 
                        type="text" 
                        id="username" 
                        name="username"
                        required
                        class="w-full px-4 py-3 bg-white/5 text-white border border-white/10 rounded-xl focus:outline-none focus:border-red-500 focus:ring-2 focus:ring-red-500/20 transition duration-200"
                        placeholder="Enter your username"
                    >
                </div>

                <!-- Password Input -->
                <div>
                    <label for="password" class="block text-white text-sm font-medium mb-2">Password</label>
                    <input 
                        type="password" 
                        id="password" 
                        name="password" 
                        required
                        class="w-full px-4 py-3 bg-white/5 text-white border border-white/10 rounded-xl focus:outline-none focus:border-red-500 focus:ring-2 focus:ring-red-500/20 transition duration-200"
                        placeholder="Enter your password"
                    >
                </div>

                <!-- Role Selection: rectangle buttons side-by-side -->
                <div>
                    <div id="roleContainer" class="flex items-center justify-center gap-4">
                        <div class="role-option relative">
                            <input type="radio" id="customer" name="usertype" value="customer" class="radio-custom" <?= $selectedType === 'customer' ? 'checked' : '' ?> />
                            <label for="customer" class="role-btn">Customer</label>
                        </div>

                        <div class="role-option relative">
                            <input type="radio" id="deliveryman" name="usertype" value="deliveryman" class="radio-custom" <?= $selectedType === 'deliveryman' ? 'checked' : '' ?> />
                            <label for="deliveryman" class="role-btn">Delivery Man</label>
                        </div>
                    </div>
                </div>

                <!-- Remember Me Checkbox -->
                <div class="flex items-center">
                    <input type="checkbox" id="remember" name="remember" class="w-4 h-4 text-red-600 bg-gray-800 border-gray-700 focus:ring-red-500 rounded">
                    <label for="remember" class="ml-2 text-white text-sm">Remember me</label>
                </div>

                <!-- Login Button -->
                <div>
                    <button type="submit" name="login" class="w-full bg-gradient-to-r from-red-600 to-red-500 text-white py-3.5 px-4 rounded-xl hover:from-red-500 hover:to-red-400 transition duration-300 font-bold shadow-lg shadow-red-900/30">LOGIN</button>
                </div>

                <!-- Additional Links -->
                <div class="text-center space-y-3">
                    <a href="#" class="block text-gray-400 hover:text-white text-sm">Forgot Password?</a>
                    <p class="text-gray-400 text-sm">Don't have an account? <a href="sign_up.php" class="text-red-500 hover:text-red-400 font-semibold">Sign Up</a></p>
                    <div class="pt-2">
                        <a href="shop_signup.php" class="inline-block w-full bg-transparent border border-red-600 text-red-500 py-2.5 hover:bg-red-600 hover:text-white transition font-bold rounded-xl text-sm">Sign in as Shop Owner</a>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <script>
        // When clicking outside the login form, clear role selection
        document.addEventListener('click', function (e) {
            const formEl = document.getElementById('loginForm');
            // if the click is not inside the whole form, uncheck radios
            if (formEl && !formEl.contains(e.target)) {
                const roleContainer = document.getElementById('roleContainer');
                if (roleContainer) {
                    const radios = roleContainer.querySelectorAll('input[type="radio"]');
                    radios.forEach(r => r.checked = false);
                    // clear wasChecked flags too
                    radios.forEach(r => r.wasChecked = false);
                }
            }
        });

        // Allow toggle off if clicking the already-selected role button again
        const roleOptions = document.querySelectorAll('.role-option input[type="radio"]');
        roleOptions.forEach(radio => {
            radio.wasChecked = radio.checked; // Initialize based on current state (persists on reload)
            radio.addEventListener('click', function (e) {
                if (radio.wasChecked) {
                    radio.checked = false;
                    radio.wasChecked = false;
                } else {
                    roleOptions.forEach(r => r.wasChecked = false);
                    radio.wasChecked = radio.checked;
                }
            });
        });
    </script>
</body>
</html>
<?php
