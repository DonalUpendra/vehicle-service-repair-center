/* ==================== JOB MANAGEMENT ==================== */
async function renderJobs() {
    showLoading('jobsTable', 'Loading jobs...');

    try {
        const jobs = await apiGet('jobs');

        if (jobs.length === 0) {
            document.getElementById('jobsTable').innerHTML = '<div class="empty-state"><i class="fa-solid fa-briefcase"></i><h4>No Active Jobs</h4><p>Approved jobs will appear here for processing.</p></div>';
            return;
        }

        const isAdmin = currentUser && currentUser.role === 'admin';
        let html = '<table class="striped"><thead><tr><th>Bill ID</th><th>Vehicle</th><th>Owner</th><th>Technician</th><th>Total (LKR)</th><th>Commission</th><th>Status</th><th>Date</th><th>Action</th></tr></thead><tbody>';

        jobs.forEach(job => {
            const isOwnJob = isAdmin || (currentUser && job.technician_id === currentUser.id);
            let actionHTML = '';
            const commissionAmt = job.commission_amount || 0;
            const commissionStr = job.commission_percentage > 0
                ? `<small>${formatCurrency(commissionAmt)}<br><span style="color:var(--text-muted);font-size:0.75rem;">(${job.commission_percentage}%)</span></small>`
                : '<small style="color:var(--text-muted);">-</small>';

            if (job.status === 'pending_admin_approval') {
                if (isAdmin) {
                    actionHTML = `<button class="btn btn-sm btn-success" onclick="adminApproveBill(${job.id})"><i class="fa-solid fa-check"></i> Approve</button>
                                  <button class="btn btn-sm btn-danger" onclick="updateBillStatus(${job.id}, 'rejected')"><i class="fa-solid fa-xmark"></i> Reject</button>`;
                } else {
                    actionHTML = `<span class="badge badge-pending"><i class="fa-solid fa-user-shield"></i> Awaiting admin</span>`;
                }
            } else if (job.status === 'approved') {
                actionHTML = isOwnJob
                    ? `<button class="btn btn-sm btn-success" onclick="startJob(${job.id})"><i class="fa-solid fa-play"></i> Start Work</button>`
                    : `<button class="btn btn-sm btn-outline" disabled style="opacity:0.5;cursor:not-allowed;" title="Assigned to ${job.technician_name || 'another technician'} — Customer: ${job.owner_name || 'N/A'}"><i class="fa-solid fa-play"></i> Start Work</button>`;
            } else if (job.status === 'in_progress') {
                actionHTML = isOwnJob
                    ? `<button class="btn btn-sm btn-accent" onclick="openJobDoneModal(${job.id}, '${(job.owner_name || 'Customer').replace(/'/g, "\\'")}', '${(job.registration_number || '').replace(/'/g, "\\'")}', '${(job.make || '').replace(/'/g, "\\'")} ${(job.model || '').replace(/'/g, "\\'")}', ${job.total_amount || 0})"><i class="fa-solid fa-circle-check"></i> Job Done</button>`
                    : `<button class="btn btn-sm btn-outline" disabled style="opacity:0.5;cursor:not-allowed;" title="Assigned to ${job.technician_name || 'another technician'} — Customer: ${job.owner_name || 'N/A'}"><i class="fa-solid fa-circle-check"></i> Job Done</button>`;
            } else if (job.status === 'completed') {
                const delivery = job.estimated_delivery ? formatDateTime(job.estimated_delivery) : 'Not set';
                actionHTML = `<span class="badge badge-completed"><i class="fa-solid fa-circle-check"></i> Done</span><br><small style="color:var(--text-muted);">Delivery: ${delivery}</small>`;
            } else if (job.status === 'rejected') {
                actionHTML = `<span class="badge badge-rejected"><i class="fa-solid fa-xmark-circle"></i> Rejected</span>`;
            }

            html += `
                <tr>
                    <td><strong>#${job.id}</strong></td>
                    <td>${job.registration_number || 'N/A'} — ${job.make || ''} ${job.model || ''}</td>
                    <td>${job.owner_name || 'N/A'}</td>
                    <td>${job.technician_name || 'N/A'}</td>
                    <td><strong>${formatCurrency(job.total_amount)}</strong></td>
                    <td>${commissionStr}</td>
                    <td>${getStatusBadge(job.status)}</td>
                    <td>${formatDate(job.created_at)}</td>
                    <td>${actionHTML}</td>
                </tr>`;
        });

        html += '</tbody></table>';
        document.getElementById('jobsTable').innerHTML = html;
    } catch (err) {
        showError('jobsTable', err.message || 'Failed to load jobs');
        showToast('Failed to load jobs: ' + (err.message || 'Unknown error'), 'error');
    }
}

async function startJob(billId) {
    try {
        await apiPut('bills/' + billId + '/status', { status: 'in_progress' });
        showToast(`Bill #${billId} — Work started!`, 'success');
        renderJobs();
        if (typeof renderReports === 'function') renderReports();
        if (typeof renderDashboard === 'function') renderDashboard();
    } catch (err) {
        showToast('Error: ' + (err.message || 'Failed to start job'), 'error');
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
                <h3><i class="fa-solid fa-circle-check"></i> Mark Job as Done</h3>
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
                    <label><i class="fa-solid fa-calendar-check"></i> Vehicle Ready for Collection *</label>
                    <input type="date" id="jobDoneDeliveryTime" value="${localStr}" required style="padding:10px;border:2px solid var(--border);border-radius:var(--radius);width:100%;font-family:inherit;font-size:0.9375rem;">
                </div>
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
        showToast('Job marked as completed! Email sent to customer.', 'success');
        renderJobs();
        if (typeof renderReports === 'function') renderReports();
        if (typeof renderDashboard === 'function') renderDashboard();
    } catch (err) {
        showToast('Error: ' + (err.message || 'Failed to mark job as done'), 'error');
        if (submitBtn) {
            submitBtn.innerHTML = '<i class="fa-solid fa-paper-plane"></i> Submit & Notify Customer';
            submitBtn.disabled = false;
        }
    }
}
