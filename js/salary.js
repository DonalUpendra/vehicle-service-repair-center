/* ==================== SALARY / PAYMENTS MANAGEMENT ==================== */
let cachedEarnings = [];

async function renderSalaries() {
    showLoading('salaryTable', 'Loading payment history...');
    showLoading('commissionEarningsTable', 'Loading commission data...');

    try {
        const [payments, techs, earnings] = await Promise.all([
            apiGet('salaries'),
            apiGet('users'),
            apiGet('salaries/earnings'),
        ]);

        cachedEarnings = earnings;

        const techSelect = document.getElementById('salaryTechnician');
        const currentVal = techSelect.value;
        techSelect.innerHTML = '<option value="">-- Select --</option>';
        techs.forEach(t => {
            const sel = t.id == currentVal ? 'selected' : '';
            techSelect.innerHTML += `<option value="${t.id}" ${sel}>${t.name} (${t.email})</option>`;
        });

        document.getElementById('salaryDate').value = new Date().toISOString().slice(0, 10);

        renderCommissionEarnings(earnings);
        renderPaymentHistory(payments);

        if (currentVal) {
            onTechnicianSelect();
        }
    } catch (err) {
        showError('salaryTable', err.message || 'Failed to load payments');
        showError('commissionEarningsTable', err.message || 'Failed to load commission data');
        showToast('Failed to load data: ' + (err.message || 'Unknown error'), 'error');
    }
}

function renderPaymentHistory(payments) {
    if (payments.length === 0) {
        document.getElementById('salaryTable').innerHTML = '<div class="empty-state"><i class="fa-solid fa-wallet"></i><h4>No Payments Yet</h4><p>Record salary/commission payments to technicians.</p></div>';
        return;
    }

    let html = '<div class="table-responsive"><table class="striped"><thead><tr><th></th><th>ID</th><th>Technician</th><th>Amount (LKR)</th><th>Date</th><th>Balance</th><th>Action</th></tr></thead><tbody>';

    payments.forEach(p => {
        const commPct = parseFloat(p.commission_percentage || 0);
        const hasDetails = p.total_billed !== null && p.total_billed !== undefined;
        const detailId = 'pmt-detail-' + p.id;

        // Main row
        html += '<tr>';
        html += '<td>';
        if (hasDetails) {
            html += '<button class="btn btn-sm btn-outline" onclick="togglePmtDetail(\'' + detailId + '\')"><i class="fa-solid fa-chevron-down" id="' + detailId + '-icon"></i></button>';
        }
        html += '</td>';
        html += '<td><strong>#' + p.id + '</strong></td>';
        html += '<td>' + (p.technician_name || 'N/A');
        if (commPct > 0) {
            html += ' <small class="text-muted">(' + commPct + '%)</small>';
        }
        html += '</td>';
        html += '<td><strong>' + formatCurrency(p.amount) + '</strong></td>';
        html += '<td>' + formatDate(p.payment_date) + '</td>';
        html += '<td class="' + (hasDetails && p.balance > 0 ? 'text-accent' : '') + '"><strong>' + (hasDetails ? formatCurrency(p.balance) : '-') + '</strong></td>';
        html += '<td><button class="btn btn-sm btn-danger" onclick="deleteSalaryPayment(' + p.id + ')"><i class="fa-solid fa-trash"></i></button></td>';
        html += '</tr>';

        // Detail row (only for first occurrence per technician)
        if (hasDetails) {
            // Build payment list for this technician
            let pmtListHtml = '';
            if (p.payments && p.payments.length > 0) {
                p.payments.forEach(function(pmt) {
                    const isThis = pmt.id === p.id ? ' <span class="badge badge-inprogress" style="font-size:0.65rem;">this payment</span>' : '';
                    pmtListHtml += '<tr>';
                    pmtListHtml += '<td>#' + pmt.id + '</td>';
                    pmtListHtml += '<td>' + formatCurrency(pmt.amount) + '</td>';
                    pmtListHtml += '<td>' + formatDate(pmt.payment_date) + '</td>';
                    pmtListHtml += '<td>' + (pmt.notes || '-') + ' ' + isThis + '</td>';
                    pmtListHtml += '</tr>';
                });
            }

            html += '<tr id="' + detailId + '" class="pmt-detail-row" style="display:none;">';
            html += '<td colspan="7">';
            html += '<div class="pmt-detail-card">';

            // Section: Commission Calculation
            html += '<div class="pmt-detail-section">';
            html += '<h4><i class="fa-solid fa-calculator"></i> Commission Calculation for ' + p.technician_name + '</h4>';
            html += '<div class="pmt-detail-grid">';
            html += '<div class="pmt-detail-item">';
            html += '<span class="pmt-detail-label">Commission Rate</span>';
            html += '<span class="pmt-detail-value">' + commPct + '%</span>';
            html += '</div>';
            html += '<div class="pmt-detail-item">';
            html += '<span class="pmt-detail-label">Total Completed Jobs (Billed Amount)</span>';
            html += '<span class="pmt-detail-value">' + formatCurrency(p.total_billed) + '</span>';
            html += '</div>';
            html += '</div>';

            // Formula box
            html += '<div class="pmt-detail-formula">';
            html += '<div class="formula-line">' + formatCurrency(p.total_billed) + ' <span class="formula-op">×</span> ' + commPct + '% <span class="formula-op">=</span> <strong>' + formatCurrency(p.estimated_commission) + '</strong></div>';
            html += '<div class="formula-label">Total Commission Earned</div>';
            html += '</div>';

            html += '<div class="pmt-detail-grid">';
            html += '<div class="pmt-detail-item">';
            html += '<span class="pmt-detail-label">Total Paid So Far</span>';
            html += '<span class="pmt-detail-value">' + formatCurrency(p.total_paid) + '</span>';
            html += '</div>';
            html += '<div class="pmt-detail-item">';
            html += '<span class="pmt-detail-label">Remaining Balance</span>';
            html += '<span class="pmt-detail-value ' + (p.balance > 0 ? 'text-accent' : 'text-success') + '">' + formatCurrency(p.balance) + '</span>';
            html += '</div>';
            html += '</div>';

            if (p.balance > 0) {
                html += '<button class="btn btn-accent btn-sm" onclick="payBalance(' + p.technician_id + ', ' + p.balance + ');closePmtDetail(\'' + detailId + '\')"><i class="fa-solid fa-sack-dollar"></i> Pay Remaining Balance</button>';
            } else {
                html += '<span class="badge badge-completed">Settled - All Commission Paid</span>';
            }
            html += '</div>'; // end commission section

            // Section: All Payments
            html += '<div class="pmt-detail-section">';
            html += '<h4><i class="fa-solid fa-clock-rotate-left"></i> All Payments for ' + p.technician_name + '</h4>';
            html += '<table class="striped" style="margin-top:8px;">';
            html += '<thead><tr><th>ID</th><th>Amount</th><th>Date</th><th>Notes</th></tr></thead>';
            html += '<tbody>' + pmtListHtml + '</tbody>';
            html += '</table>';
            html += '</div>'; // end payments section

            html += '</div>'; // end detail card
            html += '</td>';
            html += '</tr>'; // end detail row
        }
    });

    html += '</tbody></table></div>';
    document.getElementById('salaryTable').innerHTML = html;
}

