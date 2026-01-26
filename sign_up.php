<?php
include 'config/db_connection.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username']);
    $fullname = trim($_POST['fullname']);
    $email = trim($_POST['email']);
    $password = trim($_POST['password']);
    $user_type = $_POST['user_type']; // 'customer' or 'worker'

    if ($password === '') {
        echo "<script>alert('Password is required.');</script>";
        exit;
    }

    $password_hash = password_hash($password, PASSWORD_DEFAULT);

    if ($user_type === 'customer') {
        $sql = "INSERT INTO customer (username, fullname, email, password) VALUES (?, ?, ?, ?)";
    } else if ($user_type === 'worker') {
        // Unified table name: delivery_man
        $sql = "INSERT INTO delivery_man (username, fullname, email, password_hash) VALUES (?, ?, ?, ?)";
    } else {
        echo "<script>alert('Invalid user type.');</script>";
        exit;
    }

    $stmt = mysqli_prepare($conn, $sql);
    if ($stmt) {
        // Use password_hash instead of plaintext password
        mysqli_stmt_bind_param($stmt, "ssss", $username, $fullname, $email, $password_hash);
        if (mysqli_stmt_execute($stmt)) {
            echo "<script>alert('Registration successful!'); window.location.href='login.php';</script>";
        } else {
            $error = mysqli_error($conn);
            // Don't show raw DB errors in production, but keeping it simple for now as per original
            echo "<script>alert('Error: Could not register. " . addslashes($error) . "');</script>";
        }
        mysqli_stmt_close($stmt);
    } else {
        echo "<script>alert('Database error: " . addslashes(mysqli_error($conn)) . "');</script>";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Sign Up</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        .form-panel {
            transition: opacity 0.5s ease, transform 0.5s ease;
            position: absolute;
            top: 0;
            height: 100%;
            width: 50%;
            /* overflow-y: auto;  <-- removed to hide the vertical drag/scroll bar */
            overflow: hidden;
        }
        .panel-left { left: 0; }
        .panel-right { right: 0; }
        .visible { opacity: 1; pointer-events: auto; transform: translateX(0); z-index: 10; }
        .invisible-left { opacity: 0; pointer-events: none; transform: translateX(-100%); z-index: 0; }
        .invisible-right { opacity: 0; pointer-events: none; transform: translateX(100%); z-index: 0; }

        /* Custom radio button styles */
        .radio-custom {
            appearance: none;
            width: 1.25rem;
            height: 1.25rem;
            border: 2px solid #7f1d1d;
            background-color: #7f1d1d;
            cursor: pointer;
            position: relative;
        }
        
        .radio-custom:checked {
            background-color: #ef4444;
            border-color: #ef4444;
        }
        
        .radio-custom:checked::after {
            content: '';
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            width: 0.5rem;
            height: 0.5rem;
            background-color: white;
        }
    </style>
</head>
<body class="min-h-screen bg-cover bg-center bg-no-repeat" style="background-image: url('resources/sign_up.jpg');">
    <div class="flex items-center justify-center min-h-screen bg-black bg-opacity-50 relative">
        <div class="flex w-full max-w-5xl mx-auto rounded-lg overflow-hidden shadow-lg relative" style="min-height: 700px;">
            
            <!-- Customer Sign Up Form (Left) -->
            <div id="customerForm" class="form-panel panel-left bg-black bg-opacity-90 p-12 flex flex-col justify-center visible">
                <div class="flex flex-col items-center mb-8">
                    <h2 class="text-4xl font-bold text-red-600 text-center">Customer</h2>
                </div>
                <form method="POST" action="">
                    <input type="hidden" name="user_type" value="customer" />
                    
                    <div class="mb-5">
                        <label class="block text-white text-sm font-medium mb-2">Username</label>
                        <input type="text" name="username" placeholder="Enter username" class="w-full px-4 py-3 bg-gray-800 text-white border border-gray-700 focus:outline-none focus:border-red-500" required />
                    </div>
                    
                    <div class="mb-5">
                        <label class="block text-white text-sm font-medium mb-2">Full Name</label>
                        <input type="text" name="fullname" placeholder="Enter full name" class="w-full px-4 py-3 bg-gray-800 text-white border border-gray-700 focus:outline-none focus:border-red-500" required />
                    </div>
                    
                    <div class="mb-5">
                        <label class="block text-white text-sm font-medium mb-2">Email</label>
                        <input type="email" name="email" placeholder="Enter email" class="w-full px-4 py-3 bg-gray-800 text-white border border-gray-700 focus:outline-none focus:border-red-500" required />
                    </div>
                    
                    <div class="mb-7">
                        <label class="block text-white text-sm font-medium mb-2">Password</label>
                        <input type="password" name="password" placeholder="Enter password" class="w-full px-4 py-3 bg-gray-800 text-white border border-gray-700 focus:outline-none focus:border-red-500" required />
                    </div>
                    
                    <button type="submit" class="w-full py-3 bg-red-600 text-white font-bold hover:bg-red-700 transition mb-4 text-lg">Sign Up</button>
                    
                    <button type="button" id="showWorker" class="w-full py-3 bg-gray-700 text-white font-bold hover:bg-gray-600 transition mb-5 text-lg">
                        Sign Up as Delivery Man
                    </button>
                    
                    <div class="text-center">
                        <p class="text-gray-400 text-sm">
                            Already have an account? 
                            <a href="login.php" class="text-red-500 hover:text-red-400">Log In</a>
                        </p>
                    </div>
                </form>
            </div>
            
            <!-- Delivery Man Sign Up Form (Right) -->
            <div id="workerForm" class="form-panel panel-right bg-black bg-opacity-90 p-12 flex flex-col justify-center invisible-right">
                <div class="flex flex-col items-center mb-8">
                    <h2 class="text-4xl font-bold text-red-600 text-center">Delivery Man Sign Up</h2>
                </div>
                <form method="POST" action="">
                    <input type="hidden" name="user_type" value="worker" />
                    
                    <div class="mb-5">
                        <label class="block text-white text-sm font-medium mb-2">Username</label>
                        <input type="text" name="username" placeholder="Enter username" class="w-full px-4 py-3 bg-gray-800 text-white border border-gray-700 focus:outline-none focus:border-red-500" required />
                    </div>
                    
                    <div class="mb-5">
                        <label class="block text-white text-sm font-medium mb-2">Full Name</label>
                        <input type="text" name="fullname" placeholder="Enter full name" class="w-full px-4 py-3 bg-gray-800 text-white border border-gray-700 focus:outline-none focus:border-red-500" required />
                    </div>
                    
                    <div class="mb-5">
                        <label class="block text-white text-sm font-medium mb-2">Email</label>
                        <input type="email" name="email" placeholder="Enter email" class="w-full px-4 py-3 bg-gray-800 text-white border border-gray-700 focus:outline-none focus:border-red-500" required />
                    </div>
                    
                    <div class="mb-7">
                        <label class="block text-white text-sm font-medium mb-2">Password</label>
                        <input type="password" name="password" placeholder="Enter password" class="w-full px-4 py-3 bg-gray-800 text-white border border-gray-700 focus:outline-none focus:border-red-500" required />
                    </div>
                    
                    <button type="submit" class="w-full py-3 bg-red-600 text-white font-bold hover:bg-red-700 transition mb-4 text-lg">Sign Up</button>
                    
                    <button type="button" id="showCustomer" class="w-full py-3 bg-gray-700 text-white font-bold hover:bg-gray-600 transition mb-5 text-lg">
                        Sign Up as Customer
                    </button>
                    
                    <div class="text-center">
                        <p class="text-gray-400 text-sm">
                            Already have an account? 
                            <a href="login.php" class="text-red-500 hover:text-red-400">Log In</a>
                        </p>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <script>
        // Slide + fade transition between forms
        const customerForm = document.getElementById('customerForm');
        const workerForm = document.getElementById('workerForm');
        
        document.getElementById('showWorker').onclick = function() {
            customerForm.classList.remove('visible');
            customerForm.classList.add('invisible-left');
            workerForm.classList.remove('invisible-right');
            workerForm.classList.add('visible');
        };
        
        document.getElementById('showCustomer').onclick = function() {
            workerForm.classList.remove('visible');
            workerForm.classList.add('invisible-right');
            customerForm.classList.remove('invisible-left');
            customerForm.classList.add('visible');
        };
    </script>
</body>
</html>