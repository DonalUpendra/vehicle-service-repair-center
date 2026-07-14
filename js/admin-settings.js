/* ==================== ADMIN SETTINGS ==================== */
async function renderSettings() {
    try {
        const settings = await apiGet('settings');
        const map = {};
        settings.forEach(s => { map[s.setting_key] = s.setting_value; });

        document.getElementById('setSmtpHost').value = map['smtp_host'] || '';
        document.getElementById('setSmtpPort').value = map['smtp_port'] || '465';
        document.getElementById('setSmtpEncryption').value = map['smtp_encryption'] || 'ssl';
        document.getElementById('setSmtpUsername').value = map['smtp_username'] || '';
        document.getElementById('setSmtpPassword').value = map['smtp_password'] || '';
        document.getElementById('setFromEmail').value = map['smtp_from_email'] || '';
        document.getElementById('setFromName').value = map['smtp_from_name'] || '';
        document.getElementById('setEmailEnabled').value = map['email_enabled'] || '1';

        renderMakes();
    } catch (err) {
        showToast('Failed to load settings: ' + (err.message || 'Unknown error'), 'error');
    }
}

async function saveSettings() {
    const settings = {
        smtp_host: document.getElementById('setSmtpHost').value.trim(),
        smtp_port: document.getElementById('setSmtpPort').value.trim(),
        smtp_encryption: document.getElementById('setSmtpEncryption').value,
        smtp_username: document.getElementById('setSmtpUsername').value.trim(),
        smtp_password: document.getElementById('setSmtpPassword').value.trim(),
        smtp_from_email: document.getElementById('setFromEmail').value.trim(),
        smtp_from_name: document.getElementById('setFromName').value.trim(),
        email_enabled: document.getElementById('setEmailEnabled').value,
    };

    const saveBtn = document.querySelector('#page-settings .btn-accent');
    const originalHTML = saveBtn.innerHTML;
    saveBtn.innerHTML = '<span class="spinner spinner-white"></span> Saving...';
    saveBtn.disabled = true;

    try {
        await apiPut('settings', settings);
        showToast('Settings saved successfully!', 'success');
    } catch (err) {
        showToast('Failed to save settings: ' + (err.message || 'Unknown error'), 'error');
    } finally {
        saveBtn.innerHTML = originalHTML;
        saveBtn.disabled = false;
    }
}

/* ==================== VEHICLE MAKES / BRANDS ==================== */
async function renderMakes() {
    try {
        const makes = await apiGet('makes');
        const list = document.getElementById('makesList');
        if (makes.length === 0) {
            list.innerHTML = '<p style="color:var(--text-muted);">No makes defined yet.</p>';
        } else {
            let html = '<div class="table-responsive"><table class="striped"><thead><tr><th>Make / Brand</th><th>Action</th></tr></thead><tbody>';
            makes.forEach(m => {
                html += `<tr>
                    <td>${escapeHtml(m.name)}</td>
                    <td><button class="btn btn-sm btn-outline-danger" onclick="deleteMake(${m.id})"><i class="fa-solid fa-trash-can"></i> Delete</button></td>
                </tr>`;
            });
            html += '</tbody></table></div>';
            list.innerHTML = html;
        }

        const select = document.getElementById('modelMakeSelect');
        const currentVal = select.value;
        select.innerHTML = '<option value="">-- Select Make --</option>';
        makes.forEach(m => {
            select.innerHTML += `<option value="${m.id}">${escapeHtml(m.name)}</option>`;
        });
        if (currentVal) select.value = currentVal;
    } catch (err) {
        showToast('Failed to load makes: ' + (err.message || 'Unknown error'), 'error');
    }
}

async function addMake() {
    const input = document.getElementById('newMakeName');
    const name = input.value.trim();
    if (!name) {
        showToast('Please enter a make name', 'error');
        return;
    }
    try {
        await apiPost('makes', { name });
        input.value = '';
        showToast('Make added successfully!', 'success');
        renderMakes();
    } catch (err) {
        showToast('Failed to add make: ' + (err.message || 'Unknown error'), 'error');
    }
}

async function deleteMake(id) {
    if (!confirm('Are you sure you want to delete this make? All associated models will also be deleted.')) return;
    try {
        await apiDelete('makes/' + id);
        showToast('Make deleted successfully!', 'success');
        renderMakes();
    } catch (err) {
        showToast('Failed to delete make: ' + (err.message || 'Unknown error'), 'error');
    }
}

function onModelMakeChange() {
    renderModels();
}

async function renderModels() {
    const makeId = document.getElementById('modelMakeSelect').value;
    const list = document.getElementById('modelsList');
    if (!makeId) {
        list.innerHTML = '<p style="color:var(--text-muted);">Select a make above to view its models.</p>';
        return;
    }
    try {
        const models = await apiGet('models?make_id=' + makeId);
        if (models.length === 0) {
            list.innerHTML = '<p style="color:var(--text-muted);">No models defined for this make.</p>';
        } else {
            let html = '<div class="table-responsive"><table class="striped"><thead><tr><th>Model</th><th>Action</th></tr></thead><tbody>';
            models.forEach(m => {
                html += `<tr>
                    <td>${escapeHtml(m.name)}</td>
                    <td><button class="btn btn-sm btn-outline-danger" onclick="deleteModel(${m.id})"><i class="fa-solid fa-trash-can"></i> Delete</button></td>
                </tr>`;
            });
            html += '</tbody></table></div>';
            list.innerHTML = html;
        }
    } catch (err) {
        showToast('Failed to load models: ' + (err.message || 'Unknown error'), 'error');
    }
}

async function addModel() {
    const makeId = document.getElementById('modelMakeSelect').value;
    const input = document.getElementById('newModelName');
    const name = input.value.trim();
    if (!makeId) {
        showToast('Please select a make first', 'error');
        return;
    }
    if (!name) {
        showToast('Please enter a model name', 'error');
        return;
    }
    try {
        await apiPost('models', { make_id: parseInt(makeId), name });
        input.value = '';
        showToast('Model added successfully!', 'success');
        renderModels();
    } catch (err) {
        showToast('Failed to add model: ' + (err.message || 'Unknown error'), 'error');
    }
}

async function deleteModel(id) {
    if (!confirm('Are you sure you want to delete this model?')) return;
    try {
        await apiDelete('models/' + id);
        showToast('Model deleted successfully!', 'success');
        renderModels();
    } catch (err) {
        showToast('Failed to delete model: ' + (err.message || 'Unknown error'), 'error');
    }
}

/* ==================== TEST EMAIL ==================== */
async function testEmailConfig() {
    const email = prompt('Enter a test email address to send a test email to:');
    if (!email) return;

    try {
        const result = await apiPost('settings/test-email', { email: email.trim() });
        showToast(result.message, result.success ? 'success' : 'error');
    } catch (err) {
        showToast('Test email failed: ' + (err.message || 'Unknown error'), 'error');
    }
}
