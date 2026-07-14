/* ==================== PUBLIC QUOTE PAGE ==================== */
async function loadPublicQuotePage() {
    const urlParams = new URLSearchParams(window.location.search);
    const token = urlParams.get('token');

    if (!token) {
        document.getElementById('quoteCardContent').innerHTML = `
            <div class="empty-state">
                <i class="fa-solid fa-link-slash"></i>
                <h4>Invalid Link</h4>
                <p>No approval token provided. Please use the link from your email.</p>
            </div>`;
        showPublicQuotePage();
        return;
    }

    document.getElementById('quoteCardContent').innerHTML = `
        <div class="loading-state"><span class="spinner spinner-lg"></span><p>Loading quotation...</p></div>`;
    showPublicQuotePage();

    try {
        const data = await apiGet('public/quotation?token=' + encodeURIComponent(token));
        const bill = data.bill;

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

        document.getElementById('quoteCardContent').innerHTML = `
            <div class="shop-header">
                <div class="shop-icon"><img src="logo.png" alt="Lumina AutoWorks" style="width:48px;height:48px;object-fit:contain;"></div>
                <h2>Lumina AutoWorks</h2>
                <p>Quotation #${bill.id}</p>
            </div>
            <div class="info-row">
                <div><strong>Vehicle</strong><br>${bill.registration_number || 'N/A'} — ${bill.make || ''} ${bill.model || ''}</div>
                <div><strong>Owner</strong><br>${bill.owner_name || 'N/A'}</div>
                <div><strong>Date</strong><br>${formatDate(bill.created_at)}</div>
                <div><strong>Status</strong><br>${getStatusBadge(bill.status)}</div>
            </div>
            <table class="striped">
                <thead><tr><th>Item</th><th>Qty</th><th>Unit Price</th><th style="text-align:right;">Total</th></tr></thead>
                <tbody>${itemsHTML}</tbody>
            </table>
            <div class="total-row">Grand Total: ${formatCurrency(bill.total_amount)}</div>
            ${['pending_approval', 'draft'].includes(bill.status) ? `
                <div class="quote-actions">
                    <div class="form-group" style="flex:1 1 100%;margin-bottom:12px;">
                        <label><i class="fa-solid fa-pen"></i> Notes (optional)</label>
                        <textarea id="customerDescription" rows="2" placeholder="Add a note for the service center..."></textarea>
                    </div>
                    <button class="btn btn-success" onclick="customerAction('${token}', 'approve')"><i class="fa-solid fa-circle-check"></i> Approve Quotation</button>
                    <button class="btn btn-danger" onclick="customerAction('${token}', 'reject')"><i class="fa-solid fa-xmark-circle"></i> Reject Quotation</button>
                </div>
            ` : `
                <div class="quote-status-badge" style="background:${['approved','in_progress','completed'].includes(bill.status) ? 'var(--success-light)' : 'var(--danger-light)'};color:${bill.status === 'rejected' ? '#991b1b' : '#065f46'};">
                    <i class="fa-solid fa-${['approved','in_progress','completed'].includes(bill.status) ? 'circle-check' : 'circle-info'}"></i>
                    This quotation has been <strong>${bill.status.replace(/_/g, ' ').toUpperCase()}</strong>.
                </div>
            `}
        `;

        showPublicQuotePage();
    } catch (err) {
        let message = 'Invalid or expired link.';
        if (err.status === 410) message = err.message || 'This link has expired or has already been used.';
        else if (err.status === 404) message = 'Invalid link. The quotation may have been removed.';

        document.getElementById('quoteCardContent').innerHTML = `
            <div class="empty-state">
                <i class="fa-solid fa-triangle-exclamation"></i>
                <h4>Link Invalid</h4>
                <p>${message}</p>
                <p style="font-size:0.8125rem;color:var(--text-muted);">Please contact the service center for assistance.</p>
            </div>`;
        showPublicQuotePage();
    }
}

function showPublicQuotePage() {
    document.getElementById('publicQuotePage').classList.add('active');
    document.getElementById('loginScreen').classList.add('hidden');
    document.getElementById('appLayout').classList.remove('active');
}

async function customerAction(token, action) {
    try {
        const description = document.getElementById('customerDescription')?.value?.trim() || '';

        const confirmBtn = document.querySelector(`.quote-actions .btn-${action === 'approve' ? 'success' : 'danger'}`);
        if (confirmBtn) {
            confirmBtn.innerHTML = '<span class="spinner spinner-white"></span> Processing...';
            confirmBtn.disabled = true;
        }

        await apiPost('public/approve', { token, action, description });

        document.getElementById('quoteCardContent').innerHTML = `
            <div class="empty-state">
                <i class="fa-solid fa-circle-${action === 'approve' ? 'check' : 'xmark'}" style="color:${action === 'approve' ? 'var(--success)' : 'var(--danger)'};"></i>
                <h4>${action === 'approve' ? 'Quotation Approved!' : 'Quotation Rejected'}</h4>
                <p>${action === 'approve' ? 'The service center will begin work shortly. Thank you!' : 'The service center has been notified. Thank you!'}</p>
            </div>`;
        showPublicQuotePage();
    } catch (err) {
        showToast('Error: ' + (err.message || 'Failed to process request'), 'error');
    }
}
