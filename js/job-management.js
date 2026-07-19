/* ==================== JOB MANAGEMENT ==================== */
let currentJobTab = 'pending_admin_approval';

function getStatusLabel(status) {
    const labels = {
        'draft': 'Draft',
        'pending_admin_approval': 'Awaiting Review',
        'pending_approval': 'Awaiting Customer',
        'approved': 'Approved',
        'in_progress': 'In Progress',
        'pending_admin_delivery': 'Awaiting Approval',
        'ready_for_delivery': 'Ready for Delivery',
        'completed': 'Completed',
        'rejected': 'Rejected',
        'cancelled': 'Cancelled',
        'checked-in': 'Checked In',
    };
    return labels[status] || status;
}

async function renderJobs() {
    const isAdmin = currentUser && currentUser.role === 'admin';
    const contentEl = document.getElementById('jobsContent');
    const tabsEl = document.getElementById('jobTabs');

    if (!contentEl || !tabsEl) {
        showToast('Job management UI not found. Please refresh the page.', 'error');
        return;
    }

    try {
        const stats = await apiGet('jobs/stats');
        renderJobTabs(stats, isAdmin);

        let initialTab;
        if (isAdmin) {
            initialTab = stats.pending_admin_approval > 0 ? 'pending_admin_approval'
                : (stats.pending_admin_delivery > 0 ? 'pending_admin_delivery'
                : (stats.in_progress > 0 ? 'in_progress'
                : (stats.ready_for_delivery > 0 ? 'ready_for_delivery'
                : 'pending_admin_approval')));
        } else {
            initialTab = stats.my_active > 0 ? 'my_active'
                : (stats.my_drafts > 0 ? 'draft'
                : 'my_active');
        }
        currentJobTab = initialTab;
        await loadTabJobs(initialTab, isAdmin);
    } catch (err) {
        contentEl.innerHTML = '<div class="empty-state"><i class="fa-solid fa-triangle-exclamation"></i><h4>Failed to load</h4><p>' + (err.message || 'Unknown error') + '</p></div>';
        showToast('Failed to load jobs', 'error');
    }
}

function renderJobTabs(stats, isAdmin) {
    const tabsEl = document.getElementById('jobTabs');
    if (!tabsEl) return;
    let tabs = [];

    if (isAdmin) {
        tabs = [
            { key: 'pending_admin_approval', label: 'Awaiting Review', count: stats.pending_admin_approval || 0, icon: 'fa-user-shield' },
            { key: 'pending_approval', label: 'With Customer', count: stats.pending_approval || 0, icon: 'fa-envelope' },
            { key: 'in_progress', label: 'In Progress', count: stats.in_progress || 0, icon: 'fa-spinner' },
            { key: 'pending_admin_delivery', label: 'Awaiting Approval', count: stats.pending_admin_delivery || 0, icon: 'fa-clipboard-check' },
            { key: 'ready_for_delivery', label: 'Ready for Delivery', count: stats.ready_for_delivery || 0, icon: 'fa-truck-fast' },
            { key: 'completed', label: 'Completed', count: stats.completed || 0, icon: 'fa-circle-check' },
            { key: 'rejected', label: 'Rejected', count: stats.rejected || 0, icon: 'fa-xmark-circle' },
            { key: 'all', label: 'All Jobs', count: null, icon: 'fa-list' },
        ];
    } else {
        tabs = [
            { key: 'my_active', label: 'My Active Jobs', count: stats.my_active || 0, icon: 'fa-briefcase' },
            { key: 'draft', label: 'My Drafts', count: stats.my_drafts || 0, icon: 'fa-pen' },
            { key: 'ready_for_delivery', label: 'Ready for Delivery', count: stats.ready_for_delivery || 0, icon: 'fa-truck-fast' },
            { key: 'completed', label: 'Completed', count: stats.completed || 0, icon: 'fa-circle-check' },
            { key: 'all', label: 'All Jobs', count: null, icon: 'fa-list' },
        ];
    }

    let html = '';
    tabs.forEach(tab => {
        const activeClass = tab.key === currentJobTab ? ' active' : '';
        const badge = tab.count !== null ? `<span class="jtab-badge">${tab.count > 99 ? '99+' : tab.count}</span>` : '';
        html += `
            <button class="job-tab${activeClass}" onclick="switchJobTab('${tab.key}')" data-tab="${tab.key}">
                <i class="fa-solid ${tab.icon}"></i> ${tab.label} ${badge}
            </button>`;
    });
    tabsEl.innerHTML = html;
}