function togglePmtDetail(detailId) {
    const row = document.getElementById(detailId);
    const icon = document.getElementById(detailId + '-icon');
    if (!row) return;
    const isHidden = row.style.display === 'none' || row.style.display === '';
    row.style.display = isHidden ? 'table-row' : 'none';
    if (icon) {
        icon.className = isHidden ? 'fa-solid fa-chevron-up' : 'fa-solid fa-chevron-down';
    }
}

function closePmtDetail(detailId) {
    const row = document.getElementById(detailId);
    const icon = document.getElementById(detailId + '-icon');
    if (row) row.style.display = 'none';
    if (icon) icon.className = 'fa-solid fa-chevron-down';
}

function renderCommissionEarnings(earnings) {
    if (!earnings || earnings.length === 0) {
        document.getElementById('commissionEarningsTable').innerHTML = '<div class="empty-state"><i class="fa-solid fa-percent"></i><h4>No Technicians</h4><p>Add technicians first to track commissions.</p></div>';
        return;
    }

    let html = '<div class="table-responsive"><table class="striped"><thead><tr><th>Technician</th><th>Commission %</th><th>Total Billed (Completed)</th><th>Commission Earned</th><th>Paid So Far</th><th>Balance</th><th>Action</th></tr></thead><tbody>';

    earnings.forEach(e => {
        const balance = e.balance;
        html += '<tr>';
        html += '<td><strong>' + e.technician_name + '</strong><br><small>' + e.email + '</small></td>';
        html += '<td><strong>' + (e.commission_percentage > 0 ? e.commission_percentage + '%' : '-') + '</strong></td>';
        html += '<td>' + formatCurrency(e.total_billed) + '</td>';
        html += '<td><strong>' + formatCurrency(e.estimated_commission) + '</strong></td>';
        html += '<td>' + formatCurrency(e.paid) + '</td>';
        html += '<td class="' + (balance > 0 ? 'text-accent' : 'text-success') + '"><strong>' + formatCurrency(balance) + '</strong></td>';
        html += '<td>';
        if (balance > 0) {
            html += '<button class="btn btn-sm btn-accent" onclick="payBalance(' + e.technician_id + ', ' + balance + ')"><i class="fa-solid fa-sack-dollar"></i> Pay Balance</button>';
        } else {
            html += '<span class="badge badge-completed">Settled</span>';
        }
        html += '</td>';
        html += '</tr>';
    });

    html += '</tbody></table></div>';
    document.getElementById('commissionEarningsTable').innerHTML = html;
}

