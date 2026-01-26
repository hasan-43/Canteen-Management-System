<?php
session_start();
require_once __DIR__ . '/../config/db_connection.php';

if (!isset($_SESSION['usertype']) || $_SESSION['usertype'] !== 'delivery') {
    header('Location: login.php');
    exit;
}

$delivery_id = intval($_SESSION['delivery_id']);
$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_profile'])) {
    $fullname = trim($_POST['fullname'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $pwd = $_POST['password'] ?? '';

    if ($fullname !== '') {
        if ($pwd !== '') {
            $hash = password_hash($pwd, PASSWORD_DEFAULT);
            $stmt = $conn->prepare('UPDATE delivery_man SET fullname=?, phone=?, email=?, password_hash=? WHERE id=?');
            $stmt->bind_param('ssssi', $fullname, $phone, $email, $hash, $delivery_id);
        } else {
            $stmt = $conn->prepare('UPDATE delivery_man SET fullname=?, phone=?, email=? WHERE id=?');
            $stmt->bind_param('sssi', $fullname, $phone, $email, $delivery_id);
        }
        $stmt->execute();
        $stmt->close();
        $message = 'Profile updated.';
        $_SESSION['delivery_name'] = $fullname;
    }
}

$stmt = $conn->prepare('SELECT * FROM delivery_man WHERE id = ? LIMIT 1');
$stmt->bind_param('i', $delivery_id);
$stmt->execute();
$res = $stmt->get_result();
$profile = $res ? $res->fetch_assoc() : null;
$stmt->close();

?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width,initial-scale=1" />
    <title>Profile</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100">
    <?php include __DIR__ . '/navbar.php'; ?>
    <main class="max-w-md mx-auto mt-8 bg-white p-6 rounded shadow">
        <h1 class="text-2xl font-bold mb-4">Profile</h1>
        <?php if ($message): ?><div class="mb-4 text-green-700"><?= htmlspecialchars($message) ?></div><?php endif; ?>
        <form method="post">
            <label class="block mb-2">Full name</label>
            <input name="fullname" value="<?= htmlspecialchars($profile['fullname'] ?? '') ?>" class="w-full p-2 border rounded mb-3" />
            <label class="block mb-2">Phone</label>
            <input name="phone" value="<?= htmlspecialchars($profile['phone'] ?? '') ?>" class="w-full p-2 border rounded mb-3" />
            <label class="block mb-2">Email</label>
            <input name="email" value="<?= htmlspecialchars($profile['email'] ?? '') ?>" class="w-full p-2 border rounded mb-3" />
            <label class="block mb-2">New password (leave blank to keep)</label>
            <input name="password" type="password" class="w-full p-2 border rounded mb-4" />
            <button name="save_profile" class="w-full bg-red-600 text-white p-2 rounded">Save</button>
        </form>
    </main>
</body>
</html>
