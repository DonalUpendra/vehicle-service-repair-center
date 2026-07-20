/* ==================== REPORTS ==================== */
let cachedReportData = null;

async function renderReports() {
    showLoading('reportSummaryCards', 'Loading reports...');
    document.getElementById('statusBreakdownTable').innerHTML = '<div class="loading-state"><span class="spinner"></span><p>Loading...</p></div>';
    document.getElementById('detailedBillsTable').innerHTML = '<div class="loading-state"><span class="spinner"></span><p>Loading...</p></div>';
    document.getElementById('technicianPerformanceTable').innerHTML = '<div class="loading-state"><span class="spinner"></span><p>Loading...</p></div>';

    try {
        cachedReportData = await apiGet('reports/full');
    } catch (err) {
        showError('reportSummaryCards', err.message || 'Failed to load reports');
        showToast('Failed to load reports: ' + (err.message || 'Unknown error'), 'error');
        return;
    }

    const data = cachedReportData;

    renderSummaryCards(data.summary);
    renderStatusBreakdown(data.statusBreakdown);
    renderDetailedBills(data.bills);
    renderTechnicianPerformance(data.technicians);

    document.getElementById('billsCount').textContent = `Total: ${data.bills.length} bills`;
}

function switchReportTab(tabName) {
    document.querySelectorAll('.report-tab').forEach(t => t.classList.remove('active'));
    document.querySelectorAll('.report-tab-content').forEach(c => c.classList.remove('active'));

    const tabBtn = document.querySelector(`.report-tab[onclick="switchReportTab('${tabName}')"]`);
    if (tabBtn) tabBtn.classList.add('active');

    const content = document.getElementById('reportTab' + tabName.charAt(0).toUpperCase() + tabName.slice(1));
    if (content) content.classList.add('active');
}

function renderSummaryCards(summary) {
    const profitMargin = summary.totalRevenue > 0
        ? ((summary.totalProfit / summary.totalRevenue) * 100).toFixed(1)
        : '0.0';

    document.getElementById('reportSummaryCards').innerHTML = `
        <div class="stat-card card-green">
            <div class="card-icon"><i class="fa-solid fa-sack-dollar"></i></div>
            <div class="card-content">
                <div class="card-value">${formatCurrency(summary.totalRevenue || 0)}</div>
                <div class="card-label">Total Revenue</div>
            </div>
        </div>
        <div class="stat-card card-green">
            <div class="card-icon"><i class="fa-solid fa-chart-line"></i></div>
            <div class="card-content">
                <div class="card-value">${formatCurrency(summary.totalProfit || 0)}</div>
                <div class="card-label">Total Profit (${profitMargin}% margin)</div>
            </div>
        </div>
        <div class="stat-card card-blue">
            <div class="card-icon"><i class="fa-solid fa-file-invoice"></i></div>
            <div class="card-content">
                <div class="card-value">${summary.totalBills || 0}</div>
                <div class="card-label">Total Bills</div>
            </div>
        </div>
        <div class="stat-card card-orange">
            <div class="card-icon"><i class="fa-solid fa-hourglass-half"></i></div>
            <div class="card-content">
                <div class="card-value">${summary.pendingApprovals || 0}</div>
                <div class="card-label">Pending Approvals</div>
            </div>
        </div>
        <div class="stat-card card-green">
            <div class="card-icon"><i class="fa-solid fa-circle-check"></i></div>
            <div class="card-content">
                <div class="card-value">${summary.completedToday || 0}</div>
                <div class="card-label">Completed Today</div>
            </div>
        </div>
        <div class="stat-card card-blue">
            <div class="card-icon"><i class="fa-solid fa-gears"></i></div>
            <div class="card-content">
                <div class="card-value">${summary.activeJobs || 0}</div>
                <div class="card-label">Active Jobs</div>
            </div>
        </div>
        <div class="stat-card card-blue">
            <div class="card-icon"><i class="fa-solid fa-car"></i></div>
            <div class="card-content">
                <div class="card-value">${summary.totalVehicles || 0}</div>
                <div class="card-label">Total Vehicles</div>
            </div>
        </div>
        <div class="stat-card card-orange">
            <div class="card-icon"><i class="fa-solid fa-calendar-check"></i></div>
            <div class="card-content">
                <div class="card-value">${summary.totalVisits || 0}</div>
                <div class="card-label">Total Visits</div>
            </div>
        </div>
    `;
}

