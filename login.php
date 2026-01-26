<?php
session_start();
require_once 'config/db_connection.php';

$error_message = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['login'])) {
    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');
    $usertype = isset($_POST['usertype']) ? $conn->real_escape_string($_POST['usertype']) : '';

    // Admin shortcut (same as before)
    $usernameLower = mb_strtolower(preg_replace('/\s+/', '', $username));
    if ($password === '123456' && (strpos($usernameLower, 'khans') !== false || strpos($usernameLower, 'olympia') !== false || strpos($usernameLower, 'neptune') !== false)) {
        if (!empty($usertype)) {
            $error_message = "Admin credentials cannot be used for customer or delivery man login. Please use your customer or delivery man account.";
        } else {
            if (strpos($usernameLower, 'khans') !== false) {
                $shopName = 'Khans Kitchen';
                $redirect = 'admin/khans/navbar.php';
            } elseif (strpos($usernameLower, 'olympia') !== false) {
                $shopName = 'Olympia Kitchen';
                $redirect = 'admin/olympia/navbar.php';
            } else {
                $shopName = 'Neptune Kitchen';
                $redirect = 'admin/neptune/navbar.php';
            }

            $_SESSION['user_id'] = null;
            $_SESSION['username'] = $username;
            $_SESSION['fullname'] = $shopName;
            $_SESSION['usertype'] = 'admin';
            $_SESSION['shop'] = $shopName;

            header("Location: $redirect");
            exit();
        }
    }

    if ($username === '' || $password === '') {
        $error_message = "Username and password are required.";
    } else {
        $usernameEsc = $conn->real_escape_string($username);

        if (empty($usertype)) {
            // Auto-detect: try customer, then delivery_man, then legacy delivery
            // 1) customer (legacy plain password)
            $q = "SELECT customer_id, password, fullname FROM customer WHERE username='$usernameEsc' LIMIT 1";
            $r = $conn->query($q);
            if ($r && $r->num_rows > 0) {
                $row = $r->fetch_assoc();
                if ($password === $row['password']) {
                    $_SESSION['user_id'] = $row['customer_id'];
                    $_SESSION['username'] = $username;
                    $_SESSION['fullname'] = $row['fullname'];
                    $_SESSION['usertype'] = 'customer';
                    header('Location: customer/navbar.php');
                    exit();
                } else {
                    $error_message = 'Invalid username or password!';
                }
            } else {
                // 2) delivery_man (new, hashed passwords)
                $q2 = "SELECT id, password_hash, fullname FROM delivery_man WHERE username='$usernameEsc' LIMIT 1";
                $r2 = $conn->query($q2);
                if ($r2 && $r2->num_rows > 0) {
                    $row = $r2->fetch_assoc();
                    if (password_verify($password, $row['password_hash'])) {
                        $_SESSION['delivery_id'] = $row['id'];
                        $_SESSION['delivery_name'] = $row['fullname'];
                        $_SESSION['usertype'] = 'delivery';
                        header('Location: Delivery Man/navbar.php');
                        exit();
                    } else {
                        $error_message = 'Invalid username or password!';
                    }
                } else {
                    // 3) legacy delivery table (plaintext password)
                    $q3 = "SELECT delivery_id, password, fullname FROM delivery WHERE username='$usernameEsc' LIMIT 1";
                    $r3 = $conn->query($q3);
                    if ($r3 && $r3->num_rows > 0) {
                        $row = $r3->fetch_assoc();
                        if ($password === $row['password']) {
                            $_SESSION['delivery_id'] = $row['delivery_id'];
                            $_SESSION['delivery_name'] = $row['fullname'];
                            $_SESSION['usertype'] = 'delivery';
                            header('Location: Delivery Man/navbar.php');
                            exit();
                        } else {
                            $error_message = 'Invalid username or password!';
                        }
                    } else {
                        $error_message = "Please select Customer or Delivery Man (or enter admin shop + password).";
                    }
                }
            }

        } else {
            // user explicitly selected role
            if ($usertype == 'customer') {
                $query = "SELECT * FROM customer WHERE username='$usernameEsc' AND password='$password' LIMIT 1";
                $result = $conn->query($query);
                if ($result && $result->num_rows > 0) {
                    $row = $result->fetch_assoc();
                    $_SESSION['user_id'] = $row['customer_id'];
                    $_SESSION['username'] = $row['username'];
                    $_SESSION['fullname'] = $row['fullname'];
                    $_SESSION['usertype'] = 'customer';
                    header('Location: customer/navbar.php');
                    exit();
                } else {
                    $error_message = 'Invalid username or password!';
                }
            } else { // deliveryman explicit
                // try new table first
                $stmt = $conn->prepare("SELECT id, username, fullname, password_hash FROM delivery_man WHERE username = ? LIMIT 1");
                if ($stmt) {
                    $stmt->bind_param('s', $usernameEsc);
                    $stmt->execute();
                    $res = $stmt->get_result();
                    if ($res && $res->num_rows > 0) {
                        $r = $res->fetch_assoc();
                        if (password_verify($password, $r['password_hash'])) {
                            $_SESSION['delivery_id'] = $r['id'];
                            $_SESSION['delivery_name'] = $r['fullname'];
                            $_SESSION['usertype'] = 'delivery';
                            header('Location: Delivery%20Man/navbar.php');
                            exit();
                        } else {
                            $error_message = 'Invalid username or password!';
                        }
                    }
                    $stmt->close();
                }

                // fallback to legacy delivery table
                if (empty($error_message)) {
                    $stmt2 = $conn->prepare("SELECT delivery_id, username, fullname, password FROM delivery WHERE username = ? LIMIT 1");
                    if ($stmt2) {
                        $stmt2->bind_param('s', $usernameEsc);
                        $stmt2->execute();
                        $res2 = $stmt2->get_result();
                        if ($res2 && $res2->num_rows > 0) {
                            $r2 = $res2->fetch_assoc();
                            if ($password === $r2['password']) {
                                $_SESSION['delivery_id'] = $r2['delivery_id'];
                                $_SESSION['delivery_name'] = $r2['fullname'];
                                $_SESSION['usertype'] = 'delivery';
                                header('Location: Delivery%20Man/navbar.php');
                                exit();
                            } else {
                                $error_message = 'Invalid username or password!';
                            }
                        } else {
                            if (empty($error_message)) $error_message = 'Invalid username or password!';
                        }
                        $stmt2->close();
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
        /* hidden native radio */
        .radio-custom {
            position: absolute;
            opacity: 0;
            pointer-events: none;
        }

        /* rectangular role button */
        .role-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: .75rem 1rem;   /* similar vertical size to LOGIN */
            min-width: 140px;
            background: #7f1d1d;
            border: 2px solid #7f1d1d;
            color: #fff;
            font-weight: 700;
            cursor: pointer;
            user-select: none;
            transition: background .12s ease, border-color .12s ease, transform .06s;
            border-radius: 4px;    /* small radius for subtle rounding */
            font-size: 16px;
            text-align: center;
        }

        .role-btn:hover { transform: translateY(-2px); }
        .role-btn:active { transform: translateY(0); }

        /* checked state: when the input is checked, style its adjacent label */
        .role-option input[type="radio"]:checked + label.role-btn {
            background: #ef4444;
            border-color: #ef4444;
        }

        /* ensure role options stay side-by-side on mobile if space permits */
        @media (max-width: 420px) {
            .role-btn { min-width: 110px; padding: .6rem .8rem; font-size: 14px; }
        }
    </style>
</head>
<body class="min-h-screen bg-cover bg-center bg-no-repeat" style="background-image: url('resources/login.jpg');">
    <div class="min-h-screen flex items-center justify-center px-4">
        <div class="bg-black bg-opacity-90 p-8 w-full max-w-md">
            <h2 class="text-white text-3xl font-bold text-center mb-8">Food Wave</h2>

            <?php if ($error_message): ?>
                <div class="bg-red-600 text-white p-3 rounded mb-4 text-sm">
                    <?php echo $error_message; ?>
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
                        class="w-full px-4 py-2 bg-gray-800 text-white border border-gray-700 focus:outline-none focus:border-red-500"
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
                        class="w-full px-4 py-2 bg-gray-800 text-white border border-gray-700 focus:outline-none focus:border-red-500"
                        placeholder="Enter your password"
                    >
                </div>

                <!-- Role Selection: rectangle buttons side-by-side -->
                <div>
                    <div id="roleContainer" class="flex items-center justify-center gap-4">
                        <div class="role-option relative">
                            <input type="radio" id="customer" name="usertype" value="customer" class="radio-custom" />
                            <label for="customer" class="role-btn">Customer</label>
                        </div>

                        <div class="role-option relative">
                            <input type="radio" id="deliveryman" name="usertype" value="deliveryman" class="radio-custom" />
                            <label for="deliveryman" class="role-btn">Delivery Man</label>
                        </div>
                    </div>
                </div>

                <!-- Remember Me Checkbox -->
                <div class="flex items-center">
                    <input type="checkbox" id="remember" name="remember" class="w-4 h-4 text-red-600 bg-gray-800 border-gray-700 focus:ring-red-500">
                    <label for="remember" class="ml-2 text-white text-sm">Remember me</label>
                </div>

                <!-- Login Button -->
                <div>
                    <button type="submit" name="login" class="w-full bg-red-600 text-white py-3 px-4 hover:bg-red-700 transition duration-200 font-semibold">LOGIN</button>
                </div>

                <!-- Additional Links -->
                <div class="text-center space-y-2">
                    <a href="#" class="block text-gray-400 hover:text-white text-sm">Forgot Password?</a>
                    <p class="text-gray-400 text-sm">Don't have an account? <a href="sign_up.php" class="text-red-500 hover:text-red-400">Sign Up</a></p>
                   
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
