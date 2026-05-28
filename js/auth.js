/* ==================== AUTHENTICATION ==================== */
let currentUser = null;

async function handleLogin(e) {
    e.preventDefault();
    const email = document.getElementById('loginEmail').value.trim();
    const password = document.getElementById('loginPassword').value.trim();

    try {
        const data = await apiPost('login', { email, password });
        currentUser = data.user;
        setupAppLayout();
        document.getElementById('loginScreen').classList.add('hidden');
        document.getElementById('appLayout').classList.add('active');
        navigateTo('dashboard');
        startNotificationPolling();
        showToast(`Welcome back, ${data.user.name}!`, 'success');
    } catch (err) {
        document.getElementById('loginError').style.display = 'flex';
    }
}

async function handleLogout() {
    try {
        await apiPost('logout');
    } catch (e) { /* ignore */ }
    currentUser = null;
    stopNotificationPolling();
    document.getElementById('loginScreen').classList.remove('hidden');
    document.getElementById('appLayout').classList.remove('active');
    document.getElementById('sidebar').classList.remove('mobile-open');
    showToast('Logged out successfully.', 'info');
}

function setupAppLayout() {
    if (!currentUser) return;
    const isAdmin = currentUser.role === 'admin';

    document.getElementById('sidebarAvatar').textContent = currentUser.name.charAt(0).toUpperCase();
    document.getElementById('sidebarName').textContent = currentUser.name;
    document.getElementById('sidebarRole').textContent = isAdmin ? 'Administrator' : 'Technician';

    const nav = document.getElementById('sidebarNav');
    let navHTML = '';

    if (isAdmin) {
        navHTML += '<div class="nav-section">Main</div>';
        navHTML += '<a data-page="dashboard" class="active" onclick="navigateTo(\'dashboard\')"><i class="fa-solid fa-chart-pie nav-icon"></i> Dashboard</a>';
        navHTML += '<a data-page="vehicle-intake" onclick="navigateTo(\'vehicle-intake\')"><i class="fa-solid fa-car-side nav-icon"></i> Vehicle Check-In</a>';
        navHTML += '<a data-page="products" onclick="navigateTo(\'products\')"><i class="fa-solid fa-boxes-stacked nav-icon"></i> Product Catalog</a>';
        navHTML += '<a data-page="technicians" onclick="navigateTo(\'technicians\')"><i class="fa-solid fa-users-gear nav-icon"></i> Technicians</a>';
        navHTML += '<a data-page="search-bill" onclick="navigateTo(\'search-bill\')"><i class="fa-solid fa-file-invoice nav-icon"></i> Billing</a>';
        navHTML += '<a data-page="jobs" onclick="navigateTo(\'jobs\')"><i class="fa-solid fa-briefcase nav-icon"></i> Job Management</a>';
        navHTML += '<div class="nav-section">Other</div>';
        navHTML += '<a data-page="customers" onclick="navigateTo(\'customers\')"><i class="fa-solid fa-address-book nav-icon"></i> Customers</a>';
        navHTML += '<a data-page="reports" onclick="navigateTo(\'reports\')"><i class="fa-solid fa-chart-line nav-icon"></i> Reports</a>';
        navHTML += '<a data-page="salaries" onclick="navigateTo(\'salaries\')"><i class="fa-solid fa-sack-dollar nav-icon"></i> Salaries</a>';
        navHTML += '<a data-page="settings" onclick="navigateTo(\'settings\')"><i class="fa-solid fa-gear nav-icon"></i> Settings</a>';
    } else {
        navHTML += '<div class="nav-section">Main</div>';
        navHTML += '<a data-page="dashboard" class="active" onclick="navigateTo(\'dashboard\')"><i class="fa-solid fa-chart-pie nav-icon"></i> Dashboard</a>';
        navHTML += '<a data-page="vehicle-intake" onclick="navigateTo(\'vehicle-intake\')"><i class="fa-solid fa-car-side nav-icon"></i> Vehicle Check-In</a>';
        navHTML += '<a data-page="search-bill" onclick="navigateTo(\'search-bill\')"><i class="fa-solid fa-file-invoice nav-icon"></i> Search & Billing</a>';
        navHTML += '<a data-page="jobs" onclick="navigateTo(\'jobs\')"><i class="fa-solid fa-briefcase nav-icon"></i> Job Management</a>';
        navHTML += '<a data-page="products" onclick="navigateTo(\'products\')"><i class="fa-solid fa-boxes-stacked nav-icon"></i> Product Prices</a>';
    }

    nav.innerHTML = navHTML;
    document.getElementById('page-technicians').style.display = isAdmin ? '' : 'none';
}
