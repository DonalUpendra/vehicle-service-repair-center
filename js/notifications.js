/* ==================== NOTIFICATIONS ==================== */
let notificationPollInterval = null;
let lastNotificationCheck = 0;

async function pollNotifications() {
    if (!currentUser) return;
    try {
        const data = await apiGet('notifications');
        updateNotificationBadge(data.unread_count);
        if (document.getElementById('notificationPanel').classList.contains('active')) {
            renderNotificationList(data.notifications);
        }
        if (data.unread_count > lastNotificationCheck && lastNotificationCheck > 0) {
            showToast(`You have ${data.unread_count} new notification(s)`, 'info');
        }
        lastNotificationCheck = data.unread_count;
    } catch (e) {
        /* silently fail */
    }
}

function updateNotificationBadge(count) {
    const badge = document.getElementById('notificationBadge');
    if (count > 0) {
        badge.textContent = count > 99 ? '99+' : count;
        badge.style.display = 'flex';
    } else {
        badge.style.display = 'none';
    }
}

function toggleNotifications() {
    const panel = document.getElementById('notificationPanel');
    const backdrop = document.getElementById('notificationBackdrop');
    const isOpen = panel.classList.toggle('active');
    if (backdrop) backdrop.classList.toggle('active', isOpen);
    if (isOpen) {
        loadNotifications();
    }
}

function closeNotifications() {
    const panel = document.getElementById('notificationPanel');
    const backdrop = document.getElementById('notificationBackdrop');
    if (panel) panel.classList.remove('active');
    if (backdrop) backdrop.classList.remove('active');
}

async function loadNotifications() {
    const list = document.getElementById('notificationList');
    list.innerHTML = '<div class="loading-state"><span class="spinner"></span><p>Loading...</p></div>';
    try {
        const data = await apiGet('notifications');
        renderNotificationList(data.notifications);
    } catch (e) {
        list.innerHTML = '<div class="empty-state"><i class="fa-solid fa-circle-exclamation"></i><h4>Failed to load</h4></div>';
    }
}

function renderNotificationList(notifications) {
    const list = document.getElementById('notificationList');
    if (notifications.length === 0) {
        list.innerHTML = '<div class="empty-state"><i class="fa-solid fa-bell-slash"></i><h4>No Notifications</h4><p>You\'re all caught up!</p></div>';
        return;
    }
    let html = '';
    notifications.forEach(n => {
        const iconMap = {
            'info': '<i class="fa-solid fa-circle-info" style="color:var(--primary);"></i>',
            'success': '<i class="fa-solid fa-circle-check" style="color:var(--success);"></i>',
            'error': '<i class="fa-solid fa-circle-exclamation" style="color:var(--danger);"></i>',
            'warning': '<i class="fa-solid fa-triangle-exclamation" style="color:var(--warning);"></i>',
        };
        const icon = iconMap[n.type] || iconMap.info;
        const timeAgo = getTimeAgo(n.created_at);
        const escapedLink = n.link ? n.link.replace(/'/g, "\\'") : '';
        const onClick = n.link
            ? `clickNotification(event, ${n.id}, '${escapedLink}')`
            : `markNotificationRead(${n.id})`;
        html += `
            <div class="notification-item ${n.is_read ? '' : 'unread'} ${n.link ? 'clickable' : ''}" onclick="${onClick}">
                <div class="notification-icon">${icon}</div>
                <div class="notification-content">
                    <div class="notification-title">${n.title}</div>
                    <div class="notification-message">${n.message}</div>
                    <div class="notification-time">${timeAgo}</div>
                </div>
            </div>`;
    });
    list.innerHTML = html;
}

function clickNotification(e, id, link) {
    e.stopPropagation();
    markNotificationRead(id);
    closeNotifications();

    if (link) {
        if (link.startsWith('index.html#')) {
            const hash = link.split('#')[1] || '';
            if (hash.startsWith('jobs')) {
                if (typeof navigateTo === 'function') {
                    navigateTo('jobs');
                } else {
                    window.location.href = 'index.html#jobs';
                }
                return;
            }
            window.location.hash = hash;
            return;
        }
    }

    if (typeof navigateTo === 'function') {
        navigateTo('jobs');
    } else {
        window.location.href = 'index.html#jobs';
    }
}

async function markNotificationRead(id) {
    try {
        await apiPost('notifications/' + id + '/read');
        const items = document.querySelectorAll('.notification-item.unread');
        items.forEach(item => {
            const onclick = item.getAttribute('onclick') || '';
            if (onclick.includes('markNotificationRead(' + id + ')') || onclick.includes('clickNotification(event, ' + id + ',')) {
                item.classList.remove('unread');
            }
        });
        pollNotifications();
    } catch (e) {
        /* ignore */
    }
}

async function markAllNotificationsRead() {
    try {
        await apiPost('notifications/read-all');
        document.querySelectorAll('.notification-item.unread').forEach(el => el.classList.remove('unread'));
        pollNotifications();
        showToast('All notifications marked as read', 'success');
    } catch (e) {
        showToast('Failed to mark all as read', 'error');
    }
}

function getTimeAgo(dateStr) {
    if (!dateStr) return '';
    const now = new Date();
    const date = new Date(dateStr);
    const diffMs = now - date;
    const diffMins = Math.floor(diffMs / 60000);
    const diffHours = Math.floor(diffMs / 3600000);
    const diffDays = Math.floor(diffMs / 86400000);

    if (diffMins < 1) return 'Just now';
    if (diffMins < 60) return diffMins + 'm ago';
    if (diffHours < 24) return diffHours + 'h ago';
    if (diffDays < 7) return diffDays + 'd ago';
    return date.toLocaleDateString('en-US', { month: 'short', day: 'numeric' });
}

function startNotificationPolling() {
    stopNotificationPolling();
    lastNotificationCheck = 0;
    pollNotifications();
    notificationPollInterval = setInterval(pollNotifications, 30000);
}

function stopNotificationPolling() {
    if (notificationPollInterval) {
        clearInterval(notificationPollInterval);
        notificationPollInterval = null;
    }
}

/* Close notification panel on backdrop click */
document.addEventListener('click', function(e) {
    const panel = document.getElementById('notificationPanel');
    const bell = document.getElementById('notificationBell');
    if (panel && panel.classList.contains('active') && !panel.contains(e.target) && !bell.contains(e.target)) {
        panel.classList.remove('active');
    }
});