function switchJobTab(tabKey) {
    currentJobTab = tabKey;
    const isAdmin = currentUser && currentUser.role === 'admin';

    document.querySelectorAll('.job-tab').forEach(t => t.classList.remove('active'));
    const activeTab = document.querySelector(`.job-tab[data-tab="${tabKey}"]`);
    if (activeTab) activeTab.classList.add('active');

    loadTabJobs(tabKey, isAdmin);
}

async function loadTabJobs(tabKey, isAdmin) {
    const contentEl = document.getElementById('jobsContent');
    if (!contentEl) return;

    contentEl.innerHTML = '<div class="loading-state"><span class="spinner"></span><p>Loading jobs...</p></div>';

    try {
        let jobs;
        const tabStatusMap = {
            'pending_admin_approval': 'pending_admin_approval',
            'pending_approval': 'pending_approval',
            'in_progress': 'in_progress',
            'pending_admin_delivery': 'pending_admin_delivery',
            'ready_for_delivery': 'ready_for_delivery',
            'completed': 'completed',
            'rejected': 'rejected',
            'draft': 'draft',
            'my_active': 'approved,in_progress',
        };

        const statusFilter = tabStatusMap[tabKey] || '';

        if (tabKey === 'all') {
            const allStatuses = 'pending_admin_approval,pending_approval,approved,in_progress,pending_admin_delivery,ready_for_delivery,completed,rejected,draft,cancelled';
            jobs = await apiGet('jobs?status=' + encodeURIComponent(allStatuses));
        } else if (statusFilter) {
            jobs = await apiGet('jobs?status=' + encodeURIComponent(statusFilter));
        } else {
            jobs = await apiGet('jobs');
        }

        if (jobs.length === 0) {
            const tabLabel = document.querySelector(`.job-tab[data-tab="${tabKey}"]`);
            const labelText = tabLabel ? tabLabel.textContent.trim().replace(/\d+$/, '').trim() : 'Jobs';
            contentEl.innerHTML = `<div class="empty-state"><i class="fa-solid fa-inbox"></i><h4>No ${labelText}</h4><p>No jobs found in this category.</p></div>`;
            return;
        }

        renderJobTable(jobs, tabKey, isAdmin);
    } catch (err) {
        contentEl.innerHTML = '<div class="empty-state"><i class="fa-solid fa-triangle-exclamation"></i><h4>Failed to load</h4><p>' + (err.message || 'Unknown error') + '</p></div>';
    }
}

function renderJobTable(jobs, tabKey, isAdmin) {
    const contentEl = document.getElementById('jobsContent');

    const showCommission = isAdmin || !['my_active', 'draft'].includes(tabKey);

    let html = '<div class="table-responsive"><table class="striped"><thead><tr>';
    html += '<th>Bill ID</th><th>Vehicle</th><th>Owner</th>';
    if (isAdmin || ['all', 'completed', 'rejected', 'ready_for_delivery'].includes(tabKey)) {
        html += '<th>Technician</th>';
    }
    if (showCommission) {
        html += '<th>Total (LKR)</th><th>Commission</th>';
    } else {
        html += '<th>Total (LKR)</th>';
    }
    html += '<th>Status</th><th>Date</th><th>Action</th></tr></thead><tbody>';

    jobs.forEach(job => {
        const isOwnJob = isAdmin || (currentUser && job.technician_id === currentUser.id);
        const showTechCol = isAdmin || ['all', 'completed', 'rejected', 'ready_for_delivery'].includes(tabKey);
        const commissionAmt = job.commission_amount || 0;
        const commissionStr = job.commission_percentage > 0
            ? `<small>${formatCurrency(commissionAmt)}<br><span style="color:var(--text-muted);font-size:0.75rem;">(${job.commission_percentage}%)</span></small>`
            : '<small style="color:var(--text-muted);">-</small>';

        const actionHTML = getJobActions(job, isAdmin, isOwnJob);

        html += '<tr>';
        html += `<td><strong>#${job.id}</strong></td>`;
        html += `<td>${job.registration_number || 'N/A'} — ${job.make || ''} ${job.model || ''}</td>`;
        html += `<td>${job.owner_name || 'N/A'}</td>`;
        if (showTechCol) {
            html += `<td>${job.technician_name || 'N/A'}</td>`;
        }
        html += `<td><strong>${formatCurrency(job.total_amount)}</strong></td>`;
        if (showCommission) {
            html += `<td>${commissionStr}</td>`;
        }
        html += `<td>${getStatusBadge(job.status)}</td>`;
        html += `<td>${formatDate(job.created_at)}</td>`;
        html += `<td>${actionHTML}</td>`;
        html += '</tr>';
    });

    html += '</tbody></table></div>';
    contentEl.innerHTML = html;
}

