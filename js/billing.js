/* ==================== BILLING & SEARCH ==================== */
function renderSearchBill() {
    document.getElementById('searchVehicleInput').value = '';
    document.getElementById('searchResults').innerHTML = '<div class="empty-state"><i class="fa-solid fa-search"></i><h4>Search Vehicles</h4><p>Enter a registration number or owner name to find vehicles and create bills.</p></div>';
    loadProductsCache();
}

async function searchVehicles() {
    const query = document.getElementById('searchVehicleInput').value.trim();
    if (!query) {
        document.getElementById('searchResults').innerHTML = '<div class="empty-state"><i class="fa-solid fa-search"></i><h4>Enter Search</h4><p>Please enter a registration number or owner name to search.</p></div>';
        return;
    }

    document.getElementById('searchResults').innerHTML = '<div class="loading-state"><span class="spinner"></span><p>Searching vehicles...</p></div>';

    try {
        const vehicles = await apiGet('vehicles?search=' + encodeURIComponent(query));

        if (vehicles.length === 0) {
            document.getElementById('searchResults').innerHTML = '<div class="empty-state"><i class="fa-solid fa-magnifying-glass"></i><h4>No Results</h4><p>No vehicles found matching your search.</p></div>';
            return;
        }

        let html = '<table class="striped"><thead><tr><th>Reg Number</th><th>Owner</th><th>Make / Model</th><th>Last Visit</th><th>Status</th><th>Action</th></tr></thead><tbody>';

        vehicles.forEach(v => {
            const statusDisplay = v.last_visit_status ? getStatusBadge(v.last_visit_status) : '<span class="badge badge-pending"><i class="fa-solid fa-circle"></i> New</span>';

            html += `
                <tr>
                    <td><strong>${v.registration_number}</strong></td>
                    <td>${v.owner_name}</td>
                    <td>${v.make || '-'} ${v.model || '-'}</td>
                    <td>${formatDate(v.last_visit_date)}</td>
                    <td>${statusDisplay}</td>
                    <td>
                        <div class="btn-group">
                            <button class="btn btn-sm btn-outline" onclick="viewVehicleHistory(${v.id})"><i class="fa-solid fa-clock-rotate-left"></i> History</button>
                            <button class="btn btn-sm btn-accent" onclick="createBillForVehicle(${v.id})"><i class="fa-solid fa-plus"></i> New Bill</button>
                        </div>
                    </td>
                </tr>`;
        });

        html += '</tbody></table>';
        document.getElementById('searchResults').innerHTML = html;
    } catch (err) {
        showToast('Search failed: ' + (err.message || 'Unknown error'), 'error');
        document.getElementById('searchResults').innerHTML = '<div class="error-state"><i class="fa-solid fa-triangle-exclamation"></i><h4>Search Failed</h4><p>' + (err.message || 'Unknown error') + '</p></div>';
    }
}

async function viewVehicleHistory(vehicleId) {
    document.getElementById('searchResults').innerHTML = '<div class="loading-state"><span class="spinner"></span><p>Loading history...</p></div>';

    try {
        const vehicle = await apiGet('vehicles/' + vehicleId);
        let historyHTML = `<h4 style="margin-bottom:16px;"><i class="fa-solid fa-clock-rotate-left"></i> History for ${vehicle.registration_number} — ${vehicle.owner_name}</h4>`;

        const visits = vehicle.visits || [];
        if (visits.length === 0) {
            historyHTML += '<div class="empty-state"><i class="fa-solid fa-inbox"></i><h4>No Previous Visits</h4><p>This vehicle has no service history yet.</p></div>';
        } else {
            historyHTML += '<table class="striped"><thead><tr><th>Visit ID</th><th>Date</th><th>Status</th><th>Bill Total</th></tr></thead><tbody>';
            visits.forEach(vv => {
                historyHTML += `
                    <tr>
                        <td><strong>#${vv.id}</strong></td>
                        <td>${formatDate(vv.check_in_date)}</td>
                        <td>${vv.bill_status ? getStatusBadge(vv.bill_status) : getStatusBadge(vv.status)}</td>
                        <td>${vv.total_amount ? '<strong>' + formatCurrency(vv.total_amount) + '</strong>' : '-'}</td>
                    </tr>`;
            });
            historyHTML += '</tbody></table>';
        }

        historyHTML += `<button class="btn btn-sm btn-outline" onclick="renderSearchBill()" style="margin-top:12px;"><i class="fa-solid fa-arrow-left"></i> Back to Search</button>`;
        document.getElementById('searchResults').innerHTML = historyHTML;
    } catch (err) {
        showToast('Failed to load history: ' + (err.message || 'Unknown error'), 'error');
        document.getElementById('searchResults').innerHTML = '<div class="error-state"><i class="fa-solid fa-triangle-exclamation"></i><h4>Error</h4><p>' + (err.message || 'Unknown error') + '</p></div>';
    }
}

