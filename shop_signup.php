<?php
// shop_signup.php
session_start();
include 'config/db_connection.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $shop_name_input = trim($_POST['shop_name'] ?? '');
    $password = trim($_POST['password'] ?? '');

    if ($shop_name_input === '' || $password === '') {
        echo "<script>alert('Restaurant Name and Password are required.');</script>";
    } else {
        // Sanitize shop name to lowercase alphanumeric + underscores
        $shop_name = preg_replace('/[^a-zA-Z0-9_]/', '', str_replace(' ', '_', strtolower($shop_name_input)));

        if ($shop_name === '') {
            echo "<script>alert('Invalid restaurant name. Please use letters, numbers, and spaces.');</script>";
        } else {
            // Check if shop already exists
            $stmt = $conn->prepare("SELECT shop_id FROM shop WHERE shop_name = ? LIMIT 1");
            $stmt->bind_param("s", $shop_name);
            $stmt->execute();
            $res = $stmt->get_result();
            if ($res && $res->num_rows > 0) {
                echo "<script>alert('A restaurant with this name already exists.');</script>";
                $stmt->close();
            } else {
                $stmt->close();
                // Start transaction to insert shop record and create table
                $conn->begin_transaction();
                try {
                    // Insert into shop table
                    $stmt = $conn->prepare("INSERT INTO shop (shop_name, password) VALUES (?, ?)");
                    $stmt->bind_param("ss", $shop_name, $password);
                    if (!$stmt->execute()) {
                        throw new Exception("Failed to insert shop: " . $stmt->error);
                    }
                    $stmt->close();

                    // Create dynamic product table for the shop
                    $createTableSql = "CREATE TABLE `$shop_name` (
                        `{$shop_name}_id` INT AUTO_INCREMENT PRIMARY KEY,
                        `product_code` VARCHAR(50) NOT NULL UNIQUE,
                        `name` VARCHAR(100) NOT NULL,
                        `description` TEXT DEFAULT NULL,
                        `price` DECIMAL(10,2) NOT NULL,
                        `category` ENUM('mains','drinks','sides') NOT NULL,
                        `image` VARCHAR(255) DEFAULT NULL,
                        `stock` INT NOT NULL DEFAULT 0,
                        `is_available` TINYINT(1) NOT NULL DEFAULT 1,
                        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                        `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;";

                    if (!$conn->query($createTableSql)) {
                        throw new Exception("Failed to create product table: " . $conn->error);
                    }

                    // Dynamically create resources directory
                    $uploadDir = __DIR__ . DIRECTORY_SEPARATOR . 'resources' . DIRECTORY_SEPARATOR . ucfirst($shop_name);
                    if (!is_dir($uploadDir)) {
                        mkdir($uploadDir, 0755, true);
                    }

                    $conn->commit();
                    echo "<script>alert('Restaurant registered successfully! You can now log in.'); window.location.href='login.php';</script>";
                } catch (Exception $e) {
                    $conn->rollback();
                    echo "<script>alert('Registration failed: " . addslashes($e->getMessage()) . "');</script>";
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Register Restaurant - Campus Cravings</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="min-h-screen bg-cover bg-center bg-no-repeat" style="background-image: url('resources/Log_IN.jpg');">
    <div class="flex items-center justify-center min-h-screen bg-black bg-opacity-60 px-4">
        <div class="bg-black bg-opacity-90 p-8 md:p-12 w-full max-w-md rounded-xl shadow-2xl border border-gray-800">
            <div class="flex flex-col items-center mb-8">
                <h2 class="text-4xl font-extrabold text-red-600 text-center">New Restaurant</h2>
                <p class="text-gray-400 text-sm mt-2 text-center">Start your business on Campus Cravings</p>
            </div>
            
            <form method="POST" action="" class="space-y-6">
                <div>
                    <label class="block text-white text-sm font-medium mb-2">Restaurant Name</label>
                    <input 
                        type="text" 
                        name="shop_name" 
                        placeholder="e.g. Burger Town" 
                        class="w-full px-4 py-3 bg-gray-800 text-white border border-gray-700 focus:outline-none focus:border-red-500 rounded" 
                        required 
                    />
                    <p class="text-xs text-gray-500 mt-1">This will be sanitized to letters, numbers, and underscores.</p>
                </div>
                
                <div>
                    <label class="block text-white text-sm font-medium mb-2">Password</label>
                    <input 
                        type="password" 
                        name="password" 
                        placeholder="Enter owner password" 
                        class="w-full px-4 py-3 bg-gray-800 text-white border border-gray-700 focus:outline-none focus:border-red-500 rounded" 
                        required 
                    />
                </div>
                
                <button type="submit" class="w-full py-3.5 bg-red-600 text-white font-bold hover:bg-red-700 transition duration-200 text-lg rounded">
                    Register Restaurant
                </button>
                
                <div class="text-center pt-2">
                    <p class="text-gray-400 text-sm">
                        Go back to 
                        <a href="login.php" class="text-red-500 hover:text-red-400 font-semibold">Log In</a>
                    </p>
                </div>
            </form>
        </div>
    </div>
</body>
</html>