function renderStatusBreakdown(breakdown) {
    const total = breakdown.reduce((sum, s) => sum + s.count, 0);

    let html = '<div class="table-responsive"><table class="striped"><thead><tr><th>Status</th><th>Count</th><th>% of Total</th><th>Revenue (LKR)</th><th>Distribution</th></tr></thead><tbody>';

    if (total === 0) {
        html += '<tr><td colspan="5"><div class="empty-state"><i class="fa-solid fa-chart-simple"></i><h4>No Bills Yet</h4><p>Once bills are created, status distribution will appear here.</p></div></td></tr>';
    } else {
        breakdown.forEach(s => {
            const pct = ((s.count / total) * 100).toFixed(1);
            html += `
                <tr>
                    <td>${getStatusBadge(s.status)}</td>
                    <td><strong>${s.count}</strong></td>
                    <td>${pct}%</td>
                    <td><strong>${formatCurrency(s.revenue)}</strong></td>
                    <td>
                        <div class="progress-bar-wrapper">
                            <div class="progress-bar-fill" style="width:${pct}%;background:${statusColor(s.status)}"></div>
                            <span class="progress-bar-label">${pct}%</span>
                        </div>
                    </td>
                </tr>`;
        });
    }

    html += '</tbody></table></div>';
    document.getElementById('statusBreakdownTable').innerHTML = html;
}

function renderDetailedBills(bills) {
    let html = '<div class="table-responsive"><table class="striped"><thead><tr><th>Bill #</th><th>Vehicle</th><th>Owner</th><th>Phone</th><th>Technician</th><th>Items</th><th>Amount</th><th>Profit</th><th>Status</th><th>Created</th><th>Action</th></tr></thead><tbody>';

    if (bills.length === 0) {
        html += '<tr><td colspan="11"><div class="empty-state"><i class="fa-solid fa-receipt"></i><h4>No Bills Yet</h4><p>Bills and quotations will appear here once created.</p></div></td></tr>';
    } else {
        bills.forEach(bill => {
            const profit = bill.bill_profit || 0;
            const profitClass = profit >= 0 ? 'text-success' : 'text-danger';

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
            actionHTML += `<button class="btn btn-sm btn-outline" onclick="viewBillDetails(${bill.id})"><i class="fa-solid fa-eye"></i></button>`;
            actionHTML += '</div>';

            html += `
                <tr>
                    <td><strong>#${bill.id}</strong></td>
                    <td>${bill.registration_number || 'N/A'}<br><small style="color:var(--text-muted)">${bill.make || ''} ${bill.model || ''}</small></td>
                    <td>${bill.owner_name || 'N/A'}<br><small style="color:var(--text-muted)">${bill.owner_email || ''}</small></td>
                    <td>${bill.owner_phone || '-'}</td>
                    <td>${bill.technician_name || 'N/A'}</td>
                    <td><strong>${bill.item_count || 0}</strong></td>
                    <td><strong>${formatCurrency(bill.total_amount)}</strong></td>
                    <td><strong class="${profitClass}">${formatCurrency(profit)}</strong></td>
                    <td>${getStatusBadge(bill.status)}</td>
                    <td>${formatDate(bill.created_at)}</td>
                    <td>${actionHTML}</td>
                </tr>`;
        });
    }

    html += '</tbody></table></div>';
    document.getElementById('detailedBillsTable').innerHTML = html;
    document.getElementById('billsCount').textContent = `Total: ${bills.length} bills`;
}

function renderTechnicianPerformance(technicians) {
    let html = '<div class="table-responsive"><table class="striped"><thead><tr><th>Technician</th><th>Commission %</th><th>Total Bills</th><th>Active Jobs</th><th>Completed</th><th>Revenue</th><th>Profit</th><th>Commission Earned</th></tr></thead><tbody>';

    if (technicians.length === 0) {
        html += '<tr><td colspan="8"><div class="empty-state"><i class="fa-solid fa-user-gear"></i><h4>No Technicians</h4><p>Add technicians to see performance metrics.</p></div></td></tr>';
    } else {
        technicians.forEach(t => {
            const profitClass = (t.total_profit || 0) >= 0 ? 'text-success' : 'text-danger';
            html += `
                <tr>
                    <td><strong>${t.name}</strong></td>
                    <td>${t.commission_percentage}%</td>
                    <td><strong>${t.total_bills}</strong></td>
                    <td>${t.active_jobs}</td>
                    <td>${t.completed_count}</td>
                    <td>${formatCurrency(t.completed_revenue)}</td>
                    <td><strong class="${profitClass}">${formatCurrency(t.total_profit || 0)}</strong></td>
                    <td>${formatCurrency(t.commission_earned || 0)}</td>
                </tr>`;
        });
    }

    html += '</tbody></table></div>';
    document.getElementById('technicianPerformanceTable').innerHTML = html;
}

function statusColor(status) {
    const colors = {
        'completed': 'var(--success)',
        'pending_admin_approval': 'var(--warning)',
        'pending_approval': 'var(--warning)',
        'approved': 'var(--info)',
        'in_progress': 'var(--info)',
        'pending_admin_delivery': 'var(--warning)',
        'ready_for_delivery': '#8b5cf6',
        'rejected': 'var(--danger)',
        'cancelled': 'var(--danger)',
        'draft': 'var(--text-muted)',
    };
    return colors[status] || 'var(--text-muted)';
}

