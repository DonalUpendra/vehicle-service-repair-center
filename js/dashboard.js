/* ==================== DASHBOARD ==================== */
async function renderDashboard() {
    showLoading('dashboardCards', 'Loading dashboard...');
    showLoading('recentCheckinsTable', 'Loading visits...');

    try {
        const [stats, visits] = await Promise.all([
            apiGet('reports/today'),
            apiGet('reports/dashboard?limit=8'),
        ]);

        const isAdmin = currentUser && currentUser.role === 'admin';
        let staleCount = 0;
        if (isAdmin) {
            try {
                const stale = await apiGet('visits/stale');
                staleCount = stale.count || 0;
            } catch (e) {}
        }

        document.getElementById('dashboardCards').innerHTML = `
            <div class="stat-card card-blue">
                <div class="card-icon"><i class="fa-solid fa-car"></i></div>
                <div class="card-content">
                    <div class="card-value">${stats.totalVehicles || 0}</div>
                    <div class="card-label">Total Vehicles</div>
                </div>
            </div>
            <div class="stat-card card-orange">
                <div class="card-icon"><i class="fa-solid fa-calendar-day"></i></div>
                <div class="card-content">
                    <div class="card-value">${stats.todayCheckins || 0}</div>
                    <div class="card-label">Today's Check-Ins</div>
                </div>
            </div>
            <div class="stat-card card-red">
                <div class="card-icon"><i class="fa-solid fa-clock"></i></div>
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
            ${staleCount > 0 ? `
            <div class="stat-card card-red" style="cursor:pointer;" onclick="navigateTo('jobs')">
                <div class="card-icon"><i class="fa-solid fa-triangle-exclamation"></i></div>
                <div class="card-content">
                    <div class="card-value">${staleCount}</div>
                    <div class="card-label">Stale Visits (>7d)</div>
                </div>
            </div>
            ` : ''}
            ${isAdmin ? `
            <div class="stat-card card-blue">
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
            ` : ''}
        `;

        let tableHTML = '<div class="table-responsive"><table class="striped"><thead><tr><th>Visit ID</th><th>Vehicle</th><th>Owner</th><th>Date</th><th>Status</th><th>Action</th></tr></thead><tbody>';

        if (visits.length === 0) {
            tableHTML += `<tr><td colspan="6"><div class="empty-state"><i class="fa-solid fa-inbox"></i><h4>No Visits Yet</h4><p>Vehicle check-ins will appear here.</p></div></td></tr>`;
        } else {
            visits.forEach(v => {
                const statusBadge = v.bill_status ? getStatusBadge(v.bill_status) : getStatusBadge(v.status);
                tableHTML += `
                    <tr>
                        <td><strong>#${v.id}</strong></td>
                        <td>${v.registration_number || 'N/A'}</td>
                        <td>${v.owner_name || 'N/A'}</td>
                        <td>${formatDate(v.check_in_date)}</td>
                        <td>${statusBadge}</td>
                        <td>
                            <div class="btn-group">
                                ${v.bill_id ? `<button class="btn btn-sm btn-outline" onclick="viewBill(${v.bill_id})"><i class="fa-solid fa-eye"></i> View Bill</button>`
                                  : `<button class="btn btn-sm btn-accent" onclick="startBillForVisit(${v.id})"><i class="fa-solid fa-plus"></i> Create Bill</button>`}
                            </div>
                        </td>
                    </tr>`;
            });
        }
        tableHTML += '</tbody></table></div>';
        document.getElementById('recentCheckinsTable').innerHTML = tableHTML;
    } catch (err) {
        showError('dashboardCards', (err.message || 'Failed to load dashboard'));
        showToast('Failed to load dashboard: ' + (err.message || 'Unknown error'), 'error');
    }
}

function startBillForVisit(visitId) {
    openBillModalForVisit(visitId);
}

function closeViewBillModal() {
    closeModal('viewBillModalOverlay');
}

