<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
session_start();
require_once __DIR__ . '/../../config/db_connection.php';

// Session guard
if (!isset($_SESSION['usertype']) || $_SESSION['usertype'] !== 'admin' || ($_SESSION['shop'] ?? '') !== 'Neptune Kitchen') {
    header('Location: ../../login.php');
    exit;
}

// Check for session messages (from redirects)
if (isset($_SESSION['message'])) {
    $message = $_SESSION['message'];
    $messageType = $_SESSION['messageType'] ?? 'info';
    unset($_SESSION['message'], $_SESSION['messageType']);
} else {
    $message = '';
    $messageType = 'info';
}

// Profile display data (match navbar look)
$displayName = $_SESSION['username'] ?? 'Admin';
$username = $_SESSION['username'] ?? null;
$profileImage = $_SESSION['profile_image'] ?? null;
if (!$profileImage && $username) {
    $picDir = __DIR__ . '/../../resources/ProfilePics';
    if (is_dir($picDir)) {
        foreach (['jpg', 'jpeg', 'png', 'webp'] as $ext) {
            $candidateFs = $picDir . '/' . $username . '.' . $ext;
            if (file_exists($candidateFs)) {
                $profileImage = '../../resources/ProfilePics/' . $username . '.' . $ext;
                break;
            }
        }
    }
}

function initials($name) {
    $parts = preg_split('/\s+/', trim($name));
    $letters = '';
    foreach ($parts as $p) {
        if ($p !== '') {
            $letters .= strtoupper(substr($p, 0, 1));
        }
        if (strlen($letters) >= 2) {
            break;
        }
    }
    return $letters ?: 'A';
}
$initials = initials($displayName);

// Helper function to handle image upload
function handleImageUpload() {
    if (!isset($_FILES['image_file']) || $_FILES['image_file']['error'] === UPLOAD_ERR_NO_FILE) {
        return trim($_POST['image'] ?? ''); // Return manual filename if no file uploaded
    }
    
    $file = $_FILES['image_file'];
    $uploadDir = __DIR__ . '/../../resources/Neptune/';
    
    // Create directory if it doesn't exist
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }
    
    // Validate file
    if ($file['error'] !== UPLOAD_ERR_OK) {
        return '';
    }
    
    $allowedTypes = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
    $fileType = mime_content_type($file['tmp_name']);
    
    if (!in_array($fileType, $allowedTypes)) {
        return '';
    }
    
    // Limit file size to 5MB
    if ($file['size'] > 5 * 1024 * 1024) {
        return '';
    }
    
    // Generate unique filename
    $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
    $filename = uniqid('product_') . '.' . strtolower($ext);
    $filepath = $uploadDir . $filename;
    
    if (move_uploaded_file($file['tmp_name'], $filepath)) {
        return $filename;
    }
    
    return '';
}

