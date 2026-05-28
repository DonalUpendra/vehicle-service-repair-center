/* ==================== API HELPERS ==================== */
const API_BASE = 'api/index.php';

async function api(endpoint, options = {}) {
    const url = `${API_BASE}/${endpoint}`.replace(/([^:]\/)\/+/g, '$1');
    const config = {
        credentials: 'same-origin',
        headers: { 'Content-Type': 'application/json' },
        ...options,
    };
    if (config.body && typeof config.body !== 'string') {
        config.body = JSON.stringify(config.body);
    }
    const response = await fetch(url, config);
    const data = await response.json();
    if (!response.ok) {
        throw { status: response.status, message: data.error || 'Request failed' };
    }
    return data;
}

async function apiGet(endpoint) {
    return api(endpoint, { method: 'GET' });
}

async function apiPost(endpoint, body = {}) {
    return api(endpoint, { method: 'POST', body });
}

async function apiPut(endpoint, body = {}) {
    return api(endpoint, { method: 'PUT', body });
}

async function apiDelete(endpoint) {
    return api(endpoint, { method: 'DELETE' });
}

/* ==================== TOAST NOTIFICATIONS ==================== */
function showToast(message, type = 'info') {
    const container = document.getElementById('toastContainer');
    const toast = document.createElement('div');
    toast.className = `toast toast-${type}`;

    const icons = {
        success: '<i class="fa-solid fa-circle-check"></i>',
        error: '<i class="fa-solid fa-circle-exclamation"></i>',
        info: '<i class="fa-solid fa-circle-info"></i>',
        warning: '<i class="fa-solid fa-triangle-exclamation"></i>',
    };

    toast.innerHTML = `${icons[type] || icons.info} ${message}`;
    container.appendChild(toast);
    setTimeout(() => {
        toast.remove();
    }, 3500);
}

/* ==================== STATUS BADGE ==================== */
function getStatusBadge(status) {
    const map = {
        'checked-in': '<span class="badge badge-pending"><i class="fa-solid fa-circle"></i> Checked In</span>',
        'pending_admin_approval': '<span class="badge badge-pending"><i class="fa-solid fa-user-shield"></i> Awaiting Admin</span>',
        'pending_approval': '<span class="badge badge-pending"><i class="fa-solid fa-clock"></i> Pending Customer</span>',
        'approved': '<span class="badge badge-approved"><i class="fa-solid fa-check-circle"></i> Approved</span>',
        'in_progress': '<span class="badge badge-inprogress"><i class="fa-solid fa-spinner"></i> In Progress</span>',
        'completed': '<span class="badge badge-completed"><i class="fa-solid fa-circle-check"></i> Completed</span>',
        'rejected': '<span class="badge badge-rejected"><i class="fa-solid fa-xmark-circle"></i> Rejected</span>',
        'draft': '<span class="badge badge-draft"><i class="fa-solid fa-pen"></i> Draft</span>',
        'cancelled': '<span class="badge badge-cancelled"><i class="fa-solid fa-ban"></i> Cancelled</span>',
    };
    return map[status] || `<span class="badge badge-pending"><i class="fa-solid fa-circle"></i> ${status}</span>`;
}

/* ==================== UI HELPERS ==================== */
function toggleSidebar() {
    const sidebar = document.getElementById('sidebar');
    const backdrop = document.getElementById('sidebarBackdrop');
    const isOpen = sidebar.classList.toggle('mobile-open');
    if (backdrop) backdrop.classList.toggle('mobile-open', isOpen);
}

function closeSidebar() {
    const sidebar = document.getElementById('sidebar');
    const backdrop = document.getElementById('sidebarBackdrop');
    sidebar.classList.remove('mobile-open');
    if (backdrop) backdrop.classList.remove('mobile-open');
}

function showPage(pageId) {
    document.querySelectorAll('.page-content').forEach(p => p.classList.remove('active'));
    const pageEl = document.getElementById('page-' + pageId);
    if (pageEl) pageEl.classList.add('active');
}

function activateNav(page) {
    document.querySelectorAll('#sidebarNav a').forEach(a => a.classList.remove('active'));
    const activeLink = document.querySelector(`#sidebarNav a[data-page="${page}"]`);
    if (activeLink) activeLink.classList.add('active');
}

/* ==================== MODAL HELPERS ==================== */
function openModal(overlayId) {
    const overlay = document.getElementById(overlayId);
    if (overlay) overlay.classList.add('active');
}

function closeModal(overlayId) {
    const overlay = document.getElementById(overlayId);
    if (overlay) overlay.classList.remove('active');
}

/* ==================== LOADING HELPERS ==================== */
function showLoading(elementId, message = 'Loading...') {
    const el = document.getElementById(elementId);
    if (el) el.innerHTML = `<div class="loading-state"><span class="spinner"></span><p>${message}</p></div>`;
}

function showEmpty(elementId, icon, title, message) {
    const el = document.getElementById(elementId);
    if (el) el.innerHTML = `<div class="empty-state"><i class="fa-solid fa-${icon}"></i><h4>${title}</h4><p>${message}</p></div>`;
}

function showError(elementId, message) {
    const el = document.getElementById(elementId);
    if (el) el.innerHTML = `<div class="error-state"><i class="fa-solid fa-triangle-exclamation"></i><h4>Something went wrong</h4><p>${message}</p></div>`;
}

/* ==================== FORMAT HELPERS ==================== */
function formatDate(dateStr) {
    if (!dateStr) return '-';
    const d = new Date(dateStr);
    if (isNaN(d.getTime())) return dateStr.split('T')[0].split(' ')[0];
    return d.toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric' });
}

function formatDateTime(dateStr) {
    if (!dateStr) return '-';
    const d = new Date(dateStr);
    if (isNaN(d.getTime())) return dateStr;
    return d.toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit' });
}

function escapeHtml(str) {
    const div = document.createElement('div');
    div.appendChild(document.createTextNode(str));
    return div.innerHTML;
}

function formatCurrency(amount) {
    const num = parseFloat(amount) || 0;
    return 'LKR ' + num.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}