/* ---- Bill Actions ---- */
async function updateBillStatus(billId, newStatus) {
    const btn = document.querySelector(`button[onclick*="updateBillStatus(${billId}, '${newStatus}')"]`);
    if (btn) {
        btn.innerHTML = '<span class="spinner spinner-white"></span> Processing...';
        btn.disabled = true;
    }
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
        if (typeof renderDashboard === 'function') renderDashboard();
    } catch (err) {
        showToast('Error: ' + (err.message || 'Failed to update status'), 'error');
        if (btn) {
            btn.disabled = false;
            renderReports();
        }
    }
}

async function simulateResendEmail(billId) {
    const btn = document.querySelector(`button[onclick*="simulateResendEmail(${billId})"]`);
    if (btn) {
        btn.innerHTML = '<span class="spinner spinner-white"></span> Resending...';
        btn.disabled = true;
    }
    try {
        await apiPost('bills/' + billId + '/resend');
        showToast(`Approval email resent for Bill #${billId}.`, 'info');
        renderReports();
    } catch (err) {
        showToast('Error: ' + (err.message || 'Failed to resend email'), 'error');
        if (btn) {
            btn.disabled = false;
            renderReports();
        }
    }
}

async function adminApproveBill(billId) {
    const btn = document.querySelector(`button[onclick*="adminApproveBill(${billId})"]`);
    if (btn) {
        btn.innerHTML = '<span class="spinner spinner-white"></span> Approving...';
        btn.disabled = true;
    }
    try {
        await updateBillStatus(billId, 'pending_approval');
        if (typeof renderJobs === 'function') renderJobs();
    } catch (err) {
        showToast('Error: ' + (err.message || 'Failed to approve bill'), 'error');
        if (btn) {
            btn.disabled = false;
            renderReports();
        }
    }
}

async function viewBillDetails(billId) {
    try {
        const bill = await apiGet('bills/' + billId);
        let itemsHtml = '';
        let totalCost = 0;
        if (bill.items && bill.items.length > 0) {
            itemsHtml = '<table class="striped" style="margin-top:12px"><thead><tr><th>Product</th><th>Qty</th><th>Unit Price</th><th>Cost</th><th>Total</th></tr></thead><tbody>';
            bill.items.forEach(item => {
                totalCost += parseFloat(item.line_cost || 0);
                itemsHtml += `<tr>
                    <td>${item.product_name || 'Item #' + item.product_id}</td>
                    <td>${item.quantity}</td>
                    <td>${formatCurrency(item.unit_price)}</td>
                    <td>${formatCurrency(item.buy_price || 0)}</td>
                    <td><strong>${formatCurrency(item.line_total)}</strong></td>
                </tr>`;
            });
            itemsHtml += '</tbody></table>';
        }

        const profit = bill.total_amount - totalCost;
        const modalHtml = `
            <div class="modal-overlay active" id="billViewOverlay" onclick="if(event.target===this)this.remove()">
                <div class="modal" style="max-width:700px">
                    <div class="modal-header">
                        <h3><i class="fa-solid fa-file-invoice"></i> Bill #${bill.id} Details</h3>
                        <button class="modal-close" onclick="document.getElementById('billViewOverlay').remove()">&times;</button>
                    </div>
                    <div class="modal-body">
                        <div class="info-card">
                            <div class="info-row">
                                <div class="info-item"><div class="info-label">Vehicle</div><div class="info-value">${bill.registration_number} (${bill.make} ${bill.model})</div></div>
                                <div class="info-item"><div class="info-label">Owner</div><div class="info-value">${bill.owner_name}</div></div>
                                <div class="info-item"><div class="info-label">Status</div><div class="info-value">${getStatusBadge(bill.status)}</div></div>
                                <div class="info-item"><div class="info-label">Technician</div><div class="info-value">${bill.technician_name}</div></div>
                            </div>
                            <div class="info-row" style="margin-top:12px">
                                <div class="info-item"><div class="info-label">Total Amount</div><div class="info-value">${formatCurrency(bill.total_amount)}</div></div>
                                <div class="info-item"><div class="info-label">Item Cost</div><div class="info-value">${formatCurrency(totalCost)}</div></div>
                                <div class="info-item"><div class="info-label">Profit</div><div class="info-value" style="color:${profit >= 0 ? 'var(--success)' : 'var(--danger)'}">${formatCurrency(profit)}</div></div>
                                <div class="info-item"><div class="info-label">Date</div><div class="info-value">${formatDateTime(bill.created_at)}</div></div>
                            </div>
                            ${bill.admin_note ? `<div class="info-row" style="margin-top:12px"><div class="info-item"><div class="info-label">Admin Note</div><div class="info-value" style="color:var(--danger)">${bill.admin_note}</div></div></div>` : ''}
                        </div>
                        ${itemsHtml ? '<h4 style="margin-bottom:8px"><i class="fa-solid fa-list"></i> Bill Items</h4>' + itemsHtml : '<p class="text-muted">No items on this bill.</p>'}
                    </div>
                </div>
            </div>`;
        document.body.insertAdjacentHTML('beforeend', modalHtml);
    } catch (err) {
        showToast('Failed to load bill details: ' + (err.message || 'Error'), 'error');
    }
}