// Handle POST actions (ADD, UPDATE, DELETE)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'add') {
        $name = trim($_POST['name'] ?? '');
        $price = floatval($_POST['price'] ?? 0);
        $category = trim($_POST['category'] ?? 'mains');
        $image = handleImageUpload();
        $stock = intval($_POST['stock'] ?? 0);
        
        // Auto-generate product_code from name and timestamp
        $product_code = strtoupper(substr(preg_replace('/[^a-zA-Z0-9]/', '', $name), 0, 5)) . '_' . time();
        
        if ($name && $price > 0) {
            $stmt = $conn->prepare("INSERT INTO neptune (product_code, name, price, category, image, stock, is_available) VALUES (?, ?, ?, ?, ?, ?, 1)");
            if ($stmt) {
                $stmt->bind_param("ssdssi", $product_code, $name, $price, $category, $image, $stock);
                if ($stmt->execute()) {
                    $_SESSION['message'] = "✓ Product added successfully!";
                    $_SESSION['messageType'] = 'success';
                    header('Location: ' . $_SERVER['PHP_SELF']);
                    exit;
                } else {
                    $message = "✗ Error adding product: " . $stmt->error;
                    $messageType = 'error';
                }
                $stmt->close();
            } else {
                $message = "✗ Database error: " . $conn->error;
                $messageType = 'error';
            }
        } else {
            $message = "✗ Please fill in all required fields.";
            $messageType = 'error';
        }
    } elseif ($action === 'update') {
        $neptune_id = intval($_POST['neptune_id'] ?? 0);
        $name = trim($_POST['name'] ?? '');
        $price = floatval($_POST['price'] ?? 0);
        $category = trim($_POST['category'] ?? 'mains');
        // If new file uploaded, use it; otherwise keep existing image
        $image = handleImageUpload();
        if (empty($image)) {
            $image = trim($_POST['image'] ?? '');
        }
        $stock = intval($_POST['stock'] ?? 0);
        
        if ($neptune_id > 0 && $name && $price > 0) {
            $stmt = $conn->prepare("UPDATE neptune SET name=?, price=?, category=?, image=?, stock=? WHERE neptune_id=?");
            if ($stmt) {
                $stmt->bind_param("sdssii", $name, $price, $category, $image, $stock, $neptune_id);
                if ($stmt->execute()) {
                    $_SESSION['message'] = "✓ Product updated successfully!";
                    $_SESSION['messageType'] = 'success';
                    header('Location: ' . $_SERVER['PHP_SELF']);
                    exit;
                } else {
                    $message = "✗ Error updating product: " . $stmt->error;
                    $messageType = 'error';
                }
                $stmt->close();
            } else {
                $message = "✗ Database error: " . $conn->error;
                $messageType = 'error';
            }
        } else {
            $message = "✗ Invalid product data.";
            $messageType = 'error';
        }
    } elseif ($action === 'delete') {
        $neptune_id = intval($_POST['neptune_id'] ?? 0);
        
        if ($neptune_id > 0) {
            $stmt = $conn->prepare("DELETE FROM neptune WHERE neptune_id=?");
            if ($stmt) {
                $stmt->bind_param("i", $neptune_id);
                if ($stmt->execute()) {
                    $_SESSION['message'] = "✓ Product deleted successfully!";
                    $_SESSION['messageType'] = 'success';
                    header('Location: ' . $_SERVER['PHP_SELF']);
                    exit;
                } else {
                    $message = "✗ Error deleting product: " . $stmt->error;
                    $messageType = 'error';
                }
                $stmt->close();
            } else {
                $message = "✗ Database error: " . $conn->error;
                $messageType = 'error';
            }
        }
    } elseif ($action === 'toggle_availability') {
        $neptune_id = intval($_POST['neptune_id'] ?? 0);
        $is_available = intval($_POST['is_available'] ?? 0);
        
        if ($neptune_id > 0) {
            $stmt = $conn->prepare("UPDATE neptune SET is_available=? WHERE neptune_id=?");
            if ($stmt) {
                $stmt->bind_param("ii", $is_available, $neptune_id);
                if ($stmt->execute()) {
                    $message = "✓ Availability updated!";
                    $messageType = 'success';
                } else {
                    $message = "✗ Error updating availability: " . $stmt->error;
                    $messageType = 'error';
                }
                $stmt->close();
            }
        }
    }
}

// Fetch all products from neptune table
$products = [];
$sql = "SELECT neptune_id, product_code, name, price, category, image, stock, is_available FROM neptune ORDER BY category, name";
$result = $conn->query($sql);
if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $products[] = $row;
    }
}

