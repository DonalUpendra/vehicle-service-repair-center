/* ==================== SALARY / PAYMENTS MANAGEMENT ==================== */
async function renderSalaries() {
    showLoading('salaryTable', 'Loading payment history...');

    try {
        const [payments, techs] = await Promise.all([
            apiGet('salaries'),
            apiGet('users'),
        ]);

        const techSelect = document.getElementById('salaryTechnician');
        const currentVal = techSelect.value;
        techSelect.innerHTML = '<option value="">-- Select --</option>';
        techs.forEach(t => {
            const sel = t.id == currentVal ? 'selected' : '';
            techSelect.innerHTML += `<option value="${t.id}" ${sel}>${t.name} (${t.email})</option>`;
        });

        document.getElementById('salaryDate').value = new Date().toISOString().slice(0, 10);

        if (payments.length === 0) {
            document.getElementById('salaryTable').innerHTML = '<div class="empty-state"><i class="fa-solid fa-wallet"></i><h4>No Payments Yet</h4><p>Record salary/commission payments to technicians.</p></div>';
            return;
        }

        let html = '<table class="striped"><thead><tr><th>ID</th><th>Technician</th><th>Amount (LKR)</th><th>Date</th><th>Notes</th><th>Action</th></tr></thead><tbody>';

        payments.forEach(p => {
            html += `
                <tr>
                    <td><strong>#${p.id}</strong></td>
                    <td>${p.technician_name || 'N/A'}</td>
                    <td><strong>${formatCurrency(p.amount)}</strong></td>
                    <td>${formatDate(p.payment_date)}</td>
                    <td>${p.notes || '-'}</td>
                    <td><button class="btn btn-sm btn-danger" onclick="deleteSalaryPayment(${p.id})"><i class="fa-solid fa-trash"></i></button></td>
                </tr>`;
        });

        html += '</tbody></table>';
        document.getElementById('salaryTable').innerHTML = html;
    } catch (err) {
        showError('salaryTable', err.message || 'Failed to load payments');
        showToast('Failed to load payments: ' + (err.message || 'Unknown error'), 'error');
    }
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
    try {
        await apiDelete('salaries/' + id);
        renderSalaries();
        showToast('Payment deleted.', 'info');
    } catch (err) {
        showToast('Error: ' + (err.message || 'Failed to delete payment'), 'error');
    }
}