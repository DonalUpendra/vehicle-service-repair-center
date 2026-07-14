/* ==================== APP STATE & NAVIGATION ==================== */
let currentPage = 'dashboard';
let editingBillVehicleId = null;

function navigateTo(page) {
    currentPage = page;
    activateNav(page);
    showPage(page);
    closeSidebar();
    renderPage(page);
}

function renderPage(page) {
    switch (page) {
        case 'dashboard':
            renderDashboard();
            break;
        case 'vehicle-intake':
            renderVehicleIntake();
            break;
        case 'products':
            renderProducts();
            break;
        case 'technicians':
            renderTechnicians();
            break;
        case 'search-bill':
            renderSearchBill();
            break;
        case 'customers':
            renderCustomers();
            break;
        case 'reports':
            renderReports();
            break;
        case 'jobs':
            renderJobs();
            break;
        case 'salaries':
            renderSalaries();
            break;
        case 'settings':
            renderSettings();
            break;
    }
}

/* ==================== INITIALIZATION ==================== */
async function init() {
    const urlParams = new URLSearchParams(window.location.search);
    const token = urlParams.get('token');

    if (token) {
        loadPublicQuotePage();
        return;
    }

    try {
        const data = await apiGet('me');
        currentUser = data.user;
        setupAppLayout();
        document.getElementById('loginScreen').classList.add('hidden');
        document.getElementById('appLayout').classList.add('active');

        const hash = window.location.hash.replace('#', '');
        const targetPage = hash || 'dashboard';
        navigateTo(targetPage);

        startNotificationPolling();
        initPushNotifications();
    } catch {
        document.getElementById('loginScreen').classList.remove('hidden');
        document.getElementById('appLayout').classList.remove('active');
        document.getElementById('publicQuotePage').classList.remove('active');
    }
}

document.addEventListener('DOMContentLoaded', init);

window.addEventListener('hashchange', function() {
    if (!currentUser) return;
    const hash = window.location.hash.replace('#', '');
    if (hash && hash !== currentPage) {
        navigateTo(hash);
    }
});

/* ==================== MODAL BACKDROP CLICK TO CLOSE ==================== */
document.getElementById('productModalOverlay')?.addEventListener('click', function(e) {
    if (e.target === this) closeProductModal();
});

document.getElementById('technicianModalOverlay')?.addEventListener('click', function(e) {
    if (e.target === this) closeTechnicianModal();
});

document.getElementById('billModalOverlay')?.addEventListener('click', function(e) {
    if (e.target === this) closeBillModal();
});

document.getElementById('emailModalOverlay')?.addEventListener('click', function(e) {
    if (e.target === this) closeModal('emailModalOverlay');
});

/* ==================== SIDEBAR BACKDROP ==================== */
document.getElementById('sidebarBackdrop')?.addEventListener('click', closeSidebar);

/* ==================== KEYBOARD SHORTCUTS ==================== */
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        document.querySelectorAll('.modal-overlay.active').forEach(m => {
            m.classList.remove('active');
        });
        closeSidebar();
        if (typeof closeNotifications === 'function') closeNotifications();
    }
});

/* ==================== PWA INSTALL PROMPT ==================== */
let deferredInstallPrompt = null;

window.addEventListener('beforeinstallprompt', function(e) {
    e.preventDefault();
    deferredInstallPrompt = e;
    const banner = document.getElementById('installBanner');
    if (banner) banner.style.display = 'flex';
});

window.addEventListener('appinstalled', function() {
    deferredInstallPrompt = null;
    const banner = document.getElementById('installBanner');
    if (banner) banner.style.display = 'none';
});

function promptInstall() {
    if (!deferredInstallPrompt) return;
    deferredInstallPrompt.prompt();
    deferredInstallPrompt.userChoice.then(function(result) {
        deferredInstallPrompt = null;
        const banner = document.getElementById('installBanner');
        if (banner) banner.style.display = 'none';
    });
}