?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width,initial-scale=1" />
    <title>Neptune Kitchen - Product Management</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
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
        .food-wave { 
            font-weight: 900; 
            letter-spacing: 3px; 
            font-size: 2.4rem; 
            background: linear-gradient(90deg, #ff0000); 
            -webkit-background-clip: text; 
            background-clip: text; 
            -webkit-text-fill-color: transparent; 
            display: inline-block; 
        }
        .initials-circle { 
            width: 42px; 
            height: 42px; 
            border-radius:9999px; 
            background: linear-gradient(135deg,#ef4444,#7f1d1d); 
            display:inline-flex; 
            align-items:center; 
            justify-content:center; 
            color:#fff; 
            font-weight:700; 
        }
        .avatar-img { 
            width: 42px; 
            height: 42px; 
            border-radius: 9999px; 
            object-fit: cover; 
        }
        .profile-menu.show { 
            display:block; 
        }
        
        .hero {
            background-image: url('../../resources/sign_up.jpg');
            background-size: cover;
            background-position: center;
            background-attachment: fixed;
            min-height: 60vh;
            position: relative;
            overflow: hidden;
            margin-top: 4rem;
        }
        .hero::before {
            content: '';
            position: absolute;
            top: 0; 
            left: 0; 
            width: 100%; 
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            z-index: 1;
        }
        .hero-overlay { 
            position: absolute; 
            inset: 0; 
            background: transparent; 
            z-index: 1; 
        }

        .message-box { 
            padding: 1rem; 
            border-radius: 0.5rem; 
            margin-bottom: 1.5rem; 
        }
        .success { 
            background: #d1fae5; 
            color: #065f46; 
            border: 1px solid #a7f3d0; 
        }
        .error { 
            background: #fee2e2; 
            color: #7f1d1d; 
            border: 1px solid #fca5a5; 
        }
        .info { 
            background: #dbeafe; 
            color: #1e3a8a; 
            border: 1px solid #93c5fd; 
        }
        table { 
            width: 100%; 
        }
        table.product-table { 
            border-collapse: separate; 
            border-spacing: 0 12px; 
            table-layout: fixed; 
        }
        table.product-table th, table.product-table td { 
            padding: 0.75rem; 
            text-align: left; 
            overflow: hidden; 
            text-overflow: ellipsis; 
            white-space: nowrap; 
        }
        table.product-table th { 
            background: #f3f4f6; 
            font-weight: 600; 
        }
        table.product-table td { 
            background: #fff; 
            border: none; 
        }
        table.product-table th:nth-child(1), table.product-table td:nth-child(1) { 
            width: 20%; 
        }
        table.product-table th:nth-child(2), table.product-table td:nth-child(2) { 
            width: 18%; 
        }
        table.product-table th:nth-child(3), table.product-table td:nth-child(3) { 
            width: 18%; 
        }
        table.product-table th:nth-child(4), table.product-table td:nth-child(4) { 
            width: 14%; 
        }
        table.product-table th:nth-child(5), table.product-table td:nth-child(5) { 
            width: 12%; 
        }
        table.product-table th:nth-child(6), table.product-table td:nth-child(6) { 
            width: 18%; 
        }
        table.product-table tbody tr { 
            box-shadow: 0 8px 24px rgba(0,0,0,0.08); 
            border-radius: 12px; 
            overflow: hidden; 
            transition: transform 0.15s ease, box-shadow 0.15s ease; 
        }
        table.product-table tbody tr:hover { 
            transform: translateY(-2px); 
            box-shadow: 0 12px 32px rgba(0,0,0,0.12); 
        }
        table.product-table tbody tr td:first-child { 
            border-top-left-radius: 12px; 
            border-bottom-left-radius: 12px; 
        }
        table.product-table tbody tr td:last-child { 
            border-top-right-radius: 12px; 
            border-bottom-right-radius: 12px; 
        }
        .btn { 
            padding: 0.5rem 1rem; 
            border-radius: 0.375rem; 
            font-weight: 600; 
            cursor: pointer; 
        }
        .btn-primary { 
            background: #ef4444; 
            color: white; 
        }
        .btn-primary:hover { 
            background: #dc2626; 
        }
        .btn-secondary { 
            background: #6b7280; 
            color: white; 
        }
        .btn-secondary:hover { 
            background: #4b5563; 
        }
        .form-group { 
            margin-bottom: 1rem; 
        }
        label { 
            display: block; 
            margin-bottom: 0.25rem; 
            font-weight: 600; 
        }
        input, select { 
            width: 100%; 
            padding: 0.5rem; 
            border: 1px solid #d1d5db; 
            border-radius: 0.375rem; 
        }
        input:focus, select:focus { 
            outline: none; 
            border-color: #ef4444; 
        }
        .form-row { 
            display: grid; 
            grid-template-columns: 1fr 1fr; 
            gap: 1rem; 
        }
        .form-row-3col { 
            display: grid; 
            grid-template-columns: 1fr 1fr 1fr; 
            gap: 1rem; 
        }
        @media (max-width: 768px) { 
            .form-row-3col { 
                grid-template-columns: 1fr; 
            } 
        }
        @media (max-width: 640px) { 
            .form-row { 
                grid-template-columns: 1fr; 
            } 
        }
        input, select { 
            box-shadow: 0 4px 15px rgba(0,0,0,0.1); 
            transition: all 0.3s ease; 
        }
        input:focus, select:focus { 
            box-shadow: 0 8px 25px rgba(239,68,68,0.3); 
            transform: translateY(-2px); 
        }
        input { 
            background: linear-gradient(135deg, #f9fafb 0%, #ffffff 100%); 
        }
        select { 
            background: linear-gradient(135deg, #f0fdf4 0%, #ffffff 100%); 
        }
        .category-filter { 
            display: flex; 
            gap: 0.5rem; 
            margin-bottom: 2rem; 
            flex-wrap: wrap; 
        }
        .category-btn { 
            padding: 0.6rem 1.2rem; 
            border-radius: 8px; 
            font-weight: 600; 
            cursor: pointer; 
            border: 2px solid #e5e7eb; 
            background: white; 
            transition: all 0.3s ease; 
            box-shadow: 0 2px 8px rgba(0,0,0,0.05); 
        }
        .category-btn:hover { 
            transform: translateY(-2px); 
            box-shadow: 0 4px 12px rgba(0,0,0,0.1); 
        }
        .category-btn.active { 
            background: #ef4444; 
            color: white; 
            border-color: #ef4444; 
            box-shadow: 0 6px 20px rgba(239,68,68,0.4); 
        }
        .form-title { 
            text-align: center; 
            font-size: 2rem; 
            font-weight: 800; 
            background: linear-gradient(120deg, #ef4444, #f97316, #fbbf24); 
            -webkit-background-clip: text; 
            background-clip: text; 
            color: transparent; 
            text-shadow: 0 3px 0 rgba(0,0,0,0.15), 0 6px 12px rgba(0,0,0,0.2); 
            letter-spacing: 1px; 
        }
        .profile-menu { 
            position: absolute; 
            right: 0; 
            margin-top: 0.5rem; 
            width: 11rem; 
            background: #000; 
            background-color: rgba(0,0,0,0.95); 
            border: 1px solid #374151; 
            border-radius: 0.5rem; 
            box-shadow: 0 10px 25px -5px rgba(0,0,0,0.2); 
        }
        .profile-menu a { 
            display: block; 
            padding: 0.5rem 1rem; 
            color: #fff; 
            border-radius: 0.5rem; 
        }
        .profile-menu a:hover { 
            background: #4b5563; 
        }
        .profile-menu a.logout { 
            color: #ef4444; 
        }
        .modal { 
            display: none; 
            position: fixed; 
            inset: 0; 
            background: rgba(0, 0, 0, 0.5); 
            z-index: 100; 
            align-items: center; 
            justify-content: center; 
        }
        .modal.show { 
            display: flex; 
        }
        .modal-content { 
            background: white; 
            border-radius: 12px; 
            padding: 2rem; 
            max-width: 500px; 
            width: 90%; 
            box-shadow: 0 20px 60px rgba(0,0,0,0.3); 
            animation: slideUp 0.3s ease; 
        }
        @keyframes slideUp { 
            from { 
                transform: translateY(20px); 
                opacity: 0; 
            } 
            to { 
                transform: translateY(0); 
                opacity: 1; 
            } 
        }
        .modal-header { 
            font-size: 1.5rem; 
            font-weight: 700; 
            margin-bottom: 1.5rem; 
            color: #1f2937; 
        }
        .modal-footer { 
            display: flex; 
            gap: 1rem; 
            margin-top: 2rem; 
            justify-content: flex-end; 
        }
        .modal-footer button { 
            padding: 0.75rem 1.5rem; 
            border-radius: 0.5rem; 
            border: none; 
            font-weight: 600; 
            cursor: pointer; 
        }
        .btn-cancel { 
            background: #e5e7eb; 
            color: #374151; 
        }
        .btn-cancel:hover { 
            background: #d1d5db; 
        }
        .btn-save { 
            background: #ef4444; 
            color: white; 
        }
        .btn-save:hover { 
            background: #dc2626; 
        }
    </style>
</head>
<body class="bg-gray-50 text-gray-900">
    <div id="toast" class="toast"></div>
    
    <!-- Edit Product Modal -->
    <div id="editModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">Edit Product</div>
            <form id="editForm" method="POST" enctype="multipart/form-data" class="space-y-4">
                <input type="hidden" name="action" value="update">
                <input type="hidden" name="neptune_id" id="editProductId">
                <input type="hidden" name="image" id="editImage" value="">

                <div class="form-group">
                    <label for="editName">Product Name</label>
                    <input type="text" id="editName" name="name" required>
                </div>

                <div class="form-group">
                    <label for="editPrice">Price (Tk)</label>
                    <input type="number" id="editPrice" name="price" step="0.01" min="0.01" required>
                </div>

                <div class="form-group">
                    <label for="editCategory">Category</label>
                    <select id="editCategory" name="category" required>
                        <option value="mains">Main Food</option>
                        <option value="drinks">Drinks</option>
                        <option value="sides">Snacks & Sides</option>
                    </select>
                </div>

                <div class="form-group">
                    <label for="editStock">Stock Quantity</label>
                    <input type="number" id="editStock" name="stock" min="0" required>
                </div>

                <div class="form-group">
                    <label for="editImageFile">Product Image</label>
                    <input type="file" id="editImageFile" name="image_file" accept="image/*">
                    <small class="text-gray-500">Leave empty to keep current image</small>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn-cancel" onclick="closeEditModal()">Cancel</button>
                    <button type="submit" class="btn-save">Save Changes</button>
                </div>
            </form>
        </div>
    </div>
    
    <header class="h-16 flex items-center">
        <div class="relative h-full w-full max-w-7xl mx-auto px-4">
            <div class="logo-section">
                <h2 class="food-wave">Food Wave</h2>
            </div>

            <nav class="nav-buttons">
                <a href="./navbar.php" class="px-4 py-2 bg-transparent hover:bg-red-600 hover:bg-opacity-80 rounded text-sm">Home</a>
                <a href="./statistics.php" class="px-4 py-2 rounded text-sm hover:bg-red-600 hover:bg-opacity-80">Statistics</a>
                <a href="./product.php" class="px-4 py-2 rounded text-sm bg-red-600 bg-opacity-80">Product</a>
                <a href="./invoice.php" class="px-4 py-2 rounded text-sm hover:bg-red-600 hover:bg-opacity-80">Invoice</a>
                <a href="./orders.php" class="px-4 py-2 rounded text-sm hover:bg-red-600 hover:bg-opacity-80">Order</a>
            </nav>

            <div class="profile-section">
                <div class="relative" id="profileRoot">
                    <button id="profileBtn" class="flex items-center gap-2 p-1 rounded focus:outline-none">
                        <?php if (!empty($profileImage)): ?>
                            <img src="<?= htmlspecialchars($profileImage) ?>" alt="Profile" class="avatar-img" />
                        <?php else: ?>
                            <div class="initials-circle"><?= htmlspecialchars($initials) ?></div>
                        <?php endif; ?>
                        <span class="hidden sm:inline-block text-sm text-white"><?= htmlspecialchars($displayName) ?></span>
                    </button>
                    <div id="profileMenu" class="profile-menu hidden">
                        <a href="./profile.php">Profile</a>
                        <a href="../../customer/logout.php" class="logout">Log out</a>
                    </div>
                </div>
            </div>
        </div>
    </header>

    <section id="hero" class="hero">
        <div class="hero-overlay"></div>
        <div class="relative z-10 max-w-4xl mx-auto px-4 py-24 text-center text-white">
            <h1 class="text-4xl sm:text-5xl font-extrabold mb-4">Neptune Kitchen</h1>
            <p class="text-lg text-gray-200">Manage your products and inventory</p>
        </div>
    </section>

    <main class="max-w-7xl mx-auto px-4 py-10">
        <?php if ($message): ?>
            <div class="message-box <?= $messageType ?>">
                <?= htmlspecialchars($message) ?>
            </div>
        <?php endif; ?>

        <!-- Add Product Form -->
        <div class="bg-white rounded-lg shadow p-6 mb-8">
            <h2 class="form-title mb-6">Add New Product</h2>
            <form method="POST" enctype="multipart/form-data" class="space-y-4">
                <input type="hidden" name="action" value="add">

                <div class="form-row">
                    <div class="form-group">
                        <label for="name">Product Name</label>
                        <input type="text" id="name" name="name" required>
                    </div>
                    <div class="form-group">
                        <label for="image_file">Product Image</label>
                        <input type="file" id="image_file" name="image_file" accept="image/*">
                    </div>
                </div>

                <div class="form-row-3col">
                    <div class="form-group">
                        <label for="price">Price (Tk)</label>
                        <input type="number" id="price" name="price" step="0.01" min="0.01" required>
                    </div>
                    <div class="form-group">
                        <label for="category">Category</label>
                        <select id="category" name="category">
                            <option value="mains">Main Food</option>
                            <option value="drinks">Drinks</option>
                            <option value="sides">Snacks & Sides</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="stock">Stock Quantity</label>
                        <input type="number" id="stock" name="stock" min="0" required>
                    </div>
                </div>

                <button type="submit" class="btn btn-primary w-full">Add Product</button>
            </form>
        </div>

        <!-- Category Filter -->
        <div class="category-filter" id="categoryFilter">
            <button type="button" class="category-btn active" data-filter="all">All Products</button>
            <button type="button" class="category-btn" data-filter="mains">Main Food</button>
            <button type="button" class="category-btn" data-filter="drinks">Drinks</button>
            <button type="button" class="category-btn" data-filter="sides">Snacks & Sides</button>
        </div>

        <!-- Products Table -->
        <div class="bg-white rounded-lg shadow overflow-hidden">
            <h2 class="text-2xl font-bold p-6 border-b">Current Products (<span id="productCount"><?= count($products) ?></span>)</h2>
            
            <?php if (count($products) > 0): ?>
                <div class="overflow-x-auto">
                    <table class="product-table">
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Image</th>
                                <th>Category</th>
                                <th>Price</th>
                                <th>Stock</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody id="productTableBody">
                            <?php foreach ($products as $p): ?>
                                <tr data-category="<?= htmlspecialchars($p['category']) ?>">
                                    <td><?= htmlspecialchars($p['name']) ?></td>
                                    <td>
                                        <?php if (trim($p['image']) !== ''): ?>
                                            <img src="<?= '../../resources/Neptune/' . rawurlencode($p['image']) ?>" alt="<?= htmlspecialchars($p['name']) ?>" style="width:80px;height:60px;object-fit:cover;border-radius:8px;" />
                                        <?php else: ?>
                                            <span class="text-gray-500">No image</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?= htmlspecialchars($p['category']) ?></td>
                                    <td>Tk <?= number_format((float)$p['price'], 0) ?></td>
                                    <td><?= (int)$p['stock'] ?></td>
                                    <td>
                                        <button type="button" class="btn btn-secondary" style="padding:0.5rem 0.75rem; font-size:0.875rem;" onclick="editProduct(<?= (int)$p['neptune_id'] ?>, '<?= htmlspecialchars($p['name'], ENT_QUOTES) ?>', <?= (float)$p['price'] ?>, '<?= htmlspecialchars($p['category'], ENT_QUOTES) ?>', '<?= htmlspecialchars($p['image'], ENT_QUOTES) ?>', <?= (int)$p['stock'] ?>)">Edit</button>
                                        <form method="POST" style="display:inline;" onsubmit="return confirm('Delete this product?');">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="neptune_id" value="<?= (int)$p['neptune_id'] ?>">
                                            <button type="submit" class="btn btn-primary" style="padding:0.5rem 0.75rem; font-size:0.875rem;">Delete</button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    <p id="emptyMessage" class="p-6 text-gray-500 text-center" style="display:none;">No products in this category.</p>
                </div>
            <?php else: ?>
                <p class="p-6 text-gray-500 text-center" id="emptyMessage">No products yet. Add one above!</p>
            <?php endif; ?>
        </div>
    </main>

    <script>
        // Client-side category filter (no page reload)
        (function() {
            const buttons = Array.from(document.querySelectorAll('#categoryFilter .category-btn'));
            const rows = Array.from(document.querySelectorAll('#productTableBody tr'));
            const countEl = document.getElementById('productCount');
            const emptyEl = document.getElementById('emptyMessage');

            function applyFilter(filter) {
                let visible = 0;
                rows.forEach(row => {
                    const rowCat = row.dataset.category;
                    const show = filter === 'all' || rowCat === filter;
                    row.style.display = show ? '' : 'none';
                    if (show) visible++;
                });

                if (countEl) countEl.textContent = visible;
                if (emptyEl) emptyEl.style.display = visible === 0 ? 'block' : 'none';
            }

            buttons.forEach(btn => {
                btn.addEventListener('click', () => {
                    buttons.forEach(b => b.classList.remove('active'));
                    btn.classList.add('active');
                    applyFilter(btn.dataset.filter);
                });
            });

            // Initial state: keep All active and ensure empty message hidden when products exist
            applyFilter('all');
        })();

        function editProduct(id, name, price, category, image, stock) {
            document.getElementById('editProductId').value = id;
            document.getElementById('editName').value = name;
            document.getElementById('editPrice').value = price;
            document.getElementById('editCategory').value = category;
            document.getElementById('editStock').value = stock;
            document.getElementById('editImage').value = image;
            document.getElementById('editModal').classList.add('show');
        }

        function closeEditModal() {
            document.getElementById('editModal').classList.remove('show');
        }

        document.getElementById('editForm').addEventListener('submit', function(e) {
            e.preventDefault();
            this.submit();
        });

        document.getElementById('editModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeEditModal();
            }
        });

        // Allow ESC key to close modal
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closeEditModal();
            }
        });

        // Profile dropdown
        const profileBtn = document.getElementById('profileBtn');
        const profileMenu = document.getElementById('profileMenu');
        profileBtn.addEventListener('click', function(e) {
            e.stopPropagation();
            profileMenu.classList.toggle('show');
        });
        document.addEventListener('click', function() {
            profileMenu.classList.remove('show');
        });
    </script>
</body>
</html>
