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
