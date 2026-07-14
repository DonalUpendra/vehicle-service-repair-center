let sendEmailCustomerId = null;

async function renderCustomers() {
    showLoading('customersTable', 'Loading customers...');

    try {
        const search = document.getElementById('customerSearchInput')?.value || '';
        const customers = await apiGet('customers' + (search ? '?search=' + encodeURIComponent(search) : ''));

        let html = `<div class="search-bar" style="margin-bottom:20px;">
            <div class="form-group"><label>Search customers</label>
            <input type="text" id="customerSearchInput" placeholder="Search by name, email, or registration number..." value="${search.replace(/"/g, '&quot;')}"></div>
            <button class="btn btn-primary" onclick="searchCustomers()"><i class="fa-solid fa-search"></i> Search</button>
        </div>`;

        html += '<div class="table-responsive"><table class="striped"><thead><tr>';
        html += '<th>Owner</th><th>Email</th><th>Phone</th><th>Vehicle</th><th>Reg No.</th><th>Last Visit</th><th>Action</th>';
        html += '</tr></thead><tbody>';

        if (customers.length === 0) {
            html += `<tr><td colspan="7"><div class="empty-state"><i class="fa-solid fa-users-slash"></i><h4>No Customers Found</h4><p>${search ? 'No customers match your search. Try a different query.' : 'Customers will appear here after vehicle check-ins.'}</p></div></td></tr>`;
        } else {
            const seen = new Set();
            customers.forEach(c => {
                if (!c.owner_email || seen.has(c.owner_email)) return;
                seen.add(c.owner_email);

                const vehicle = [c.make, c.model].filter(Boolean).join(' ') || 'N/A';
                const lastVisit = c.last_visit_date ? formatDate(c.last_visit_date) : '-';
                html += `<tr>
                    <td><strong>${c.owner_name || 'N/A'}</strong></td>
                    <td>${c.owner_email || '-'}</td>
                    <td>${c.owner_phone || '-'}</td>
                    <td>${vehicle}</td>
                    <td>${c.registration_number || 'N/A'}</td>
                    <td>${lastVisit}</td>
                    <td>
                        <button class="btn btn-sm btn-primary" onclick="openSendEmailModal(${c.id}, '${(c.owner_name || '').replace(/'/g, "\\'")}', '${(c.owner_email || '').replace(/'/g, "\\'")}')"><i class="fa-solid fa-envelope"></i> Send Email</button>
                    </td>
                </tr>`;
            });
        }

        html += '</tbody></table></div>';
        document.getElementById('customersTable').innerHTML = html;
    } catch (err) {
        showError('customersTable', err.message || 'Failed to load customers');
        showToast('Failed to load customers: ' + (err.message || 'Unknown error'), 'error');
    }
}

function searchCustomers() {
    renderCustomers();
}

function openSendEmailModal(customerId, customerName, customerEmail) {
    sendEmailCustomerId = customerId;
    document.getElementById('emailTo').value = customerEmail;
    document.getElementById('emailCustomerName').textContent = customerName;
    document.getElementById('emailSubject').value = 'Service Update — Lumina AutoWorks';
    document.getElementById('emailBody').value = getDefaultEmailBody(customerName);
    document.getElementById('emailSendBtn').disabled = false;
    document.getElementById('emailSendBtn').innerHTML = '<i class="fa-solid fa-paper-plane"></i> Send Email';
    openModal('emailModalOverlay');
}

function getDefaultEmailBody(customerName) {
    return `<p>Dear ${customerName},</p>

<p>Thank you for choosing our Lumina AutoWorks for your vehicle service needs.</p>

<p>Your vehicle is currently being serviced, and we will keep you updated on the progress. If you have any questions or concerns, please don't hesitate to reach out to us.</p>

<p>Best regards,<br>
Lumina AutoWorks</p>`;
}

async function sendCustomerEmail() {
    const subject = document.getElementById('emailSubject').value.trim();
    const body = document.getElementById('emailBody').value.trim();

    if (!subject || !body) {
        showToast('Please fill in both subject and body', 'warning');
        return;
    }

    const btn = document.getElementById('emailSendBtn');
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner spinner-white"></span> Sending...';

    try {
        const result = await apiPost('customers/' + sendEmailCustomerId + '/send-email', { subject, body });
        showToast('Email sent successfully to ' + result.customer_name, 'success');
        closeModal('emailModalOverlay');
    } catch (err) {
        showToast('Failed to send email: ' + (err.message || 'Unknown error'), 'error');
        btn.disabled = false;
        btn.innerHTML = '<i class="fa-solid fa-paper-plane"></i> Send Email';
    }
}

document.addEventListener('DOMContentLoaded', function() {
    const emailSendBtn = document.getElementById('emailSendBtn');
    if (emailSendBtn) {
        emailSendBtn.addEventListener('click', sendCustomerEmail);
    }
});
