/* ==================== TECHNICIANS MANAGEMENT ==================== */
async function renderTechnicians() {
    if (!currentUser || currentUser.role !== 'admin') return;

    showLoading('techniciansTable', 'Loading...');

    try {
        const techs = await apiGet('users');
        let tableHTML = '<div class="table-responsive"><table class="striped"><thead><tr><th>ID</th><th>Name</th><th>Email</th><th>Commission</th><th>Status</th><th>Actions</th></tr></thead><tbody>';

        if (techs.length === 0) {
            tableHTML += `<tr><td colspan="6"><div class="empty-state"><i class="fa-solid fa-user-slash"></i><h4>No Technicians</h4><p>Add a technician to get started.</p></div></td></tr>`;
        } else {
            techs.forEach(t => {
                const active = t.active === 1 || t.active === true || t.active === '1';
                const commission = parseFloat(t.commission_percentage || 0);
                tableHTML += `
                    <tr>
                        <td><strong>#${t.id}</strong></td>
                        <td>${t.name}</td>
                        <td>${t.email}</td>
                        <td><strong>${commission > 0 ? commission + '%' : '-'}</strong></td>
                        <td>${active ? '<span class="badge badge-approved"><i class="fa-solid fa-check-circle"></i> Active</span>' : '<span class="badge badge-rejected"><i class="fa-solid fa-ban"></i> Disabled</span>'}</td>
                        <td>
                            <div class="btn-group">
                                <button class="btn btn-sm btn-outline" onclick="editTechnicianCommission(${t.id}, ${commission})"><i class="fa-solid fa-percent"></i> Commission</button>
                                <button class="btn btn-sm btn-outline" onclick="toggleTechnician(${t.id}, ${active})">
                                    <i class="fa-solid fa-${active ? 'toggle-off' : 'toggle-on'}"></i> ${active ? 'Disable' : 'Enable'}
                                </button>
                                <button class="btn btn-sm btn-danger" onclick="deleteTechnician(${t.id})"><i class="fa-solid fa-trash"></i></button>
                            </div>
                        </td>
                    </tr>`;
            });
        }
        tableHTML += '</tbody></table></div>';
        document.getElementById('techniciansTable').innerHTML = tableHTML;
    } catch (err) {
        showError('techniciansTable', err.message || 'Failed to load technicians');
        showToast('Failed to load technicians', 'error');
    }
}

function openTechnicianModal() {
    openModal('technicianModalOverlay');
    document.getElementById('techModalTitle').innerHTML = '<i class="fa-solid fa-user-plus"></i> Add Technician';
    document.getElementById('technicianForm').reset();
    document.getElementById('techEditId').value = '';
    document.getElementById('techCommission').value = '';
}

function closeTechnicianModal() {
    closeModal('technicianModalOverlay');
}

async function saveTechnician(e) {
    e.preventDefault();
    const payload = {
        name: document.getElementById('techName').value.trim(),
        email: document.getElementById('techEmail').value.trim(),
        password: document.getElementById('techPassword').value.trim(),
        commission_percentage: parseFloat(document.getElementById('techCommission').value) || 0,
    };

    try {
        await apiPost('users', payload);
        closeTechnicianModal();
        renderTechnicians();
        showToast('Technician added successfully!', 'success');
    } catch (err) {
        showToast('Error: ' + (err.message || 'Failed to add technician'), 'error');
    }
}

async function editTechnicianCommission(id, currentCommission) {
    const newCommission = prompt('Enter commission percentage for this technician:', currentCommission);
    if (newCommission === null) return;
    const val = parseFloat(newCommission);
    if (isNaN(val) || val < 0) {
        showToast('Please enter a valid percentage (0 or higher).', 'warning');
        return;
    }
    try {
        await apiPut('users/' + id, { commission_percentage: val });
        renderTechnicians();
        showToast('Commission updated to ' + val + '%.', 'success');
    } catch (err) {
        showToast('Error: ' + (err.message || 'Failed to update commission'), 'error');
    }
}

async function toggleTechnician(id, currentlyActive) {
    try {
        await apiPut('users/' + id, { active: !currentlyActive });
        renderTechnicians();
        showToast(`Technician ${currentlyActive ? 'disabled' : 'enabled'}.`, 'info');
    } catch (err) {
        showToast('Error: ' + (err.message || 'Failed to toggle technician'), 'error');
    }
}

async function deleteTechnician(id) {
    if (!confirm('Are you sure you want to delete this technician?')) return;
    try {
        await apiDelete('users/' + id);
        renderTechnicians();
        showToast('Technician deleted.', 'info');
    } catch (err) {
        showToast('Error: ' + (err.message || 'Failed to delete technician'), 'error');
    }
}