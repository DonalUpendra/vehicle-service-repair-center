/* ==================== REPORTS ==================== */
async function renderReports() {
    showLoading('reportCards', 'Loading reports...');
    showLoading('allBillsTable', 'Loading bills...');

    try {
        const [stats, bills] = await Promise.all([
            apiGet('reports/today'),
            apiGet('bills'),
        ]);

        document.getElementById('reportCards').innerHTML = `
            <div class="stat-card card-green">
                <div class="card-icon"><i class="fa-solid fa-sack-dollar"></i></div>
                <div class="card-content">
                    <div class="card-value">${formatCurrency(stats.totalRevenue || 0)}</div>
                    <div class="card-label">Total Revenue</div>
                </div>
            </div>
            <div class="stat-card card-green">
                <div class="card-icon"><i class="fa-solid fa-chart-line"></i></div>
                <div class="card-content">
                    <div class="card-value">${formatCurrency(stats.totalProfit || 0)}</div>
                    <div class="card-label">Total Profit</div>
                </div>
            </div>
            <div class="stat-card card-blue">
                <div class="card-icon"><i class="fa-solid fa-file-invoice"></i></div>
                <div class="card-content">
                    <div class="card-value">${bills.length}</div>
                    <div class="card-label">Total Bills</div>
                </div>
            </div>
            <div class="stat-card card-orange">
                <div class="card-icon"><i class="fa-solid fa-hourglass-half"></i></div>
                <div class="card-content">
                    <div class="card-value">${stats.pendingApprovals || 0}</div>
                    <div class="card-label">Pending Approvals</div>
                </div>
            </div>
            <div class="stat-card card-green">
                <div class="card-icon"><i class="fa-solid fa-circle-check"></i></div>
                <div class="card-content">
                    <div class="card-value">${stats.completedToday || 0}</div>
                    <div class="card-label">Completed Today</div>
                </div>
            </div>
        `;

        let tableHTML = '<table class="striped"><thead><tr><th>Bill ID</th><th>Vehicle</th><th>Owner</th><th>Total (LKR)</th><th>Status</th><th>Date</th><th>Action</th></tr></thead><tbody>';

        if (bills.length === 0) {
            tableHTML += `<tr><td colspan="7"><div class="empty-state"><i class="fa-solid fa-receipt"></i><h4>No Bills Yet</h4><p>Bills and quotations will appear here once created.</p></div></td></tr>`;
        } else {
            bills.forEach(bill => {
                let actionHTML = '<div class="btn-group">';
                if (bill.status === 'pending_admin_approval' && currentUser?.role === 'admin') {
                    actionHTML += `<button class="btn btn-sm btn-success" onclick="adminApproveBill(${bill.id})"><i class="fa-solid fa-check"></i> Approve</button>`;
                    actionHTML += `<button class="btn btn-sm btn-danger" onclick="updateBillStatus(${bill.id}, 'rejected')"><i class="fa-solid fa-xmark"></i> Reject</button>`;
                }
                if (bill.status === 'pending_approval' && currentUser?.role === 'admin') {
                    actionHTML += `<button class="btn btn-sm btn-outline" onclick="simulateResendEmail(${bill.id})"><i class="fa-solid fa-paper-plane"></i> Resend</button>`;
                }
                if (bill.status === 'approved') {
                    actionHTML += `<button class="btn btn-sm btn-success" onclick="updateBillStatus(${bill.id}, 'in_progress')"><i class="fa-solid fa-play"></i> Start</button>`;
                }
                if (bill.status === 'in_progress') {
                    actionHTML += `<button class="btn btn-sm btn-success" onclick="updateBillStatus(${bill.id}, 'completed')"><i class="fa-solid fa-circle-check"></i> Complete</button>`;
                }
                actionHTML += '</div>';

                tableHTML += `
                    <tr>
                        <td><strong>#${bill.id}</strong></td>
                        <td>${bill.registration_number || 'N/A'}</td>
                        <td>${bill.owner_name || 'N/A'}</td>
                        <td><strong>${formatCurrency(bill.total_amount)}</strong></td>
                        <td>${getStatusBadge(bill.status)}</td>
                        <td>${formatDate(bill.created_at)}</td>
                        <td>${actionHTML}</td>
                    </tr>`;
            });
        }

        tableHTML += '</tbody></table>';
        document.getElementById('allBillsTable').innerHTML = tableHTML;
    } catch (err) {
        showError('reportCards', err.message || 'Failed to load reports');
        showToast('Failed to load reports: ' + (err.message || 'Unknown error'), 'error');
    }
}

async function updateBillStatus(billId, newStatus) {
    try {
        await apiPut('bills/' + billId + '/status', { status: newStatus });

        if (newStatus === 'pending_approval') {
            showToast(`Bill #${billId} approved! Email sent to customer.`, 'success');
        } else if (newStatus === 'completed') {
            showToast(`Bill #${billId} marked as Completed! Email sent to customer.`, 'success');
        } else {
            showToast(`Bill #${billId} status updated to: ${newStatus.replace(/_/g, ' ')}`, 'info');
        }

        renderReports();
        renderDashboard();
    } catch (err) {
        showToast('Error: ' + (err.message || 'Failed to update status'), 'error');
    }
}

async function simulateResendEmail(billId) {
    try {
        await apiPost('bills/' + billId + '/resend');
        showToast(`Approval email resent for Bill #${billId}.`, 'info');
        renderReports();
    } catch (err) {
        showToast('Error: ' + (err.message || 'Failed to resend email'), 'error');
    }
}

async function adminApproveBill(billId) {
    try {
        await updateBillStatus(billId, 'pending_approval');
        if (typeof renderJobs === 'function') renderJobs();
    } catch (err) {
        showToast('Error: ' + (err.message || 'Failed to approve bill'), 'error');
    }
}