async function createBillForVehicle(vehicleId) {
    try {
        editingBillVehicleId = vehicleId;
        const vehicle = await apiGet('vehicles/' + vehicleId);
        const visits = vehicle.visits || [];
        let visit = visits.length > 0 ? visits[0] : null;

        if (!visit) {
            showToast('No visit found for this vehicle. Please check in the vehicle first.', 'error');
            return;
        }

        const visitDetail = await apiGet('visits/' + visit.id);
        openBillModal(visitDetail);
    } catch (err) {
        showToast('Error: ' + (err.message || 'Failed to create bill'), 'error');
    }
}

async function openBillModal(visit, prefillItems = []) {
    try {
        await loadProductsCache();
    } catch (e) { /* use cache if available */ }

    openModal('billModalOverlay');

    let itemsHTML = '';
    if (prefillItems.length > 0) {
        prefillItems.forEach(item => {
            itemsHTML += `
                <div class="form-row bill-item-row">
                    <div class="form-group">
                        <label>Product / Service</label>
                        <select class="bill-product-select" onchange="updateBillItemTotal(this)">
                            <option value="">-- Select --</option>
                            ${cachedProducts.map(p => `<option value="${p.id}" data-price="${p.unit_price}" ${p.id === item.product_id ? 'selected' : ''}>${p.name} (LKR ${parseFloat(p.unit_price).toLocaleString('en-US', {minimumFractionDigits:2})})</option>`).join('')}
                        </select>
                    </div>
                    <div class="form-group"><label>Qty</label><input type="number" class="bill-qty" value="${item.quantity}" min="1" onchange="updateBillItemTotal(this)"></div>
                    <div class="form-group"><label>Line Total</label><input type="text" class="bill-line-total" value="${parseFloat(item.line_total).toFixed(2)}" readonly></div>
                    <div class="form-group"><label>&nbsp;</label><button type="button" class="btn btn-sm btn-danger" onclick="removeBillItemRow(this)" title="Remove"><i class="fa-solid fa-trash"></i></button></div>
                </div>`;
        });
    } else {
        itemsHTML = `
            <div class="form-row bill-item-row">
                <div class="form-group">
                    <label>Product / Service</label>
                    <select class="bill-product-select" onchange="updateBillItemTotal(this)">
                        <option value="">-- Select --</option>
                        ${cachedProducts.map(p => `<option value="${p.id}" data-price="${p.unit_price}">${p.name} (LKR ${parseFloat(p.unit_price).toLocaleString('en-US', {minimumFractionDigits:2})})</option>`).join('')}
                    </select>
                </div>
                <div class="form-group"><label>Qty</label><input type="number" class="bill-qty" value="1" min="1" onchange="updateBillItemTotal(this)"></div>
                <div class="form-group"><label>Line Total</label><input type="text" class="bill-line-total" value="0.00" readonly></div>
                <div class="form-group"><label>&nbsp;</label><button type="button" class="btn btn-sm btn-danger" onclick="removeBillItemRow(this)" title="Remove"><i class="fa-solid fa-trash"></i></button></div>
            </div>`;
    }

    const isEdit = prefillItems.length > 0;
    document.getElementById('billModalBody').innerHTML = `
        <div class="form-row" style="margin-bottom:20px;">
            <div style="flex:1;">
                <p style="margin:0 0 2px;color:var(--text-secondary);font-size:0.75rem;text-transform:uppercase;letter-spacing:0.5px;">Vehicle</p>
                <p style="margin:0;font-weight:600;">${visit.registration_number || 'N/A'}</p>
                <p style="margin:0;color:var(--text-secondary);font-size:0.875rem;">${visit.make || ''} ${visit.model || ''}</p>
            </div>
            <div style="flex:1;">
                <p style="margin:0 0 2px;color:var(--text-secondary);font-size:0.75rem;text-transform:uppercase;letter-spacing:0.5px;">Owner</p>
                <p style="margin:0;font-weight:600;">${visit.owner_name || 'N/A'}</p>
            </div>
            <div style="flex:1;">
                <p style="margin:0 0 2px;color:var(--text-secondary);font-size:0.75rem;text-transform:uppercase;letter-spacing:0.5px;">Visit Info</p>
                <p style="margin:0;font-size:0.875rem;">#${visit.id} &nbsp;|&nbsp; ${formatDate(visit.check_in_date)}</p>
                ${visit.odometer ? `<p style="margin:0;font-size:0.875rem;">Odometer: ${Number(visit.odometer).toLocaleString()} km</p>` : ''}
            </div>
        </div>
        ${visit.issues ? `
        <div style="margin-bottom:16px;padding:12px;background:var(--bg-secondary);border-radius:8px;border-left:4px solid var(--warning, #f59e0b);">
            <p style="margin:0 0 4px;font-weight:600;font-size:0.875rem;color:var(--text-secondary);"><i class="fa-solid fa-triangle-exclamation"></i> Reported Issues</p>
            <p style="margin:0;white-space:pre-wrap;">${visit.issues}</p>
        </div>
        ` : ''}
        ${isEdit ? `<div style="margin-bottom:12px;padding:10px 14px;background:#fef3c7;border-radius:8px;border-left:4px solid #f59e0b;"><i class="fa-solid fa-pen-to-square"></i> <strong>Editing Quotation</strong> — Update items and re-submit for admin approval.</div>` : ''}
        <hr>
        <h4 style="margin-bottom:12px;display:flex;align-items:center;gap:8px;"><i class="fa-solid fa-list-ol"></i> Bill Items</h4>
        <div id="billItemsContainer">
            ${itemsHTML}
        </div>
        <button type="button" class="btn btn-sm btn-outline" onclick="addBillItemRow()" style="margin-top:8px;"><i class="fa-solid fa-plus"></i> Add Item</button>
        <div style="text-align:right;margin-top:16px;font-size:1.2rem;font-weight:700;color:var(--primary);">Total: LKR <span id="billGrandTotal">0.00</span></div>
        <div class="modal-footer" style="border:none;padding:0;margin-top:20px;">
            <button type="button" class="btn btn-outline btn-sm" onclick="closeBillModal()">Cancel</button>
            <button type="button" class="btn btn-accent btn-sm" onclick="submitBill(${visit.id})"><i class="fa-solid fa-paper-plane"></i> ${isEdit ? 'Re-submit for Admin Review' : 'Submit for Admin Review'}</button>
        </div>
    `;

    updateBillGrandTotal();
}