function getJobActions(job, isAdmin, isOwnJob) {
    const safeOwner = (job.owner_name || 'Customer').replace(/'/g, "\\'");
    const safeReg = (job.registration_number || '').replace(/'/g, "\\'");
    const safeMake = (job.make || '').replace(/'/g, "\\'");
    const safeModel = (job.model || '').replace(/'/g, "\\'");

    if (job.status === 'pending_admin_approval') {
        if (isAdmin) {
            return `<button class="btn btn-sm btn-success" onclick="adminApproveBill(${job.id})"><i class="fa-solid fa-check"></i> Approve</button>
                    <button class="btn btn-sm btn-danger" onclick="openRejectModal(${job.id})"><i class="fa-solid fa-xmark"></i> Reject</button>
                    <button class="btn btn-sm btn-outline" onclick="viewBill(${job.id})"><i class="fa-solid fa-eye"></i></button>`;
        }
        return `<span class="badge badge-pending"><i class="fa-solid fa-user-shield"></i> Awaiting admin</span>
                <button class="btn btn-sm btn-outline" onclick="viewBill(${job.id})"><i class="fa-solid fa-eye"></i></button>`;
    }

    if (job.status === 'pending_approval') {
        if (isAdmin) {
            return `<button class="btn btn-sm btn-accent" onclick="resendApproval(${job.id})"><i class="fa-solid fa-paper-plane"></i> Resend</button>
                    <button class="btn btn-sm btn-danger" onclick="cancelPendingApproval(${job.id})"><i class="fa-solid fa-ban"></i> Cancel</button>
                    <button class="btn btn-sm btn-outline" onclick="viewBill(${job.id})"><i class="fa-solid fa-eye"></i></button>`;
        }
        return `<span class="badge badge-pending"><i class="fa-solid fa-envelope"></i> Customer</span>
                <button class="btn btn-sm btn-outline" onclick="viewBill(${job.id})"><i class="fa-solid fa-eye"></i></button>`;
    }

    if (job.status === 'approved') {
        if (isOwnJob) {
            return `<button class="btn btn-sm btn-success" onclick="startJob(${job.id})"><i class="fa-solid fa-play"></i> Start Work</button>
                    <button class="btn btn-sm btn-outline" onclick="viewBill(${job.id})"><i class="fa-solid fa-eye"></i></button>`;
        }
        return `<button class="btn btn-sm btn-outline" disabled style="opacity:0.5;cursor:not-allowed;" title="Assigned to ${job.technician_name || 'another technician'}"><i class="fa-solid fa-play"></i> Start</button>
                <button class="btn btn-sm btn-outline" onclick="viewBill(${job.id})"><i class="fa-solid fa-eye"></i></button>`;
    }

    if (job.status === 'in_progress') {
        if (isOwnJob) {
            return `<button class="btn btn-sm btn-accent" onclick="openJobDoneModal(${job.id}, '${safeOwner}', '${safeReg}', '${safeMake} ${safeModel}', ${job.total_amount || 0})"><i class="fa-solid fa-circle-check"></i> Job Done</button>
                    <button class="btn btn-sm btn-outline" onclick="viewBill(${job.id})"><i class="fa-solid fa-eye"></i></button>`;
        }
        return `<button class="btn btn-sm btn-outline" disabled style="opacity:0.5;cursor:not-allowed;" title="Assigned to ${job.technician_name || 'another technician'}"><i class="fa-solid fa-circle-check"></i> Done</button>
                <button class="btn btn-sm btn-outline" onclick="viewBill(${job.id})"><i class="fa-solid fa-eye"></i></button>`;
    }

    if (job.status === 'pending_admin_delivery') {
        const delivery = job.estimated_delivery ? formatDateTime(job.estimated_delivery) : 'Not set';
        if (isAdmin) {
            return `<button class="btn btn-sm btn-success" onclick="approveDelivery(${job.id})"><i class="fa-solid fa-check"></i> Approve Delivery</button>
                    <button class="btn btn-sm btn-danger" onclick="rejectDelivery(${job.id})"><i class="fa-solid fa-rotate-left"></i> Return</button>
                    <button class="btn btn-sm btn-outline" onclick="viewBill(${job.id})"><i class="fa-solid fa-eye"></i></button>
                    <br><small style="color:var(--text-muted);">Delivery: ${delivery}</small>`;
        }
        return `<span class="badge badge-pending"><i class="fa-solid fa-clipboard-check"></i> Awaiting Admin</span>
                <button class="btn btn-sm btn-outline" onclick="viewBill(${job.id})"><i class="fa-solid fa-eye"></i></button>
                <br><small style="color:var(--text-muted);">Delivery: ${delivery}</small>`;
    }

    if (job.status === 'ready_for_delivery') {
        const delivery = job.estimated_delivery ? formatDateTime(job.estimated_delivery) : 'Not set';
        let actions = '';
        if (isAdmin) {
            actions = `<button class="btn btn-sm btn-success" onclick="markJobCompleted(${job.id})"><i class="fa-solid fa-check-circle"></i> Mark Completed</button>`;
        } else if (isOwnJob) {
            actions = `<button class="btn btn-sm btn-accent" onclick="reopenJob(${job.id})"><i class="fa-solid fa-rotate-left"></i> Reopen</button>`;
        } else {
            actions = `<span class="badge badge-inprogress"><i class="fa-solid fa-truck-fast"></i> Ready</span>`;
        }
        return `${actions}
                <button class="btn btn-sm btn-outline" onclick="viewBill(${job.id})"><i class="fa-solid fa-eye"></i></button>
                <br><small style="color:var(--text-muted);">Delivery: ${delivery}</small>`;
    }

    if (job.status === 'completed') {
        const delivery = job.estimated_delivery ? formatDateTime(job.estimated_delivery) : '';
        return `<span class="badge badge-completed"><i class="fa-solid fa-circle-check"></i> Done</span>
                <button class="btn btn-sm btn-outline" onclick="viewBill(${job.id})"><i class="fa-solid fa-eye"></i></button>
                ${delivery ? '<br><small style="color:var(--text-muted);">' + delivery + '</small>' : ''}`;
    }

    if (job.status === 'rejected') {
        const adminNote = job.admin_note ? `<br><small style="color:var(--danger);"><i class="fa-solid fa-comment"></i> ${job.admin_note.replace(/'/g, "\\'")}</small>` : '';
        const custReason = job.rejection_reason ? `<br><small style="color:var(--danger);"><i class="fa-solid fa-user"></i> ${job.rejection_reason.replace(/'/g, "\\'")}</small>` : '';
        let resubmitBtn = '';
        if (isOwnJob && !isAdmin) {
            resubmitBtn = `<br><button class="btn btn-sm btn-accent" style="margin-top:4px;" onclick="resubmitBill(${job.id})"><i class="fa-solid fa-redo"></i> Edit & Resubmit</button>`;
        }
        return `<span class="badge badge-rejected"><i class="fa-solid fa-xmark-circle"></i> Rejected</span>
                <button class="btn btn-sm btn-outline" onclick="viewBill(${job.id})"><i class="fa-solid fa-eye"></i></button>
                ${adminNote}${custReason}${resubmitBtn}`;
    }

    if (job.status === 'cancelled') {
        return `<span class="badge badge-cancelled"><i class="fa-solid fa-ban"></i> Cancelled</span>
                <button class="btn btn-sm btn-outline" onclick="viewBill(${job.id})"><i class="fa-solid fa-eye"></i></button>`;
    }

    if (job.status === 'draft') {
        if (isOwnJob) {
            return `<button class="btn btn-sm btn-accent" onclick="editDraftBill(${job.id})"><i class="fa-solid fa-pen-to-square"></i> Edit</button>
                    <button class="btn btn-sm btn-success" onclick="submitDraftBill(${job.id})"><i class="fa-solid fa-paper-plane"></i> Submit</button>
                    <button class="btn btn-sm btn-outline" onclick="viewBill(${job.id})"><i class="fa-solid fa-eye"></i></button>`;
        }
        return `<span class="badge badge-draft"><i class="fa-solid fa-pen"></i> Draft</span>
                <button class="btn btn-sm btn-outline" onclick="viewBill(${job.id})"><i class="fa-solid fa-eye"></i></button>`;
    }

    return `<button class="btn btn-sm btn-outline" onclick="viewBill(${job.id})"><i class="fa-solid fa-eye"></i></button>`;
}

async function editDraftBill(billId) {
    const contentEl = document.getElementById('jobsContent');
    if (contentEl) contentEl.innerHTML = '<div class="loading-state"><span class="spinner"></span><p>Loading draft...</p></div>';
    try {
        const bill = await apiGet('bills/' + billId);
        const visit = await apiGet('visits/' + bill.visit_id);
        editingBillVehicleId = visit.vehicle_id;
        openBillModal(visit, bill.items || []);
    } catch (err) {
        showToast('Error: ' + (err.message || 'Failed to open draft'), 'error');
        if (typeof renderJobs === 'function') renderJobs();
    }
}

async function submitDraftBill(billId) {
    if (!confirm('Submit this draft quotation for admin review?')) return;
    const btn = document.querySelector(`button[onclick*="submitDraftBill(${billId})"]`);
    if (btn) {
        btn.innerHTML = '<span class="spinner spinner-white"></span> Submitting...';
        btn.disabled = true;
    }
    try {
        await apiPut('bills/' + billId + '/status', { status: 'pending_admin_approval' });
        showToast('Quotation submitted for admin review!', 'success');
        renderJobs();
        if (typeof renderReports === 'function') renderReports();
        if (typeof renderDashboard === 'function') renderDashboard();
    } catch (err) {
        showToast('Error: ' + (err.message || 'Failed to submit'), 'error');
        if (btn) { btn.disabled = false; renderJobs(); }
    }
}

async function startJob(billId) {
    const btn = document.querySelector(`button[onclick*="startJob(${billId})"]`);
    if (btn) {
        btn.innerHTML = '<span class="spinner spinner-white"></span> Starting...';
        btn.disabled = true;
    }
    try {
        await apiPut('bills/' + billId + '/status', { status: 'in_progress' });
        showToast('Work started!', 'success');
        renderJobs();
        if (typeof renderReports === 'function') renderReports();
        if (typeof renderDashboard === 'function') renderDashboard();
    } catch (err) {
        showToast('Error: ' + (err.message || 'Failed to start job'), 'error');
        if (btn) { btn.disabled = false; renderJobs(); }
    }
}

async function adminApproveBill(billId) {
    const btn = document.querySelector(`button[onclick*="adminApproveBill(${billId})"]`);
    if (btn) {
        btn.innerHTML = '<span class="spinner spinner-white"></span> Approving...';
        btn.disabled = true;
    }
    try {
        await apiPut('bills/' + billId + '/status', { status: 'pending_approval' });
        showToast('Bill approved and sent to customer!', 'success');
        renderJobs();
        if (typeof renderReports === 'function') renderReports();
        if (typeof renderDashboard === 'function') renderDashboard();
    } catch (err) {
        showToast('Error: ' + (err.message || 'Failed to approve'), 'error');
        if (btn) { btn.disabled = false; renderJobs(); }
    }
}

async function updateBillStatus(billId, status) {
    const btn = document.querySelector(`button[onclick*="updateBillStatus(${billId}, '${status}')"]`);
    if (btn) {
        btn.innerHTML = '<span class="spinner spinner-white"></span> Processing...';
        btn.disabled = true;
    }
    try {
        await apiPut('bills/' + billId + '/status', { status: status });
        showToast('Status updated to: ' + getStatusLabel(status), 'success');
        renderJobs();
        if (typeof renderReports === 'function') renderReports();
        if (typeof renderDashboard === 'function') renderDashboard();
    } catch (err) {
        showToast('Error: ' + (err.message || 'Failed to update status'), 'error');
        if (btn) { btn.disabled = false; renderJobs(); }
    }
}

function openRejectModal(billId) {
    const overlay = document.createElement('div');
    overlay.className = 'modal-overlay active';
    overlay.id = 'rejectModalOverlay';
    overlay.innerHTML = `
        <div class="modal" style="max-width:480px;">
            <div class="modal-header">
                <h3><i class="fa-solid fa-xmark-circle"></i> Reject Quotation</h3>
                <button class="modal-close" onclick="closeRejectModal()"><i class="fa-solid fa-xmark"></i></button>
            </div>
            <div class="modal-body">
                <p style="margin-bottom:16px;color:var(--text-muted);">Please provide a reason for rejecting this quotation. The technician will see this note and can revise the quotation.</p>
                <div class="form-group">
                    <label>Rejection Reason *</label>
                    <textarea id="rejectNote" placeholder="e.g., Prices need adjustment, Add missing parts, Incorrect labor cost..." rows="4" required style="width:100%;padding:10px;border:2px solid var(--border);border-radius:var(--radius);font-family:inherit;font-size:0.9375rem;"></textarea>
                </div>
                <div class="modal-footer" style="border:none;padding:0;margin-top:20px;">
                    <button type="button" class="btn btn-outline btn-sm" onclick="closeRejectModal()">Cancel</button>
                    <button type="button" class="btn btn-danger btn-sm" onclick="submitReject(${billId})"><i class="fa-solid fa-xmark"></i> Reject Quotation</button>
                </div>
            </div>
        </div>
    `;
    overlay.addEventListener('click', function(e) {
        if (e.target === overlay) closeRejectModal();
    });
    document.body.appendChild(overlay);
}

function closeRejectModal() {
    const overlay = document.getElementById('rejectModalOverlay');
    if (overlay) overlay.remove();
}

async function submitReject(billId) {
    const note = document.getElementById('rejectNote').value.trim();
    if (!note) {
        showToast('Please enter a rejection reason.', 'warning');
        return;
    }

    const submitBtn = document.querySelector('#rejectModalOverlay .btn-danger');
    if (submitBtn) {
        submitBtn.innerHTML = '<span class="spinner spinner-white"></span> Rejecting...';
        submitBtn.disabled = true;
    }

    try {
        await apiPut('bills/' + billId + '/status', { status: 'rejected', admin_note: note });
        closeRejectModal();
        showToast('Quotation rejected. Technician has been notified.', 'success');
        renderJobs();
        if (typeof renderReports === 'function') renderReports();
        if (typeof renderDashboard === 'function') renderDashboard();
    } catch (err) {
        showToast('Error: ' + (err.message || 'Failed to reject'), 'error');
        if (submitBtn) {
            submitBtn.innerHTML = '<i class="fa-solid fa-xmark"></i> Reject Quotation';
            submitBtn.disabled = false;
        }
    }
}

async function resubmitBill(billId) {
    if (!confirm('Edit and re-submit this quotation for admin approval?')) return;
    const btn = document.querySelector(`button[onclick*="resubmitBill(${billId})"]`);
    if (btn) {
        btn.innerHTML = '<span class="spinner spinner-white"></span> Loading...';
        btn.disabled = true;
    }
    try {
        await apiPut('bills/' + billId + '/status', { status: 'draft' });
        const bill = await apiGet('bills/' + billId);
        const visit = await apiGet('visits/' + bill.visit_id);
        editingBillVehicleId = visit.vehicle_id;
        openBillModal(visit, bill.items || []);
        showToast('Quotation opened for editing. Update items and re-submit.', 'info');
    } catch (err) {
        showToast('Error: ' + (err.message || 'Failed to open for editing'), 'error');
        if (btn) { btn.disabled = false; renderJobs(); }
    }
}

async function markJobCompleted(billId) {
    if (!confirm('Mark this job as completed? The customer has picked up the vehicle.')) return;
    const btn = document.querySelector(`button[onclick*="markJobCompleted(${billId})"]`);
    if (btn) {
        btn.innerHTML = '<span class="spinner spinner-white"></span> Completing...';
        btn.disabled = true;
    }
    try {
        await apiPut('bills/' + billId + '/status', { status: 'completed' });
        showToast('Job marked as completed!', 'success');
        renderJobs();
        if (typeof renderReports === 'function') renderReports();
        if (typeof renderDashboard === 'function') renderDashboard();
    } catch (err) {
        showToast('Error: ' + (err.message || 'Failed to mark completed'), 'error');
        if (btn) { btn.disabled = false; renderJobs(); }
    }
}

async function resendApproval(billId) {
    if (!confirm('Resend the approval email to the customer?')) return;
    const btn = document.querySelector(`button[onclick*="resendApproval(${billId})"]`);
    if (btn) {
        btn.innerHTML = '<span class="spinner spinner-white"></span> Resending...';
        btn.disabled = true;
    }
    try {
        await apiPost('bills/' + billId + '/resend');
        showToast('Approval email resent to customer!', 'success');
        renderJobs();
    } catch (err) {
        showToast('Error: ' + (err.message || 'Failed to resend approval'), 'error');
        if (btn) { btn.disabled = false; renderJobs(); }
    }
}

async function cancelPendingApproval(billId) {
    if (!confirm('Cancel this quotation? The customer will not be able to approve it.')) return;
    const btn = document.querySelector(`button[onclick*="cancelPendingApproval(${billId})"]`);
    if (btn) {
        btn.innerHTML = '<span class="spinner spinner-white"></span> Cancelling...';
        btn.disabled = true;
    }
    try {
        await apiPut('bills/' + billId + '/status', { status: 'cancelled' });
        showToast('Quotation cancelled.', 'success');
        renderJobs();
        if (typeof renderReports === 'function') renderReports();
        if (typeof renderDashboard === 'function') renderDashboard();
    } catch (err) {
        showToast('Error: ' + (err.message || 'Failed to cancel'), 'error');
        if (btn) { btn.disabled = false; renderJobs(); }
    }
}

async function reopenJob(billId) {
    if (!confirm('Reopen this job? It will be moved back to In Progress for additional work.')) return;
    const btn = document.querySelector(`button[onclick*="reopenJob(${billId})"]`);
    if (btn) {
        btn.innerHTML = '<span class="spinner spinner-white"></span> Reopening...';
        btn.disabled = true;
    }
    try {
        await apiPut('bills/' + billId + '/status', { status: 'in_progress' });
        showToast('Job reopened for additional work.', 'success');
        renderJobs();
        if (typeof renderReports === 'function') renderReports();
        if (typeof renderDashboard === 'function') renderDashboard();
    } catch (err) {
        showToast('Error: ' + (err.message || 'Failed to reopen job'), 'error');
        if (btn) { btn.disabled = false; renderJobs(); }
    }
}

async function approveDelivery(billId) {
    if (!confirm('Approve this job for delivery? The customer will be notified by email.')) return;
    const btn = document.querySelector(`button[onclick*="approveDelivery(${billId})"]`);
    if (btn) {
        btn.innerHTML = '<span class="spinner spinner-white"></span> Approving...';
        btn.disabled = true;
    }
    try {
        await apiPut('bills/' + billId + '/status', { status: 'ready_for_delivery' });
        showToast('Delivery approved! Customer has been notified by email.', 'success');
        renderJobs();
        if (typeof renderReports === 'function') renderReports();
        if (typeof renderDashboard === 'function') renderDashboard();
    } catch (err) {
        showToast('Error: ' + (err.message || 'Failed to approve delivery'), 'error');
        if (btn) { btn.disabled = false; renderJobs(); }
    }
}

async function rejectDelivery(billId) {
    if (!confirm('Return this job to the technician for additional work?')) return;
    const btn = document.querySelector(`button[onclick*="rejectDelivery(${billId})"]`);
    if (btn) {
        btn.innerHTML = '<span class="spinner spinner-white"></span> Returning...';
        btn.disabled = true;
    }
    try {
        await apiPut('bills/' + billId + '/status', { status: 'in_progress' });
        showToast('Job returned to technician for rework.', 'success');
        renderJobs();
        if (typeof renderReports === 'function') renderReports();
        if (typeof renderDashboard === 'function') renderDashboard();
    } catch (err) {
        showToast('Error: ' + (err.message || 'Failed to return job'), 'error');
        if (btn) { btn.disabled = false; renderJobs(); }
    }
}

function openJobDoneModal(billId, ownerName, regNumber, vehicleInfo, totalAmount) {
    const now = new Date();
    const localStr = now.toISOString().slice(0, 10);

    const overlay = document.createElement('div');
    overlay.className = 'modal-overlay active';
    overlay.id = 'jobDoneModalOverlay';
    overlay.innerHTML = `
        <div class="modal" style="max-width:500px;">
            <div class="modal-header">
                <h3><i class="fa-solid fa-circle-check"></i> Mark Job as Ready</h3>
                <button class="modal-close" onclick="closeJobDoneModal()"><i class="fa-solid fa-xmark"></i></button>
            </div>
            <div class="modal-body">
                <div style="margin-bottom:16px;background:#f8fafc;padding:14px 16px;border-radius:8px;">
                    <p><strong>Bill #${billId}</strong> — ${vehicleInfo} (${regNumber})</p>
                    <p><strong>Owner:</strong> ${ownerName}</p>
                    <p><strong>Total:</strong> ${formatCurrency(totalAmount)}</p>
                </div>
                <hr>
                <div class="form-group">
                    <label><i class="fa-solid fa-calendar-check"></i> Estimated Delivery Date *</label>
                    <input type="date" id="jobDoneDeliveryTime" value="${localStr}" required style="padding:10px;border:2px solid var(--border);border-radius:var(--radius);width:100%;font-family:inherit;font-size:0.9375rem;">
                </div>
                <p style="font-size:0.85rem;color:var(--text-muted);margin-top:8px;"><i class="fa-solid fa-info-circle"></i> Vehicle will be marked as 'Ready for Delivery'. Customer will be notified by email.</p>
                <div class="modal-footer" style="border:none;padding:0;margin-top:20px;">
                    <button type="button" class="btn btn-outline btn-sm" onclick="closeJobDoneModal()">Cancel</button>
                    <button type="button" class="btn btn-accent btn-sm" onclick="submitJobDone(${billId})"><i class="fa-solid fa-paper-plane"></i> Submit & Notify Customer</button>
                </div>
            </div>
        </div>
    `;
    overlay.addEventListener('click', function(e) {
        if (e.target === overlay) closeJobDoneModal();
    });
    document.body.appendChild(overlay);
}

function closeJobDoneModal() {
    const overlay = document.getElementById('jobDoneModalOverlay');
    if (overlay) overlay.remove();
}

async function submitJobDone(billId) {
    const deliveryTime = document.getElementById('jobDoneDeliveryTime').value;

    if (!deliveryTime) {
        showToast('Please select a delivery date.', 'warning');
        return;
    }

    const submitBtn = document.querySelector('#jobDoneModalOverlay .btn-accent');
    if (submitBtn) {
        submitBtn.innerHTML = '<span class="spinner spinner-white"></span> Processing...';
        submitBtn.disabled = true;
    }

    try {
        const mysqlFormat = deliveryTime + ' 00:00:00';

        await apiPost('bills/' + billId + '/job-done', {
            estimated_delivery: mysqlFormat
        });

        closeJobDoneModal();
        showToast('Job marked as ready for delivery! Customer notified by email.', 'success');
        renderJobs();
        if (typeof renderReports === 'function') renderReports();
        if (typeof renderDashboard === 'function') renderDashboard();
    } catch (err) {
        showToast('Error: ' + (err.message || 'Failed to mark job as ready'), 'error');
        if (submitBtn) {
            submitBtn.innerHTML = '<i class="fa-solid fa-paper-plane"></i> Submit & Notify Customer';
            submitBtn.disabled = false;
        }
    }
}
