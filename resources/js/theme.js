// resources/js/theme.js
// Global Theme Manager for Campus Cravings

// Configure Tailwind CDN darkMode config
if (window.tailwind) {
    tailwind.config = tailwind.config || {};
    tailwind.config.darkMode = 'class';
} else {
    window.tailwind = {
        config: {
            darkMode: 'class'
        }
    };
}

// 1. Immediately apply the stored theme on load to prevent Flash of Unstyled Content (FOUC)
(function() {
    const theme = localStorage.getItem('theme') || 'dark';
    if (theme === 'dark') {
        document.documentElement.classList.add('dark');
        document.documentElement.classList.remove('light');
    } else {
        document.documentElement.classList.add('light');
        document.documentElement.classList.remove('dark');
    }
})();

// 2. Inject CSS rules for dark/light overrides
const themeStyle = document.createElement('style');
themeStyle.id = 'theme-style-overrides';
themeStyle.innerHTML = `
    /* ==========================================
       LIGHT MODE OVERRIDES (For Dark Page Defaults)
       ========================================== */
    html.light body,
    html.light body.bg-\\[\\#0f0f0f\\],
    html.light body.bg-gray-900 {
        background-color: #f8fafc !important;
        color: #0f172a !important;
    }
    
    html.light .shops-section {
        background: linear-gradient(180deg, #f8fafc 0%, #e2e8f0 50%, #f8fafc 100%) !important;
    }
    
    html.light .section-title {
        background: linear-gradient(135deg, #0f172a, #475569) !important;
        -webkit-background-clip: text !important;
        -webkit-text-fill-color: transparent !important;
        background-clip: text !important;
    }
    
    html.light .section-subtitle {
        color: #475569 !important;
    }
    
    html.light .shop-card {
        background: #ffffff !important;
        border-color: #cbd5e1 !important;
        box-shadow: 0 10px 30px rgba(0, 0, 0, 0.05) !important;
    }
    
    html.light .shop-card:hover {
        box-shadow: 0 15px 40px rgba(0, 0, 0, 0.1) !important;
        border-color: #94a3b8 !important;
    }
    
    html.light .shop-card h3 {
        color: #0f172a !important;
    }
    
    html.light .shop-card p {
        color: #475569 !important;
    }
    
    html.light .stat-badge {
        background: #f1f5f9 !important;
        border-color: #e2e8f0 !important;
        color: #475569 !important;
    }
    
    html.light .star-empty {
        color: #cbd5e1 !important;
    }
    
    html.light footer {
        background: #f1f5f9 !important;
        border-top: 1px solid #cbd5e1 !important;
        color: #475569 !important;
    }

    /* Cart / Checkout Light Mode fixes */
    html.light .cart-container,
    html.light .bg-gray-800,
    html.light .bg-slate-900,
    html.light .bg-black\\/40 {
        background-color: #ffffff !important;
        border-color: #cbd5e1 !important;
        color: #0f172a !important;
    }
    
    html.light .border-gray-800,
    html.light .border-gray-700 {
        border-color: #cbd5e1 !important;
    }
    
    html.light .text-gray-400,
    html.light .text-gray-300 {
        color: #475569 !important;
    }
    
    html.light .page-overlay {
        background: rgba(255, 255, 255, 0.85) !important;
    }
    
    html.light .profile-card,
    html.light .settings-card {
        background-color: #ffffff !important;
        border-color: #cbd5e1 !important;
        color: #0f172a !important;
    }
    
    html.light .settings-card input,
    html.light .settings-card textarea,
    html.light .settings-card select {
        background-color: #f8fafc !important;
        border-color: #cbd5e1 !important;
        color: #0f172a !important;
    }

    /* ==========================================
       DARK MODE OVERRIDES (For Light Page Defaults)
       ========================================== */
    html.dark body,
    html.dark body.bg-white,
    html.dark body.bg-gray-100,
    html.dark body.bg-gray-50 {
        background-color: #0f0f0f !important;
        color: #f8fafc !important;
    }

    /* Primary layout container background skins */
    html.dark .bg-white,
    html.dark .bg-gray-50,
    html.dark .bg-gray-50\\/60,
    html.dark .bg-gray-100 {
        background-color: #18181b !important;
        background: #18181b !important;
        border-color: #27272a !important;
    }
    
    /* Global heading tags color force */
    html.dark h1,
    html.dark h2,
    html.dark h3,
    html.dark h4,
    html.dark h5,
    html.dark h6 {
        color: #ffffff !important;
    }

    /* Dark-text utility overrides (maps unreadable dark texts to white/light-gray) */
    html.dark .text-black,
    html.dark .text-gray-950,
    html.dark .text-gray-900,
    html.dark .text-gray-800,
    html.dark .text-slate-950,
    html.dark .text-slate-900,
    html.dark .text-slate-800,
    html.dark .text-zinc-950,
    html.dark .text-zinc-900,
    html.dark .text-zinc-800,
    html.dark .text-neutral-950,
    html.dark .text-neutral-900,
    html.dark .text-neutral-800,
    html.dark [style*="color:#1f2937"],
    html.dark [style*="color: #1f2937"],
    html.dark [style*="color:#374151"],
    html.dark [style*="color: #374151"],
    html.dark [style*="color:#111827"],
    html.dark [style*="color: #111827"],
    html.dark .review-author {
        color: #f8fafc !important;
    }
    
    html.dark .text-gray-700,
    html.dark .text-gray-600,
    html.dark .text-gray-500,
    html.dark .text-slate-700,
    html.dark .text-slate-600,
    html.dark .text-slate-500,
    html.dark .text-zinc-700,
    html.dark .text-zinc-600,
    html.dark .text-zinc-500,
    html.dark .text-neutral-700,
    html.dark .text-neutral-600,
    html.dark .text-neutral-500,
    html.dark .review-text {
        color: #cbd5e1 !important;
    }
    
    html.dark .review-date {
        color: #94a3b8 !important;
    }

    /* Amber / Yellow / Blue / Red soft colors in dark mode for highlights */
    html.dark .text-amber-900,
    html.dark .text-amber-800,
    html.dark .text-amber-700 {
        color: #fef3c7 !important;
    }
    html.dark .review-summary-label,
    html.dark .review-summary-value {
        color: #fef3c7 !important;
    }

    /* Table headers, rows specific specificity overrides */
    html.dark table.product-table th,
    html.dark .table th,
    html.dark table th {
        background-color: #202024 !important;
        background: #202024 !important;
        color: #cbd5e1 !important;
        border-color: #27272a !important;
    }
    html.dark table.product-table td,
    html.dark .table td,
    html.dark table td {
        background-color: #18181b !important;
        background: #18181b !important;
        color: #cbd5e1 !important;
        border-color: #27272a !important;
    }

    /* Table, cell borders, lists */
    html.dark .border-gray-100,
    html.dark .border-gray-200,
    html.dark .border-gray-300,
    html.dark .border-gray-100\\/90,
    html.dark .border-gray-100\\/80 {
        border-color: #27272a !important;
    }
    
    html.dark .shadow-sm,
    html.dark .shadow-md,
    html.dark .shadow-lg,
    html.dark .shadow-2xl {
        box-shadow: 0 4px 20px rgba(0, 0, 0, 0.4) !important;
    }
    
    html.dark .hover\\:bg-red-50:hover {
        background-color: rgba(239, 68, 68, 0.15) !important;
        color: #ef4444 !important;
    }

    /* Form Fields dark overrides globally (clearing background-image gradients) */
    html.dark input,
    html.dark select,
    html.dark textarea {
        background-color: #202024 !important;
        background-image: none !important;
        background: #202024 !important;
        border-color: #3f3f46 !important;
        color: #f8fafc !important;
    }
    
    /* Modals dark overrides */
    html.dark .modal-content {
        background-color: #18181b !important;
        background: #18181b !important;
        color: #f8fafc !important;
        border: 1px solid #27272a !important;
    }
    html.dark .btn-cancel {
        background-color: #27272a !important;
        background: #27272a !important;
        color: #cbd5e1 !important;
    }
    html.dark .btn-cancel:hover {
        background-color: #3f3f46 !important;
        background: #3f3f46 !important;
    }
    
    /* Guest auth buttons on light pages in dark mode */
    html.dark .auth-btn-outline {
        border-color: rgba(255,255,255,0.6) !important;
        color: #fff !important;
    }

    /* Chat page dark overrides */
    html.dark .sidebar {
        background: #18181b !important;
        border-right: 1px solid #27272a !important;
    }
    
    html.dark .sidebar h2,
    html.dark .sidebar div:not(.bg-red-50) {
        color: #f8fafc !important;
    }
    
    html.dark .sidebar .bg-gray-50 {
        background-color: #27272a !important;
        color: #a1a1aa !important;
    }
    
    html.dark .kitchen-card {
        border-bottom-color: #27272a !important;
    }
    
    html.dark .kitchen-card:hover {
        background-color: #202024 !important;
    }
    
    html.dark .kitchen-card.active {
        background-color: #2e1818 !important;
        border-right: 3px solid #ef4444 !important;
    }
    
    html.dark .chat-area {
        background-color: #111113 !important;
        background-image: radial-gradient(#27272a 1px, transparent 1px) !important;
    }
    
    html.dark .chat-area div.bg-white {
        background-color: #18181b !important;
        border-bottom-color: #27272a !important;
        border-top-color: #27272a !important;
    }
    
    html.dark .chat-area h2 {
        color: #f8fafc !important;
    }
    
    html.dark .received {
        background-color: #27272a !important;
        color: #f8fafc !important;
        border: 1px solid #3f3f46 !important;
    }
    
    html.dark #messageInput {
        background-color: #202024 !important;
        border-color: #3f3f46 !important;
        color: #f8fafc !important;
    }
    
    html.dark #messageInput::placeholder {
        color: #71717a !important;
    }
    
    html.dark .chat-area .flex-1.bg-gray-50 {
        background-color: #111113 !important;
    }
    
    html.dark .chat-area .flex-1.bg-gray-50 div.bg-white {
        background-color: #18181b !important;
    }

    /* Checkout & Invoice page dark backdrop overlays */
    html.dark body::after {
        background: rgba(15, 23, 42, 0.85) !important;
    }
    
    html.dark .payment-option {
        border-color: #27272a !important;
        background: linear-gradient(135deg, #18181b 0%, #202024 100%) !important;
    }
    
    html.dark .payment-option:hover {
        border-color: #ef4444 !important;
        background: linear-gradient(135deg, #2e1818 0%, #202024 100%) !important;
    }
    
    html.dark .payment-option input[type="radio"]:checked + label {
        border-color: #ef4444 !important;
        background: linear-gradient(135deg, #3f1a1a 0%, #2e1818 100%) !important;
    }

    /* Admin statistics & order cards styling overrides */
    html.dark .order-card {
        background-color: #18181b !important;
        border: 1px solid #27272a !important;
    }
    
    html.dark .item-row {
        border-bottom-color: #27272a !important;
    }

    /* Order success dark overrides */
    html.dark .order-success-card,
    html.dark .bg-gray-100 {
        background-color: #18181b !important;
        color: #f8fafc !important;
    }

    /* ==========================================
       NAVBAR TEXT ALWAYS WHITE STANDARDIZATION
       ========================================== */
    header .nav-buttons a,
    header .nav-buttons button,
    header .brand-text,
    header #profileBtn span {
        color: #ffffff !important;
    }
`;
document.head.appendChild(themeStyle);