async function viewBill(billId) {
    try {
        const bill = await apiGet('bills/' + billId);

        let itemsHTML = '';
        (bill.items || []).forEach(item => {
            itemsHTML += `
                <tr>
                    <td>${item.product_name}</td>
                    <td>${item.quantity}</td>
                    <td>${formatCurrency(item.unit_price)}</td>
                    <td style="text-align:right;">${formatCurrency(item.line_total)}</td>
                </tr>`;
        });

        const deliveryHTML = bill.estimated_delivery
            ? `<p style="margin-top:4px;"><strong>Estimated Delivery:</strong> ${formatDate(bill.estimated_delivery)}</p>`
            : '';

        document.getElementById('viewBillModalBody').innerHTML = `
            <div style="margin-bottom:20px;">
                <div style="display:flex;justify-content:space-between;align-items:flex-start;flex-wrap:wrap;gap:12px;">
                    <div>
                        <h3 style="margin:0 0 4px;font-size:1.1rem;">Bill #${bill.id}</h3>
                        <p style="color:var(--text-secondary);font-size:0.875rem;margin:0;">
                            ${formatDate(bill.created_at)} &nbsp;|&nbsp; ${getStatusBadge(bill.status)}
                        </p>
                        ${deliveryHTML}
                    </div>
                </div>
            </div>
            <hr>
            <div class="form-row" style="margin-top:16px;margin-bottom:16px;">
                <div style="flex:1;">
                    <p style="margin:0 0 2px;color:var(--text-secondary);font-size:0.75rem;text-transform:uppercase;letter-spacing:0.5px;">Vehicle</p>
                    <p style="margin:0;font-weight:600;">${bill.registration_number || 'N/A'}</p>
                    <p style="margin:0;color:var(--text-secondary);font-size:0.875rem;">${bill.make || ''} ${bill.model || ''}</p>
                </div>
                <div style="flex:1;">
                    <p style="margin:0 0 2px;color:var(--text-secondary);font-size:0.75rem;text-transform:uppercase;letter-spacing:0.5px;">Customer</p>
                    <p style="margin:0;font-weight:600;">${bill.owner_name || 'N/A'}</p>
                    <p style="margin:0;color:var(--text-secondary);font-size:0.875rem;">${bill.owner_email || ''}</p>
                </div>
                <div style="flex:1;">
                    <p style="margin:0 0 2px;color:var(--text-secondary);font-size:0.75rem;text-transform:uppercase;letter-spacing:0.5px;">Technician</p>
                    <p style="margin:0;font-weight:600;">${bill.technician_name || 'N/A'}</p>
                </div>
            </div>
            <hr>
            <h4 style="margin:16px 0 12px;display:flex;align-items:center;gap:8px;"><i class="fa-solid fa-list-ol"></i> Items</h4>
            <div class="table-responsive"><table class="striped">
                <thead><tr><th>Item</th><th>Qty</th><th>Unit Price</th><th style="text-align:right;">Total</th></tr></thead>
                <tbody>${itemsHTML || '<tr><td colspan="4" style="text-align:center;color:var(--text-muted);">No items</td></tr>'}</tbody>
            </table></div>
            <div style="text-align:right;margin-top:16px;font-size:1.3rem;font-weight:700;color:var(--primary);">Grand Total: ${formatCurrency(bill.total_amount)}</div>
            <div class="modal-footer" style="border:none;padding:0;margin-top:20px;">
                <button type="button" class="btn btn-outline btn-sm" onclick="closeViewBillModal()">Close</button>
            </div>
        `;

        openModal('viewBillModalOverlay');
    } catch (err) {
        showToast('Failed to load bill: ' + (err.message || 'Unknown error'), 'error');
    }
}

async function openBillModalForVisit(visitId) {
    try {
        const visit = await apiGet('visits/' + visitId);
        editingBillVehicleId = visit.vehicle_id;
        openBillModal(visit);
    } catch (err) {
        showToast('Failed to load visit', 'error');
    }
}