function onTechnicianSelect() {
    const techId = parseInt(document.getElementById('salaryTechnician').value);
    const infoDiv = document.getElementById('commissionInfo');

    if (!techId) {
        infoDiv.style.display = 'none';
        return;
    }

    const earning = cachedEarnings.find(e => e.technician_id === techId);
    if (!earning) {
        infoDiv.style.display = 'none';
        return;
    }

    infoDiv.style.display = 'block';
    const balance = earning.balance;
    infoDiv.innerHTML =
        '<div class="info-card">' +
        '<div class="info-row">' +
        '<div class="info-item">' +
        '<span class="info-label">Commission Rate</span>' +
        '<span class="info-value">' + (earning.commission_percentage > 0 ? earning.commission_percentage + '%' : 'Not Set') + '</span>' +
        '</div>' +
        '<div class="info-item">' +
        '<span class="info-label">Total Completed Jobs</span>' +
        '<span class="info-value">' + formatCurrency(earning.total_billed) + '</span>' +
        '</div>' +
        '<div class="info-item">' +
        '<span class="info-label">Commission Earned</span>' +
        '<span class="info-value text-accent">' + formatCurrency(earning.estimated_commission) + '</span>' +
        '</div>' +
        '<div class="info-item">' +
        '<span class="info-label">Already Paid</span>' +
        '<span class="info-value">' + formatCurrency(earning.paid) + '</span>' +
        '</div>' +
        '<div class="info-item">' +
        '<span class="info-label">Remaining Balance</span>' +
        '<span class="info-value ' + (balance > 0 ? 'text-accent' : 'text-success') + '">' + formatCurrency(balance) + '</span>' +
        '</div>' +
        '</div>' +
        (balance > 0
            ? '<button class="btn btn-accent btn-sm" onclick="payBalance(' + earning.technician_id + ', ' + balance + ')" style="margin-top:10px;"><i class="fa-solid fa-sack-dollar"></i> Pay Remaining Balance (' + formatCurrency(balance) + ')</button>'
            : '<span class="badge badge-completed" style="margin-top:10px;display:inline-block;">All Paid</span>') +
        '</div>';
}

function payBalance(techId, balance) {
    document.getElementById('salaryTechnician').value = techId;
    document.getElementById('salaryAmount').value = balance.toFixed(2);
    document.getElementById('salaryNotes').value = 'Commission payment for completed jobs';
    onTechnicianSelect();
    document.getElementById('salaryAmount').focus();
    showToast('Amount set to balance: ' + formatCurrency(balance), 'info');
}

async function handleSalaryPayment(e) {
    e.preventDefault();

    const btn = e.target.querySelector('button[type="submit"]');
    const originalText = btn.innerHTML;
    btn.innerHTML = '<span class="spinner spinner-white"></span> Saving...';
    btn.disabled = true;

    const payload = {
        technician_id: parseInt(document.getElementById('salaryTechnician').value),
        amount: parseFloat(document.getElementById('salaryAmount').value),
        payment_date: document.getElementById('salaryDate').value,
        notes: document.getElementById('salaryNotes').value.trim(),
    };

    try {
        await apiPost('salaries', payload);
        document.getElementById('salaryForm').reset();
        document.getElementById('salaryDate').value = new Date().toISOString().slice(0, 10);
        document.getElementById('commissionInfo').style.display = 'none';
        showToast('Payment recorded successfully!', 'success');
        renderSalaries();
    } catch (err) {
        showToast('Error: ' + (err.message || 'Failed to record payment'), 'error');
    } finally {
        btn.innerHTML = originalText;
        btn.disabled = false;
    }
}

async function deleteSalaryPayment(id) {
    if (!confirm('Delete this payment record?')) return;
    showLoading('salaryTable', 'Deleting...');
    try {
        await apiDelete('salaries/' + id);
        renderSalaries();
        showToast('Payment deleted.', 'info');
    } catch (err) {
        showToast('Error: ' + (err.message || 'Failed to delete payment'), 'error');
        renderSalaries();
    }
}
