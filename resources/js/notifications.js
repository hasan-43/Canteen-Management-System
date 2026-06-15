(function() {
    // Dynamic Toast Injector
    function showNotificationToast(message, type = 'info') {
        let toastContainer = document.getElementById('notification-toast-container');
        if (!toastContainer) {
            toastContainer = document.createElement('div');
            toastContainer.id = 'notification-toast-container';
            toastContainer.style.cssText = 'position: fixed; bottom: 2rem; right: 2rem; z-index: 99999; display: flex; flex-direction: column; gap: 0.75rem; max-width: 380px; font-family: "Outfit", sans-serif;';
            document.body.appendChild(toastContainer);
        }
        
        const toast = document.createElement('div');
        toast.style.cssText = `
            background: rgba(10, 10, 12, 0.95);
            backdrop-filter: blur(16px);
            -webkit-backdrop-filter: blur(16px);
            color: #fff;
            padding: 1.25rem 1.5rem;
            border-radius: 1.25rem;
            box-shadow: 0 20px 40px -5px rgba(0,0,0,0.5), 0 0 25px rgba(239, 68, 68, 0.25);
            border: 1px solid rgba(255, 255, 255, 0.1);
            display: flex;
            align-items: center;
            gap: 1rem;
            transform: translateX(450px);
            transition: transform 0.4s cubic-bezier(0.16, 1, 0.3, 1);
            cursor: pointer;
        `;
        
        const icon = type === 'message' ? '💬' : '🔔';
        const title = type === 'message' ? 'New Message' : 'New Order';
        
        toast.innerHTML = `
            <div style="font-size: 1.75rem; display: flex; align-items: center; justify-content: center; background: rgba(239, 68, 68, 0.1); border-radius: 0.75rem; padding: 0.5rem; width: 48px; height: 48px;">${icon}</div>
            <div style="flex-grow: 1; min-width: 0;">
                <div style="font-weight: 800; font-size: 0.85rem; color: #ef4444; text-transform: uppercase; tracking-wider; margin-bottom: 2px;">${title}</div>
                <div style="font-size: 0.95rem; color: #e2e8f0; font-weight: 500; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">${message}</div>
            </div>
            <div style="opacity: 0.5; font-size: 1.2rem; line-height: 1; align-self: flex-start; margin-top: -2px;">&times;</div>
        `;
        
        toast.addEventListener('click', (e) => {
            if (e.target.innerText === '×') {
                toast.style.transform = 'translateX(450px)';
                setTimeout(() => toast.remove(), 400);
                return;
            }
            toast.style.transform = 'translateX(450px)';
            setTimeout(() => toast.remove(), 400);
            
            // Resolve relative path to redirect
            const inAdmin = window.location.pathname.includes('/admin/');
            const inDelivery = window.location.pathname.includes('/Delivery Man/');
            
            if (type === 'message') {
                if (inAdmin) {
                    window.location.href = '../../admin/chat/index.php';
                } else if (inDelivery) {
                    window.location.href = '../Delivery Man/chat.php';
                } else {
                    window.location.href = '../customer/chat.php';
                }
            } else {
                if (inAdmin) {
                    window.location.href = 'orders.php';
                } else if (inDelivery) {
                    window.location.href = '../Delivery Man/orders.php';
                }
            }
        });
        
        toastContainer.appendChild(toast);
        // Slide in
        setTimeout(() => {
            toast.style.transform = 'translateX(0)';
        }, 50);
        
        // Auto remove
        setTimeout(() => {
            if (toast.parentNode) {
                toast.style.transform = 'translateX(450px)';
                setTimeout(() => toast.remove(), 400);
            }
        }, 6000);
    }

    // Determine path dynamically based on URL structure
    let apiPath = '';
    const path = window.location.pathname;
    if (path.includes('/admin/')) {
        apiPath = '../../api/notifications.php';
    } else if (path.includes('/Delivery Man/')) {
        apiPath = '../api/notifications.php';
    } else {
        apiPath = '../api/notifications.php';
    }
    
    function checkNotifications() {
        fetch(apiPath + '?action=check_updates')
            .then(res => res.json())
            .then(data => {
                if (data.status === 'success') {
                    // 1. Check Messages
                    const prevUnread = sessionStorage.getItem('unread_messages_count');
                    const currentUnread = parseInt(data.unread_messages);
                    
                    if (prevUnread !== null) {
                        if (currentUnread > parseInt(prevUnread)) {
                            // Don't show toast if we are already inside the chat viewport
                            if (!path.includes('chat.php')) {
                                showNotificationToast('You have new unread messages!', 'message');
                                try { (new Audio('https://assets.mixkit.co/active_storage/sfx/2357/2357-84.wav')).play(); } catch(e){}
                            }
                        }
                    }
                    sessionStorage.setItem('unread_messages_count', currentUnread);
                    
                    // 2. Check Orders (Admin/Delivery only)
                    if (data.role === 'admin' || data.role === 'delivery') {
                        const prevLatestOrder = sessionStorage.getItem('latest_order_id');
                        const currentLatestOrder = parseInt(data.latest_order_id);
                        
                        if (prevLatestOrder !== null) {
                            if (currentLatestOrder > parseInt(prevLatestOrder)) {
                                showNotificationToast('A new order has been received!', 'order');
                                try { (new Audio('https://assets.mixkit.co/active_storage/sfx/2869/2869-84.wav')).play(); } catch(e){}
                                
                                // Auto reload page if viewing orders log
                                if (path.includes('orders.php')) {
                                    setTimeout(() => window.location.reload(), 2000);
                                }
                            }
                        }
                        sessionStorage.setItem('latest_order_id', currentLatestOrder);
                    }
                }
            })
            .catch(e => console.error('Notification check failed', e));
    }
    
    // Setup initial check and polling
    setTimeout(() => {
        // Initial fetch to populate base values without triggers
        fetch(apiPath + '?action=check_updates')
            .then(res => res.json())
            .then(data => {
                if (data.status === 'success') {
                    sessionStorage.setItem('unread_messages_count', data.unread_messages);
                    if (data.role === 'admin' || data.role === 'delivery') {
                        sessionStorage.setItem('latest_order_id', data.latest_order_id);
                    }
                }
            });
            
        // Check updates every 4 seconds
        setInterval(checkNotifications, 4000);
    }, 1000);
})();
