<?php
// admin/chat/index.php (Optimized for multi-role)
session_start();
require_once '../../config/db_connection.php';

if (!isset($_SESSION['usertype']) || $_SESSION['usertype'] !== 'admin' || !isset($_SESSION['shop'])) {
    header('Location: ../../login.php');
    exit;
}

$shopName = $_SESSION['shop'];
$kitchenId = (stripos($shopName, 'khans') !== false) ? 'khans' : ((stripos($shopName, 'neptune') !== false) ? 'neptune' : 'olympia');
$navbarLink = "../$kitchenId/navbar.php";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Messages - <?= htmlspecialchars($shopName) ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;600;700&display=swap" rel="stylesheet">
    <style>
        body { background-color: #F9FAFB; color: #1F2937; font-family: 'Outfit', sans-serif; min-height: 100vh; }
        .card { background: white; border: 1px solid #E5E7EB; transition: all 0.2s ease; box-shadow: 0 1px 3px rgba(0,0,0,0.05); }
        .card:hover { transform: translateY(-2px); box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1); border-color: #EF4444; }
    </style>
</head>
<body>
    <header class="bg-white border-b border-gray-200 h-20 flex items-center justify-between px-8 sticky top-0 z-50 shadow-sm">
        <div class="flex items-center gap-6">
            <a href="<?= $navbarLink ?>" class="group flex items-center gap-2 text-gray-500 hover:text-red-600 transition">
                <div class="p-2 rounded-full group-hover:bg-red-50 transition"><svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path d="M10 19l-7-7m0 0l7-7m-7 7h18" /></svg></div>
                <span class="font-medium">Dashboard</span>
            </a>
            <h1 class="text-2xl font-bold text-gray-900 tracking-tight">Messages</h1>
        </div>
        <div class="flex items-center gap-4">
             <div class="text-right hidden sm:block"><div class="text-xs text-gray-400">ADMIN</div><div class="font-bold text-gray-900"><?= $shopName ?></div></div>
             <div class="w-10 h-10 rounded-full bg-red-100 text-red-600 border flex items-center justify-center font-bold font-mono"><?= substr($shopName, 0, 1) ?></div>
        </div>
    </header>

    <main class="max-w-6xl mx-auto p-6 md:p-10">
        <div id="loading" class="flex flex-col items-center justify-center py-20"><div class="w-12 h-12 rounded-full border-4 border-gray-200 border-t-red-600 animate-spin mb-4"></div><p class="text-gray-400">Loading conversations...</p></div>
        <div id="conversationList" class="grid grid-cols-1 md:grid-cols-2 gap-6"></div>
    </main>

<script>
    const kitchenId = "<?= $kitchenId ?>";
    const listEl = document.getElementById('conversationList');
    const loadingEl = document.getElementById('loading');

    async function loadConversations() {
        try {
            const res = await fetch(`../../api/chat.php?action=get_admin_conversations&kitchen=${kitchenId}`);
            const data = await res.json();
            if (loadingEl) loadingEl.style.display = 'none';
            if (data.status === 'success') {
                const { customers, riders } = data.conversations;
                if ((!customers || customers.length === 0) && (!riders || riders.length === 0)) {
                    listEl.innerHTML = '<div class="col-span-full py-20 text-center text-gray-400">No active chats found.</div>';
                    return;
                }
                let html = '';
                if (customers && customers.length > 0) {
                    html += '<div class="col-span-full mb-2 text-xs font-bold text-gray-400 uppercase tracking-widest">Customer Inquiries</div>';
                    customers.forEach(c => html += renderCard(c, `chat.php?customer_id=${c.id}`, '👤', 'Customer'));
                }
                if (riders && riders.length > 0) {
                    html += '<div class="col-span-full mt-8 mb-2 text-xs font-bold text-gray-400 uppercase tracking-widest">Delivery Support</div>';
                    riders.forEach(r => html += renderCard(r, `chat.php?rider_id=${r.id}`, '🚴', 'Rider'));
                }
                listEl.innerHTML = html;
            }
        } catch (e) { console.error(e); }
    }

    function renderCard(c, link, icon, type) {
        const timeDisp = c.last_msg_time ? new Date(c.last_msg_time).toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'}) : 'Active';
        return `
            <a href="${link}" class="card rounded-2xl p-6 flex items-center gap-5 group cursor-pointer border border-gray-100 shadow-sm hover:shadow-md transition-all">
                <div class="w-14 h-14 rounded-full bg-gray-50 flex items-center justify-center text-2xl border border-gray-100 group-hover:bg-red-50 transition-colors">${icon}</div>
                <div class="flex-1 min-w-0">
                    <div class="flex items-center justify-between mb-1">
                        <h2 class="text-lg font-bold text-gray-900 truncate">${c.fullname}</h2>
                        <span class="text-[10px] bg-gray-100 px-2 py-1 rounded text-gray-500 font-bold">${timeDisp}</span>
                    </div>
                    <div class="text-sm text-gray-500 flex items-center gap-2">
                        <span class="truncate">@${c.username}</span>
                        <span class="w-1 h-1 rounded-full bg-gray-300"></span>
                        <span class="text-red-500 text-xs font-bold tracking-wide">${type.toUpperCase()}</span>
                    </div>
                </div>
            </a>
        `;
    }

    loadConversations();
    setInterval(loadConversations, 5000);
</script>
</body>
</html>
