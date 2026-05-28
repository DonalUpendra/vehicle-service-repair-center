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
        navigateTo('dashboard');
        startNotificationPolling();
    } catch {
        document.getElementById('loginScreen').classList.remove('hidden');
        document.getElementById('appLayout').classList.remove('active');
        document.getElementById('publicQuotePage').classList.remove('active');
    }
}

document.addEventListener('DOMContentLoaded', init);

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
    }
});