function closeBillModal() {
    closeModal('billModalOverlay');
    editingBillVehicleId = null;
}

function addBillItemRow() {
    const container = document.getElementById('billItemsContainer');
    const row = document.createElement('div');
    row.className = 'form-row bill-item-row';
    row.style.animation = 'fadeInUp 0.3s ease';
    row.innerHTML = `
        <div class="form-group">
            <label>Product / Service</label>
            <select class="bill-product-select" onchange="updateBillItemTotal(this)">
                <option value="">-- Select --</option>
                ${cachedProducts.map(p => `<option value="${p.id}" data-price="${p.unit_price}">${p.name} (LKR ${parseFloat(p.unit_price).toLocaleString('en-US', {minimumFractionDigits:2})})</option>`).join('')}
            </select>
        </div>
        <div class="form-group"><label>Qty</label><input type="number" class="bill-qty" value="1" min="1" onchange="updateBillItemTotal(this)"></div>
        <div class="form-group"><label>Line Total</label><input type="text" class="bill-line-total" value="0.00" readonly></div>
        <div class="form-group"><label>&nbsp;</label><button type="button" class="btn btn-sm btn-danger" onclick="removeBillItemRow(this)" title="Remove"><i class="fa-solid fa-trash"></i></button></div>
    `;
    container.appendChild(row);
}

