/* ==================== PUSH NOTIFICATIONS ==================== */
let pushPublicKey = null;
let pushSubscription = null;

function urlBase64ToUint8Array(base64String) {
    const padding = '='.repeat((4 - (base64String.length % 4)) % 4);
    const base64 = (base64String + padding).replace(/-/g, '+').replace(/_/g, '/');
    const rawData = atob(base64);
    const outputArray = new Uint8Array(rawData.length);
    for (let i = 0; i < rawData.length; i++) {
        outputArray[i] = rawData.charCodeAt(i);
    }
    return outputArray;
}

async function fetchPublicKey() {
    if (pushPublicKey) return pushPublicKey;
    try {
        const resp = await fetch('api/index.php/push/public-key', {
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json' },
        });
        const data = await resp.json();
        pushPublicKey = data.publicKey;
        return pushPublicKey;
    } catch (e) {
        console.error('Failed to fetch push public key:', e);
        return null;
    }
}

async function subscribeToPush() {
    if (!('serviceWorker' in navigator) || !('PushManager' in window)) {
        showToast('Push notifications are not supported in this browser.', 'warning');
        return false;
    }

    const permission = await Notification.requestPermission();
    if (permission !== 'granted') {
        showToast('Notification permission denied. Please enable notifications in browser settings to receive alerts.', 'warning');
        updatePushBanner();
        return false;
    }

    try {
        const registration = await navigator.serviceWorker.ready;
        let existingSubscription = await registration.pushManager.getSubscription();
        if (existingSubscription) {
            pushSubscription = existingSubscription;
            await saveSubscriptionToServer(existingSubscription);
            updatePushBanner();
            showToast('Push notifications enabled!', 'success');
            return true;
        }

        const publicKey = await fetchPublicKey();
        if (!publicKey) {
            showToast('Failed to load push notification configuration.', 'error');
            return false;
        }

        const subscription = await registration.pushManager.subscribe({
            userVisibleOnly: true,
            applicationServerKey: urlBase64ToUint8Array(publicKey),
        });

        pushSubscription = subscription;
        await saveSubscriptionToServer(subscription);
        updatePushBanner();
        showToast('Push notifications enabled!', 'success');
        return true;
    } catch (e) {
        console.error('Push subscription failed:', e);
        showToast('Failed to enable push notifications. Please try again later.', 'error');
        return false;
    }
}

async function saveSubscriptionToServer(subscription) {
    const payload = {
        endpoint: subscription.endpoint,
        keys: {
            p256dh: btoa(String.fromCharCode.apply(null, new Uint8Array(subscription.getKey('p256dh'))))
                .replace(/\+/g, '-').replace(/\//g, '_').replace(/=/g, ''),
            auth: btoa(String.fromCharCode.apply(null, new Uint8Array(subscription.getKey('auth'))))
                .replace(/\+/g, '-').replace(/\//g, '_').replace(/=/g, ''),
        },
    };
    await apiPost('push/subscribe', payload);
}

async function unsubscribeFromPush() {
    if (!pushSubscription) {
        const registration = await navigator.serviceWorker.ready;
        pushSubscription = await registration.pushManager.getSubscription();
    }
    if (pushSubscription) {
        await pushSubscription.unsubscribe();
        await apiPost('push/unsubscribe', { endpoint: pushSubscription.endpoint });
        pushSubscription = null;
    }
    updatePushBanner();
}

function updatePushBanner() {
    const banner = document.getElementById('pushNotificationBanner');
    if (!banner) return;

    if (Notification.permission === 'granted' && pushSubscription) {
        banner.style.display = 'none';
        return;
    }

    if (Notification.permission === 'denied') {
        banner.innerHTML = `
            <div class="push-banner-content">
                <i class="fa-solid fa-bell-slash"></i>
                <span>Notifications are blocked. Enable them in your browser settings.</span>
            </div>
            <button class="btn btn-outline btn-sm" disabled>
                <i class="fa-solid fa-ban"></i> Blocked
            </button>`;
        banner.style.background = 'linear-gradient(135deg, #ef4444, #dc2626)';
        banner.style.display = 'flex';
        return;
    }

    banner.style.display = 'flex';
    banner.style.background = '';
    banner.innerHTML = `
        <div class="push-banner-content">
            <i class="fa-solid fa-bell"></i>
            <span>Get instant notifications on your phone for new vehicle check-ins</span>
        </div>
        <button class="btn btn-accent btn-sm" id="enablePushBtn" onclick="subscribeToPush()">
            <i class="fa-solid fa-bell"></i> Enable Notifications
        </button>`;
}

async function initPushNotifications() {
    if (!currentUser) return;

    try {
        const registration = await navigator.serviceWorker.ready;
        pushSubscription = await registration.pushManager.getSubscription();
        if (pushSubscription) {
            await saveSubscriptionToServer(pushSubscription);
        }
    } catch (e) {
        // silently fail; user can subscribe manually
    }

    updatePushBanner();
}
