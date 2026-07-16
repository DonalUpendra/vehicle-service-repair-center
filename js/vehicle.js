/* ==================== VEHICLE INTAKE ==================== */
let vehicleAutocompleteCache = [];
let selectedVehicle = null;
let autocompleteDebounce = null;

async function loadMakesDropdown() {
    try {
        const makes = await apiGet('makes');
        const select = document.getElementById('vMake');
        select.innerHTML = '<option value="">-- Select Make --</option>';
        makes.forEach(m => {
            select.innerHTML += `<option value="${escapeHtml(m.name)}">${escapeHtml(m.name)}</option>`;
        });
    } catch (err) {
        showToast('Failed to load makes', 'error');
    }
}

async function onMakeChange() {
    const makeName = document.getElementById('vMake').value;
    const datalist = document.getElementById('modelSuggestions');
    datalist.innerHTML = '';
    document.getElementById('vModel').value = '';
    if (!makeName) return;
    try {
        const makes = await apiGet('makes');
        const make = makes.find(m => m.name === makeName);
        if (!make) return;
        const models = await apiGet('models?make_id=' + make.id);
        models.forEach(m => {
            const opt = document.createElement('option');
            opt.value = m.name;
            datalist.appendChild(opt);
        });
    } catch (err) {
        showToast('Failed to load models', 'error');
    }
}