// 3. Inject Toggle Button once profile dropdown container is loaded
(function() {
    function initThemeToggle() {
        const profileRoot = document.getElementById('profileRoot');
        if (!profileRoot || document.getElementById('themeToggleBtn')) return;

        const btn = document.createElement('button');
        btn.id = 'themeToggleBtn';
        btn.type = 'button';
        btn.className = 'flex items-center justify-center p-2 rounded-full hover:bg-white/10 text-white transition-colors duration-200 focus:outline-none';
        btn.style.cursor = 'pointer';
        btn.style.marginRight = '12px';
        btn.style.width = '38px';
        btn.style.height = '38px';

        // SVGs for Sun and Moon
        const sunIcon = `<svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-amber-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364-6.364l-.707.707M6.343 17.657l-.707.707m0-12.728l.707.707m12.728 12.728l.707-.707M12 8a4 4 0 100 8 4 4 0 000-8z" />
        </svg>`;
        const moonIcon = `<svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-blue-300" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z" />
        </svg>`;

        const theme = localStorage.getItem('theme') || 'dark';
        btn.innerHTML = theme === 'dark' ? sunIcon : moonIcon;

        btn.addEventListener('click', function() {
            const isDark = document.documentElement.classList.contains('dark');
            if (isDark) {
                document.documentElement.classList.remove('dark');
                document.documentElement.classList.add('light');
                localStorage.setItem('theme', 'light');
                btn.innerHTML = moonIcon;
            } else {
                document.documentElement.classList.remove('light');
                document.documentElement.classList.add('dark');
                localStorage.setItem('theme', 'dark');
                btn.innerHTML = sunIcon;
            }
        });

        const parent = profileRoot.parentElement;
        if (parent) {
            parent.style.display = 'flex';
            parent.style.alignItems = 'center';
            parent.insertBefore(btn, profileRoot);
        }
    }

    // Attempt to load immediately if page is loaded
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initThemeToggle);
    } else {
        initThemeToggle();
    }

    // Backup interval polling for dynamically loaded profiles
    const poll = setInterval(function() {
        if (document.getElementById('profileRoot')) {
            initThemeToggle();
            clearInterval(poll);
        }
    }, 50);

    setTimeout(function() {
        clearInterval(poll);
    }, 4000);
})();
