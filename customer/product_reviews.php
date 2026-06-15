<?php
session_start();
require_once __DIR__ . '/../config/db_connection.php';
require_once __DIR__ . '/../config/hd_helper.php';

$product_code = $_GET['product'] ?? '';
$kitchen = $_GET['kitchen'] ?? '';

if (!$product_code || !$kitchen) {
    header("Location: navbar.php");
    exit();
}

// Get product details
$productDetails = null;
$table = $kitchen;
$stmt = $conn->prepare("SELECT * FROM $table WHERE product_code = ?");
$stmt->bind_param("s", $product_code);
$stmt->execute();
$productDetails = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$productDetails) {
    header("Location: navbar.php");
    exit();
}

// Get review statistics
$stmt = $conn->prepare("
    SELECT 
        COUNT(*) as total_reviews,
        COALESCE(AVG(rating), 0) as avg_rating,
        SUM(CASE WHEN rating = 5 THEN 1 ELSE 0 END) as five_star,
        SUM(CASE WHEN rating = 4 THEN 1 ELSE 0 END) as four_star,
        SUM(CASE WHEN rating = 3 THEN 1 ELSE 0 END) as three_star,
        SUM(CASE WHEN rating = 2 THEN 1 ELSE 0 END) as two_star,
        SUM(CASE WHEN rating = 1 THEN 1 ELSE 0 END) as one_star
    FROM product_reviews 
    WHERE product_code = ? AND kitchen = ?
");
$stmt->bind_param("ss", $product_code, $kitchen);
$stmt->execute();
$stats = $stmt->get_result()->fetch_assoc();
$stmt->close();

// Get all reviews
$reviews = [];
$stmt = $conn->prepare("
    SELECT 
        pr.*,
        c.username,
        c.fullname,
        DATE_FORMAT(pr.created_at, '%M %d, %Y') as review_date
    FROM product_reviews pr
    JOIN customer c ON pr.customer_id = c.customer_id
    WHERE pr.product_code = ? AND pr.kitchen = ?
    ORDER BY pr.created_at DESC
");
$stmt->bind_param("ss", $product_code, $kitchen);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $reviews[] = $row;
}
$stmt->close();

function initials($name) {
    $parts = preg_split('/\s+/', trim($name));
    $letters = '';
    foreach ($parts as $p) {
        if ($p !== '') $letters .= strtoupper(substr($p, 0, 1));
        if (strlen($letters) >= 2) break;
    }
    return $letters ?: 'U';
}

$displayName = $_SESSION['fullname'] ?? $_SESSION['username'] ?? 'Guest';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($productDetails['name']) ?> Reviews - Campus Cravings</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        body { font-family: 'Inter', sans-serif; background: #f5f7fa; }
        .star-rating .star { font-size: 1.25rem; color: #fbbf24; }
        .star-rating .empty { color: #d1d5db; }
        .progress-bar { height: 8px; background: #e5e7eb; border-radius: 9999px; overflow: hidden; }
        .progress-fill { height: 100%; background: #fbbf24; transition: width 0.3s; }
        .review-card { background: white; border-radius: 0.75rem; padding: 1.5rem; margin-bottom: 1rem; box-shadow: 0 1px 3px rgba(0,0,0,0.1); }
    </style>
</head>
<body>
    <?php include __DIR__ . '/navbar.php'; ?>
    
    <div class="max-w-6xl mx-auto px-4 py-8 mt-20">
        <!-- Product Header -->
        <div class="bg-white rounded-xl shadow-lg p-6 mb-8">
            <div class="flex flex-col md:flex-row gap-6">
                <div class="w-full md:w-48 h-48 bg-gray-200 rounded-lg overflow-hidden">
                    <img src="<?= getHDProductImage($productDetails['name'], $kitchen, $productDetails['image']) ?>" 
                         alt="<?= htmlspecialchars($productDetails['name']) ?>" 
                         class="w-full h-full object-cover transition-transform duration-500 hover:scale-105" />
                </div>
                <div class="flex-1">
                    <h1 class="text-3xl font-bold text-gray-900 mb-2"><?= htmlspecialchars($productDetails['name']) ?></h1>
                    <p class="text-lg text-red-600 font-semibold mb-4">Tk <?= number_format($productDetails['price'], 0) ?></p>
                    <p class="text-gray-600 mb-2">
                        <span class="font-semibold">Kitchen:</span> 
                        <?= ucfirst($kitchen) ?> Kitchen
                    </p>
                    <p class="text-gray-600">
                        <span class="font-semibold">Category:</span> 
                        <?= ucfirst($productDetails['category']) ?>
                    </p>
                </div>
            </div>
        </div>
        
        <!-- Rating Summary -->
        <div class="bg-white rounded-xl shadow-lg p-8 mb-8">
            <h2 class="text-2xl font-bold text-gray-900 mb-6">Customer Reviews</h2>
            
            <div class="grid md:grid-cols-2 gap-8">
                <!-- Overall Rating -->
                <div class="text-center">
                    <div class="text-5xl font-bold text-gray-900 mb-2">
                        <?= number_format($stats['avg_rating'], 1) ?>
                    </div>
                    <div class="star-rating flex justify-center gap-1 mb-2">
                        <?php
                        $rating = $stats['avg_rating'];
                        for ($i = 1; $i <= 5; $i++) {
                            if ($rating >= $i) {
                                echo '<span class="star">★</span>';
                            } elseif ($rating >= $i - 0.5) {
                                echo '<span class="star">⯨</span>';
                            } else {
                                echo '<span class="star empty">★</span>';
                            }
                        }
                        ?>
                    </div>
                    <p class="text-gray-600"><?= $stats['total_reviews'] ?> <?= $stats['total_reviews'] === 1 ? 'review' : 'reviews' ?></p>
                </div>
                
                <!-- Rating Breakdown -->
                <div class="space-y-2">
                    <?php
                    $starCounts = [
                        5 => $stats['five_star'],
                        4 => $stats['four_star'],
                        3 => $stats['three_star'],
                        2 => $stats['two_star'],
                        1 => $stats['one_star']
                    ];
                    foreach ($starCounts as $star => $count):
                        $percentage = $stats['total_reviews'] > 0 ? ($count / $stats['total_reviews']) * 100 : 0;
                    ?>
                        <div class="flex items-center gap-2">
                            <span class="w-12 text-sm font-semibold text-gray-700"><?= $star ?> star</span>
                            <div class="progress-bar flex-1">
                                <div class="progress-fill" style="width: <?= $percentage ?>%"></div>
                            </div>
                            <span class="w-12 text-right text-sm text-gray-600"><?= $count ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        
        <!-- Reviews List -->
        <div class="space-y-4">
            <h3 class="text-xl font-bold text-gray-900 mb-4">All Reviews (<?= count($reviews) ?>)</h3>
            
            <?php if (empty($reviews)): ?>
                <div class="bg-white rounded-xl shadow p-8 text-center">
                    <p class="text-gray-600 text-lg">No reviews yet. Be the first to review this product!</p>
                </div>
            <?php else: ?>
                <?php foreach ($reviews as $review): ?>
                    <div class="review-card">
                        <div class="flex items-start gap-4">
                            <div class="w-12 h-12 rounded-full bg-red-600 flex items-center justify-center text-white font-bold flex-shrink-0">
                                <?= initials($review['fullname']) ?>
                            </div>
                            <div class="flex-1">
                                <div class="flex items-center justify-between mb-2">
                                    <div>
                                        <h4 class="font-semibold text-gray-900"><?= htmlspecialchars($review['fullname']) ?></h4>
                                        <?php if ($review['is_verified_purchase']): ?>
                                            <span class="text-xs text-green-600 font-semibold">
                                                <i class="fas fa-check-circle"></i> Verified Purchase
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                    <span class="text-sm text-gray-500"><?= $review['review_date'] ?></span>
                                </div>
                                <div class="star-rating flex gap-0.5 mb-2">
                                    <?php for ($i = 1; $i <= 5; $i++): ?>
                                        <span class="star <?= $i <= $review['rating'] ? '' : 'empty' ?>">★</span>
                                    <?php endfor; ?>
                                </div>
                                <?php if ($review['review_text']): ?>
                                    <p class="text-gray-700 leading-relaxed"><?= nl2br(htmlspecialchars($review['review_text'])) ?></p>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
        
        <div class="mt-8 text-center">
            <a href="<?= strtolower($kitchen) ?>.php" class="inline-block bg-red-600 hover:bg-red-700 text-white font-semibold py-3 px-8 rounded-lg transition">
                Back to <?= ucfirst($kitchen) ?> Kitchen
            </a>
        </div>
    </div>
</body>
</html>
