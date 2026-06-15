<?php
// customer/chat.php
session_start();
require_once '../config/db_connection.php';

if (!isset($_SESSION['usertype']) || $_SESSION['usertype'] !== 'customer') {
    header('Location: ../login.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$username = $_SESSION['username'];

$displayName = $_SESSION['fullname'] ?? $_SESSION['username'] ?? 'Customer';
$profilePic = $_SESSION['profile_pic'] ?? null;

if (!empty($_SESSION['user_id'])) {
    $userId = (int) $_SESSION['user_id'];
    $sql = "SELECT fullname, email";
    $hasProfilePic = false;
    $res = $conn->query("SELECT COUNT(*) AS cnt FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'customer' AND COLUMN_NAME = 'profile_pic'");
    if ($res) {
        $row = $res->fetch_assoc();
        if ((int)$row['cnt'] > 0) {
            $hasProfilePic = true;
            $sql .= ", profile_pic";
        }
    }
    $sql .= " FROM customer WHERE customer_id = ? LIMIT 1";
    if ($stmt = $conn->prepare($sql)) {
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result && $row = $result->fetch_assoc()) {
            if (!empty($row['fullname'])) $displayName = $row['fullname'];
            if ($hasProfilePic && !empty($row['profile_pic'])) $profilePic = $row['profile_pic'];
        }
        $stmt->close();
    }
}

if (!function_exists('initials')) {
    function initials($name) {
        $parts = preg_split('/\s+/', trim($name));
        $letters = '';
        foreach ($parts as $p) {
            if ($p !== '') $letters .= mb_strtoupper(mb_substr($p, 0, 1));
            if (mb_strlen($letters) >= 2) break;
        }
        return $letters ?: 'C';
    }
}
$initials = initials($displayName);

$shops = [];
$shopResultForNav = $conn->query("SELECT shop_id, shop_name FROM shop ORDER BY shop_id");
if ($shopResultForNav) {
    while ($row = $shopResultForNav->fetch_assoc()) {
        $shops[] = [
            'id'           => $row['shop_id'],
            'name'         => $row['shop_name'],
            'display_name' => ucfirst($row['shop_name']) . ' Kitchen',
        ];
    }
}

$kitchens = [];
$shopResult = $conn->query("SELECT shop_name FROM shop ORDER BY shop_id");
if ($shopResult) {
    while ($row = $shopResult->fetch_assoc()) {
        $name = $row['shop_name'];
        if ($name === 'khans') {
            $desc = 'Traditional & Modern Cuisine';
        } elseif ($name === 'olympia') {
            $desc = 'Fast Food & Beverages';
        } elseif ($name === 'neptune') {
            $desc = 'Snacks & Quick Bites';
        } else {
            $desc = 'Fresh Campus Food';
        }
        $kitchens[] = [
            'id' => $name,
            'name' => ucfirst($name) . ' Kitchen',
            'desc' => $desc
        ];
    }
}

// Fetch active riders for this customer
$riders = [];
$sql_riders = "SELECT DISTINCT d.delivery_id, d.fullname, d.username 
               FROM orders o 
               JOIN delivery d ON o.delivery_man_id = d.delivery_id 
               WHERE o.customer_id = ? AND o.order_status NOT IN ('delivered', 'cancelled')";
$stmt = $conn->prepare($sql_riders);
if ($stmt) {
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $res_riders = $stmt->get_result();
    while ($r = $res_riders->fetch_assoc()) {
        $riders[] = $r;
    }
}

$selected_kitchen = $_GET['kitchen'] ?? null;
$selected_rider = $_GET['rider'] ?? null;
$chat_name = "Select a Conversation";
$chat_icon = "💬";

if ($selected_kitchen) {
    foreach($kitchens as $k) {
        if ($k['id'] === $selected_kitchen) {
            $chat_name = $k['name'];
            if ($k['id'] == 'khans') {
                $chat_icon = '🍛';
            } elseif ($k['id'] == 'olympia') {
                $chat_icon = '🍔';
            } elseif ($k['id'] == 'neptune') {
                $chat_icon = '☕';
            } else {
                $chat_icon = '🍽️';
            }
            break;
        }
    }
} elseif ($selected_rider) {
    if ($stmt = $conn->prepare("SELECT fullname FROM delivery WHERE delivery_id = ?")) {
        $stmt->bind_param("i", $selected_rider);
        $stmt->execute();
        $r_res = $stmt->get_result()->fetch_assoc();
        if ($r_res) {
            $chat_name = "Rider: " . $r_res['fullname'];
            $chat_icon = "🚴";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <script src="../resources/js/theme.js"></script>
    <title>Chat - Campus Cravings</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;600;700&display=swap" rel="stylesheet">
    <style>
        body { 
            background-color: #F3F4F6;
            color: #1F2937; 
            font-family: 'Outfit', sans-serif;
            overflow: hidden; 
        }
        .sidebar { background: white; border-right: 1px solid #E5E7EB; }
        .chat-area { background: #FFFFFF; background-image: radial-gradient(#F3F4F6 1px, transparent 1px); background-size: 20px 20px; }
        .message-bubble { max-width: 75%; padding: 12px 18px; border-radius: 18px; position: relative; animation: fadeIn 0.3s ease-out; font-size: 0.95rem; line-height: 1.5; box-shadow: 0 1px 2px rgba(0,0,0,0.05); }
        .sent { background: #EF4444; color: white; align-self: flex-end; border-bottom-right-radius: 4px; }
        .received { background: #F3F4F6; color: #1F2937; align-self: flex-start; border-bottom-left-radius: 4px; border: 1px solid #E5E7EB; }
        .custom-scroll::-webkit-scrollbar { width: 6px; }
        .custom-scroll::-webkit-scrollbar-track { background: transparent; }
        .custom-scroll::-webkit-scrollbar-thumb { background: #D1D5DB; border-radius: 10px; }
        .kitchen-card:hover { background: #F9FAFB; }
        .kitchen-card.active { background: #FEF2F2; border-right: 3px solid #EF4444; }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }

        /* Unified fixed header layout rules */
        header {
          position: fixed; top: 0; left: 0; right: 0; z-index: 50;
          height: 4rem;
          background: rgba(10, 10, 12, 0.9) !important;
          backdrop-filter: blur(12px) !important;
          -webkit-backdrop-filter: blur(12px) !important;
          border-bottom: 1px solid rgba(255, 255, 255, 0.1) !important;
        }
        .logo-section { position: absolute; left: 2rem; top: 50%; transform: translateY(-50%); }
        .logo-section a { display: flex; align-items: center; gap: 0.75rem; text-decoration: none; }
        .brand-text { font-size: 1.25rem; font-weight: 800; color: #ffffff; letter-spacing: 0.05em; transition: color 0.3s ease; }
        .logo-section a:hover .brand-text { color: #ef4444; }
        .profile-section { position: absolute; right: 2rem; top: 50%; transform: translateY(-50%); }
        .profile-menu { display: none; z-index: 80; box-shadow: 0 12px 30px rgba(0,0,0,0.35); backdrop-filter: blur(6px); }
        .profile-menu.show { display: block; }
        .initials-circle {
          width: 40px; height: 40px; border-radius: 9999px;
          background: linear-gradient(135deg, #ef4444, #7f1d1d);
          display: inline-flex; align-items: center; justify-content: center;
          color: #fff; font-weight: 700;
        }
        .campus-cravings-logo {
          height: 50px; width: auto;
          transition: transform 0.3s ease;
        }
        .campus-cravings-logo:hover { transform: scale(1.05); }
        .nav-buttons { display: none; }
        @media (min-width: 768px) {
          .nav-buttons {
            display: flex; position: absolute; left: 50%; top: 50%;
            transform: translate(-50%, -50%); gap: 1rem;
          }
        }
    </style>
</head>
<body class="h-screen flex flex-col">

  <header>
    <div class="relative h-16 max-w-7xl mx-auto px-4">
      <!-- Logo left -->
      <div class="logo-section">
        <a href="./navbar.php">
          <img src="../resources/logo.jpg" alt="Campus Cravings" class="campus-cravings-logo" />
          <span class="brand-text">Campus Cravings</span>
        </a>
      </div>

      <!-- Nav buttons center (hidden on mobile) -->
      <nav class="nav-buttons">
        <a href="./navbar.php" class="px-4 py-2 bg-transparent hover:bg-red-600 hover:bg-opacity-80 rounded text-sm text-white font-semibold">Home</a>
        <div class="relative group">
          <button class="px-4 py-2 rounded text-sm hover:bg-red-600 hover:bg-opacity-80 text-white transition">Shop</button>
          <div class="absolute left-0 mt-2 w-48 bg-black bg-opacity-95 border border-gray-800 rounded shadow-lg opacity-0 group-hover:opacity-100 transform scale-95 group-hover:scale-100 transition-all origin-top z-50">
            <?php foreach ($shops as $shop): 
              $isLegacy = in_array($shop['name'], ['khans', 'olympia', 'neptune']);
              $shopUrl = $isLegacy ? "./" . htmlspecialchars($shop['name']) . ".php" : "./shop.php?name=" . urlencode($shop['name']);
            ?>
              <a href="<?= $shopUrl ?>" class="block px-4 py-2 hover:bg-gray-700 text-white"><?= htmlspecialchars($shop['display_name']) ?></a>
            <?php endforeach; ?>
          </div>
        </div>
        <a href="./invoice.php" class="px-4 py-2 rounded text-sm hover:bg-red-600 hover:bg-opacity-80 text-white transition">Invoice</a>
        <a href="./chat.php" class="px-4 py-2 bg-red-600 bg-opacity-80 rounded text-sm text-white font-semibold">Chat</a>
        <a href="./cart.php" class="px-4 py-2 rounded text-sm hover:bg-red-600 hover:bg-opacity-80 text-white transition">Cart</a>
      </nav>

      <!-- Profile right -->
      <div class="profile-section">
        <div class="relative" id="profileRoot">
          <button id="profileBtn" class="flex items-center gap-2 p-1 rounded profile-btn focus:outline-none">
            <?php if (!empty($profilePic)): ?>
              <img src="<?= htmlspecialchars($profilePic) ?>" alt="Profile" class="w-10 h-10 rounded-full object-cover border border-gray-600" />
            <?php else: ?>
              <div class="initials-circle"><?= htmlspecialchars($initials) ?></div>
            <?php endif; ?>
            <span class="hidden sm:inline-block text-sm text-white"><?= htmlspecialchars($displayName) ?></span>
          </button>

          <div id="profileMenu" class="profile-menu absolute right-0 mt-2 w-48 bg-gray-900 bg-opacity-95 rounded-lg border border-gray-800 text-left">
            <a href="../customer/profile.php" class="block px-4 py-2.5 text-gray-100 hover:bg-gray-800 hover:bg-opacity-70">Profile</a>
            <a href="./logout.php" class="block px-4 py-2.5 text-red-200 hover:bg-gray-800 hover:bg-opacity-70">Log out</a>
          </div>
        </div>
      </div>
    </div>
  </header>

    <div class="flex z-10 overflow-hidden" style="height: calc(100vh - 4rem); margin-top: 4rem;">
        <aside class="w-full md:w-80 sidebar flex flex-col <?= ($selected_kitchen || $selected_rider) ? 'hidden md:flex' : 'flex' ?>">
            <div class="p-5 border-b border-gray-100"><h2 class="text-lg font-bold">Conversations</h2></div>
            <div class="flex-1 overflow-y-auto custom-scroll">
                <div class="px-5 py-3 bg-gray-50 text-[10px] uppercase font-bold text-gray-400">Kitchens Support</div>
                <?php foreach ($kitchens as $k): ?>
                    <a href="?kitchen=<?= $k['id'] ?>" class="kitchen-card block p-4 border-b border-gray-50 <?= $selected_kitchen === $k['id'] ? 'active' : '' ?>">
                        <div class="flex items-center gap-3">
                            <div class="w-12 h-12 rounded-full bg-white border flex items-center justify-center text-xl">
                                <?php 
                                    if ($k['id'] == 'khans') echo '🍛'; 
                                    elseif ($k['id'] == 'olympia') echo '🍔'; 
                                    elseif ($k['id'] == 'neptune') echo '☕'; 
                                    else echo '🍽️'; 
                                ?>
                            </div>
                            <div class="font-bold text-gray-800"><?= $k['name'] ?></div>
                        </div>
                    </a>
                <?php endforeach; ?>
                <?php if (!empty($riders)): ?>
                <div class="px-5 py-3 bg-gray-50 text-[10px] uppercase font-bold text-gray-400 mt-4">Active Riders</div>
                <?php foreach ($riders as $r): ?>
                    <a href="?rider=<?= $r['id'] ?>" class="kitchen-card block p-4 border-b border-gray-50 <?= $selected_rider == $r['id'] ? 'active' : '' ?>">
                        <div class="flex items-center gap-3">
                            <div class="w-12 h-12 rounded-full bg-red-50 border border-red-100 flex items-center justify-center text-xl">🚴</div>
                            <div class="font-bold text-gray-800"><?= htmlspecialchars($r['fullname']) ?></div>
                        </div>
                    </a>
                <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </aside>

        <main class="flex-1 chat-area flex flex-col relative <?= !($selected_kitchen || $selected_rider) ? 'hidden md:flex' : 'flex' ?>">
            <?php if ($selected_kitchen || $selected_rider): ?>
                <div class="h-16 border-b flex items-center px-6 bg-white shrink-0 z-20">
                    <a href="chat.php" class="md:hidden mr-4 text-gray-500"><svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path d="M15 19l-7-7 7-7" /></svg></a>
                    <div class="flex items-center gap-3">
                         <div class="w-10 h-10 rounded-full bg-red-50 flex items-center justify-center text-lg"><?= $chat_icon ?></div>
                         <h2 class="font-bold text-gray-900"><?= htmlspecialchars($chat_name) ?></h2>
                    </div>
                </div>
                <div id="messagesList" class="flex-1 overflow-y-auto custom-scroll p-6 flex flex-col gap-2"></div>
                <div class="p-4 bg-white border-t z-20">
                    <form id="chatForm" class="flex gap-3 max-w-4xl mx-auto items-end">
                        <input type="text" id="messageInput" class="flex-1 bg-gray-50 border rounded-2xl px-5 py-3 focus:outline-none focus:border-red-500" placeholder="Type a message..." autocomplete="off">
                        <button type="submit" class="bg-red-600 text-white rounded-xl p-3"><svg class="h-6 w-6" viewBox="0 0 20 20" fill="currentColor"><path d="M10.894 2.553a1 1 0 00-1.788 0l-7 14a1 1 0 001.169 1.409l5-1.429A1 1 0 009 15.571V11a1 1 0 112 0v4.571a1 1 0 00.725.962l5 1.428a1 1 0 001.17-1.408l-7-14z" /></svg></button>
                    </form>
                </div>
            <?php else: ?>
                <div class="flex-1 flex flex-col items-center justify-center bg-gray-50"><div class="w-20 h-20 bg-white rounded-full flex items-center justify-center mb-6 shadow-sm">💬</div><h3 class="text-2xl font-bold text-gray-900">My Messages</h3><p class="text-gray-500">Choose a kitchen or rider to start chatting.</p></div>
            <?php endif; ?>
        </main>
    </div>

<script>
    const customerId = "<?= $user_id ?>";
    const selectedKitchen = "<?= $selected_kitchen ?>";
    const selectedRider = "<?= $selected_rider ?>";
    const messagesList = document.getElementById('messagesList');
    const chatForm = document.getElementById('chatForm');
    const messageInput = document.getElementById('messageInput');
    let lastMessageCount = 0;
    let isFetching = false;

    function scrollToBottom() { if(messagesList) messagesList.scrollTop = messagesList.scrollHeight; }

    async function fetchMessages() {
        if (!(selectedKitchen || selectedRider) || isFetching) return;
        isFetching = true;
        const url = `../api/chat.php?action=get_messages&customer_id=${customerId}` + (selectedKitchen ? `&kitchen=${selectedKitchen}` : `&rider_id=${selectedRider}`);
        try {
            const res = await fetch(url);
            const data = await res.json();
            if (data.status === 'success' && data.messages.length !== lastMessageCount) {
                messagesList.innerHTML = data.messages.map(msg => {
                    const isMe = msg.sender === 'customer';
                    const time = new Date(msg.created_at).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
                    return `<div class="message-bubble ${isMe ? 'sent' : 'received'}"><div class="break-words font-medium">${msg.message}</div><div class="text-[10px] ${isMe ? 'text-white/80' : 'text-gray-400'} mt-1 text-right font-medium">${time}</div></div>`;
                }).join('');
                scrollToBottom();
                lastMessageCount = data.messages.length;
            }
        } catch (e) { console.error(e); } finally { isFetching = false; }
    }

    if (chatForm) {
        chatForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            const text = messageInput.value.trim();
            if (!text) return;
            messageInput.value = '';
            const formData = new FormData();
            formData.append('customer_id', customerId);
            if(selectedKitchen) formData.append('kitchen', selectedKitchen);
            if(selectedRider) formData.append('rider_id', selectedRider);
            formData.append('sender', 'customer');
            formData.append('message', text);
            try {
                const res = await fetch('../api/chat.php?action=send_message', { method: 'POST', body: formData });
                const data = await res.json();
                if (data.status === 'success') fetchMessages();
                else alert('Failed to send');
            } catch (e) { console.error(e); }
        });
        fetchMessages();
        setInterval(fetchMessages, 3000);
        messageInput.focus();
    }

    // Profile dropdown toggle + click outside to close
    (function() {
      const profileBtn = document.getElementById('profileBtn');
      const profileMenu = document.getElementById('profileMenu');
      if (profileBtn && profileMenu) {
        profileBtn.addEventListener('click', function(e) {
          e.stopPropagation();
          profileMenu.classList.toggle('show');
        });
        document.addEventListener('click', function() {
          profileMenu.classList.remove('show');
        });
      }
    })();
</script>
</body>
</html>