function removeBillItemRow(btn) {
    btn.closest('.bill-item-row').remove();
    const rows = document.querySelectorAll('#billItemsContainer .bill-item-row');
    if (rows.length === 0) addBillItemRow();
    updateBillGrandTotal();
}

function updateBillItemTotal(el) {
    const row = el.closest('.bill-item-row');
    const select = row.querySelector('.bill-product-select');
    const qtyInput = row.querySelector('.bill-qty');
    const lineTotal = row.querySelector('.bill-line-total');
    const selectedOption = select.options[select.selectedIndex];
    const price = selectedOption ? parseFloat(selectedOption.getAttribute('data-price')) || 0 : 0;
    const qty = parseInt(qtyInput.value) || 0;
    lineTotal.value = (price * qty).toFixed(2);
    updateBillGrandTotal();
}

function updateBillGrandTotal() {
    const lineTotals = document.querySelectorAll('#billItemsContainer .bill-line-total');
    let grand = 0;
    lineTotals.forEach(lt => { grand += parseFloat(lt.value) || 0; });
    const grandEl = document.getElementById('billGrandTotal');
    if (grandEl) grandEl.textContent = grand.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

async function submitBill(visitId) {
    const selects = document.querySelectorAll('#billItemsContainer .bill-product-select');
    const qtys = document.querySelectorAll('#billItemsContainer .bill-qty');
    const lineTotals = document.querySelectorAll('#billItemsContainer .bill-line-total');
    const items = [];
    let grandTotal = 0;

    for (let i = 0; i < selects.length; i++) {
        const productId = parseInt(selects[i].value);
        const qty = parseInt(qtys[i].value) || 0;
        const lineTotal = parseFloat(lineTotals[i].value) || 0;

        if (productId && qty > 0) {
            const product = cachedProducts.find(p => p.id === productId);
            items.push({
                productId: productId,
                productName: product ? product.name : 'Unknown',
                quantity: qty,
                unitPrice: product ? parseFloat(product.unit_price) : 0,
                buyPrice: product ? parseFloat(product.buy_price) : 0,
                lineTotal: lineTotal,
            });
            grandTotal += lineTotal;
        }
    }

    if (items.length === 0) {
        showToast('Please add at least one item to the bill.', 'warning');
        return;
    }

    const submitBtn = document.querySelector('#billModalBody .modal-footer .btn-accent');
    const originalHTML = submitBtn.innerHTML;
    submitBtn.innerHTML = '<span class="spinner spinner-white"></span> Submitting...';
    submitBtn.disabled = true;

    try {
        const result = await apiPost('bills', {
            visitId: visitId,
            technicianId: currentUser.id,
            items: items,
        });

        closeBillModal();
        showToast(`Quotation #${result.bill.id} submitted for admin review!`, 'success');
        renderDashboard();
        renderSearchBill();
    } catch (err) {
        showToast('Error: ' + (err.message || 'Failed to submit bill'), 'error');
        submitBtn.innerHTML = originalHTML;
        submitBtn.disabled = false;
    }
}