async function renderVehicleIntake() {
    if (!currentUser || currentUser.role !== 'admin') {
        document.getElementById('allVehiclesTable').innerHTML = '<div class="empty-state"><i class="fa-solid fa-lock"></i><h4>Access Denied</h4><p>Only administrators can access vehicle check-in.</p></div>';
        return;
    }
    showLoading('allVehiclesTable', 'Loading vehicles...');
    clearIntakeForm();
    await loadMakesDropdown();

    try {
        const vehicles = await apiGet('vehicles');
        vehicleAutocompleteCache = vehicles;

        let tableHTML = '<div class="table-responsive"><table class="striped"><thead><tr><th>ID</th><th>Reg Number</th><th>Make / Model</th><th>Owner</th><th>Email</th><th>Phone</th><th>Last Visit</th><th>Status</th><th>Action</th></tr></thead><tbody>';

        if (vehicles.length === 0) {
            tableHTML += `<tr><td colspan="9"><div class="empty-state"><i class="fa-solid fa-car-rear"></i><h4>No Vehicles Registered</h4><p>Use the form above to check in your first vehicle.</p></div></td></tr>`;
        } else {
            vehicles.forEach(v => {
                const regSafe = (v.registration_number || '').replace(/'/g, "\\'");
                tableHTML += `
                    <tr>
                        <td><strong>#${v.id}</strong></td>
                        <td><strong>${v.registration_number}</strong></td>
                        <td>${v.make || '-'} ${v.model || '-'}</td>
                        <td>${v.owner_name}</td>
                        <td>${v.owner_email || '-'}</td>
                        <td>${v.owner_phone || '-'}</td>
                        <td>${formatDate(v.last_visit_date)}</td>
                        <td>${v.last_visit_status ? getStatusBadge(v.last_visit_status) : '<span class="badge badge-pending"><i class="fa-solid fa-circle"></i> New</span>'}</td>
                        <td><button class="btn btn-sm btn-outline" onclick="selectVehicleFromReg('${regSafe}')"><i class="fa-solid fa-pen-to-square"></i> Manage</button></td>
                    </tr>`;
            });
        }
        tableHTML += '</tbody></table></div>';
        document.getElementById('allVehiclesTable').innerHTML = tableHTML;
    } catch (err) {
        showError('allVehiclesTable', err.message || 'Failed to load vehicles');
        showToast('Failed to load vehicles', 'error');
    }
}

function clearIntakeForm() {
    document.getElementById('vRegNumber').value = '';
    document.getElementById('vMake').value = '';
    document.getElementById('vModel').value = '';
    document.getElementById('modelSuggestions').innerHTML = '';
    document.getElementById('vOwnerName').value = '';
    document.getElementById('vOwnerEmail').value = '';
    document.getElementById('vOwnerPhone').value = '';
    document.getElementById('vOdometer').value = '';
    document.getElementById('vIssues').value = '';
    selectedVehicle = null;
    hideSuggestions();
    const btn = document.querySelector('#vehicleIntakeForm button[type="submit"]');
    if (btn) {
        btn.innerHTML = '<i class="fa-solid fa-circle-check"></i> Check-In Vehicle';
        btn.className = 'btn btn-accent';
    }
    document.getElementById('vehicleHistory').innerHTML = '';
    document.getElementById('vehicleHistoryPanel').style.display = 'none';
}

function setIntakeForm(vehicle) {
    document.getElementById('vRegNumber').value = vehicle.registration_number || '';
    const makeSelect = document.getElementById('vMake');
    if (vehicle.make && [...makeSelect.options].some(o => o.value === vehicle.make)) {
        makeSelect.value = vehicle.make;
        onMakeChange().then(() => {
            const modelInput = document.getElementById('vModel');
            if (vehicle.model) modelInput.value = vehicle.model;
        });
    } else {
        makeSelect.value = '';
        onMakeChange().then(() => {
            const modelInput = document.getElementById('vModel');
            if (vehicle.model) modelInput.value = vehicle.model;
        });
    }
    document.getElementById('vOwnerName').value = vehicle.owner_name || '';
    document.getElementById('vOwnerEmail').value = vehicle.owner_email || '';
    document.getElementById('vOwnerPhone').value = vehicle.owner_phone || '';
    document.getElementById('vOdometer').value = vehicle.odometer || '';
    document.getElementById('vIssues').value = '';
    selectedVehicle = vehicle;
    const btn = document.querySelector('#vehicleIntakeForm button[type="submit"]');
    if (btn) {
        btn.innerHTML = '<i class="fa-solid fa-rotate"></i> Re-Check-In & Update';
        btn.className = 'btn btn-warning';
    }
}

function hideSuggestions() {
    document.getElementById('vRegSuggestions').classList.remove('active');
}

function showSuggestions() {
    const el = document.getElementById('vRegSuggestions');
    if (el.children.length > 0) el.classList.add('active');
}

function onRegNumberInput() {
    clearTimeout(autocompleteDebounce);
    const input = document.getElementById('vRegNumber');
    const val = input.value.toUpperCase().trim();

    if (selectedVehicle && selectedVehicle.registration_number.toUpperCase() !== val) {
        selectedVehicle = null;
        const btn = document.querySelector('#vehicleIntakeForm button[type="submit"]');
        if (btn) {
            btn.innerHTML = '<i class="fa-solid fa-circle-check"></i> Check-In Vehicle';
            btn.className = 'btn btn-accent';
        }
        document.getElementById('vehicleHistory').innerHTML = '';
        document.getElementById('vehicleHistoryPanel').style.display = 'none';
    }

    if (val.length < 1) {
        hideSuggestions();
        return;
    }

    autocompleteDebounce = setTimeout(() => {
        const matches = vehicleAutocompleteCache.filter(v =>
            v.registration_number && v.registration_number.toUpperCase().includes(val)
        );

        const container = document.getElementById('vRegSuggestions');
        container.innerHTML = '';

        if (matches.length === 0) {
            hideSuggestions();
            return;
        }

        matches.forEach(v => {
            const div = document.createElement('div');
            div.className = 'suggestion-item';
            div.innerHTML = `<div class="sug-reg">${v.registration_number}</div><div class="sug-detail">${v.make || '?'} ${v.model || '?'} — ${v.owner_name}</div>`;
            div.addEventListener('mousedown', function(e) {
                e.preventDefault();
                selectVehicle(v.id);
            });
            container.appendChild(div);
        });

        showSuggestions();
    }, 200);
}

function onRegNumberBlur() {
    setTimeout(hideSuggestions, 200);
}

function onRegNumberFocus() {
    if (document.getElementById('vRegSuggestions').children.length > 0) {
        showSuggestions();
    }
}

async function selectVehicle(vehicleId) {
    hideSuggestions();
    try {
        const detail = await apiGet('vehicles/' + vehicleId);
        setIntakeForm(detail);
        renderVehicleHistory(detail);
    } catch (err) {
        showToast('Failed to load vehicle details', 'error');
    }
}

function selectVehicleFromReg(regNumber) {
    document.getElementById('vRegNumber').value = regNumber;
    onRegNumberInput();
    const match = vehicleAutocompleteCache.find(v => v.registration_number === regNumber);
    if (match) selectVehicle(match.id);
}

function renderVehicleHistory(vehicle) {
    const visits = vehicle.visits || [];
    const panel = document.getElementById('vehicleHistoryPanel');

    if (visits.length === 0) {
        document.getElementById('vehicleHistory').innerHTML = '<p style="color:var(--text-muted);">No previous visits.</p>';
    } else {
        let html = '<div class="table-responsive"><table class="striped"><thead><tr><th>Visit ID</th><th>Date</th><th>Odometer</th><th>Issues</th><th>Status</th><th>Bill</th></tr></thead><tbody>';
        visits.forEach(v => {
            const billLink = v.bill_id
                ? `<a href="#" onclick="viewBill(${v.bill_id}); return false;">#${v.bill_id} (${formatCurrency(v.total_amount)})</a>`
                : '-';
            html += `
                <tr>
                    <td>#${v.id}</td>
                    <td>${formatDate(v.check_in_date)}</td>
                    <td>${v.odometer ? Number(v.odometer).toLocaleString() : '-'}</td>
                    <td>${v.issues || '-'}</td>
                    <td>${getStatusBadge(v.status)}</td>
                    <td>${billLink}</td>
                </tr>`;
        });
        html += '</tbody></table></div>';
        document.getElementById('vehicleHistory').innerHTML = html;
    }
    panel.style.display = 'block';
}

async function handleVehicleIntake(e) {
    e.preventDefault();

    const btn = e.target.querySelector('button[type="submit"]');
    const originalText = btn.innerHTML;
    btn.innerHTML = '<span class="spinner spinner-white"></span> Checking In...';
    btn.disabled = true;

    const payload = {
        registration_number: document.getElementById('vRegNumber').value.trim(),
        make: document.getElementById('vMake').value.trim(),
        model: document.getElementById('vModel').value.trim(),
        owner_name: document.getElementById('vOwnerName').value.trim(),
        owner_email: document.getElementById('vOwnerEmail').value.trim(),
        owner_phone: document.getElementById('vOwnerPhone').value.trim(),
        odometer: parseInt(document.getElementById('vOdometer').value) || 0,
        issues: document.getElementById('vIssues').value.trim(),
    };

    try {
        const result = await apiPost('vehicles/check-in', payload);
        const isExisting = result.vehicle && result.vehicle.existing;

        clearIntakeForm();
        hideSuggestions();

        if (isExisting) {
            showToast('Vehicle re-checked in & owner details updated!', 'success');
        } else {
            showToast('Vehicle checked in successfully! Confirmation email sent to customer.', 'success');
        }
        renderVehicleIntake();
        renderDashboard();
    } catch (err) {
        showToast('Error: ' + (err.message || 'Failed to check in vehicle'), 'error');
    } finally {
        btn.innerHTML = originalText;
        btn.disabled = false;
    }
}