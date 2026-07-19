/* ==================== PRODUCTS MANAGEMENT ==================== */
async function renderProducts(searchTerm = '') {
    showLoading('productsTable', 'Loading products...');

    const isAdmin = currentUser && currentUser.role === 'admin';

    try {
        const endpoint = searchTerm ? `products?search=${encodeURIComponent(searchTerm)}` : 'products';
        const products = await apiGet(endpoint);
        let tableHTML = '<div class="table-responsive"><table class="striped"><thead><tr><th>ID</th><th>Name</th><th>Sell Price (LKR)</th><th>Cost Price (LKR)</th><th>Margin</th><th>Description</th>' + (isAdmin ? '<th>Actions</th>' : '') + '</tr></thead><tbody>';

        if (products.length === 0) {
            const colSpan = isAdmin ? 7 : 6;
            tableHTML += `<tr><td colspan="${colSpan}"><div class="empty-state"><i class="fa-solid fa-box-open"></i><h4>No Products Found</h4><p>${searchTerm ? `No products match "${searchTerm}"` : 'Add your first product or service to the catalog.'}</p></div></td></tr>`;
        } else {
            products.forEach(p => {
                const margin = p.unit_price > 0 ? ((p.unit_price - p.buy_price) / p.unit_price * 100).toFixed(1) : '0.0';
                const marginColor = margin >= 20 ? 'var(--success)' : margin >= 10 ? 'var(--accent)' : 'var(--danger)';
                let actionsHtml = '';
                if (isAdmin) {
                    actionsHtml = `
                            <div class="btn-group">
                                <button class="btn btn-sm btn-outline" onclick="editProduct(${p.id})"><i class="fa-solid fa-pen-to-square"></i> Edit</button>
                                <button class="btn btn-sm btn-danger" onclick="deleteProduct(${p.id})"><i class="fa-solid fa-trash"></i></button>
                            </div>`;
                }
                tableHTML += `
                    <tr>
                        <td><strong>#${p.id}</strong></td>
                        <td>${p.name}</td>
                        <td>LKR ${parseFloat(p.unit_price).toLocaleString('en-US', {minimumFractionDigits: 2})}</td>
                        <td>LKR ${parseFloat(p.buy_price).toLocaleString('en-US', {minimumFractionDigits: 2})}</td>
                        <td style="color:${marginColor};font-weight:600;">${margin}%</td>
                        <td>${p.description || '-'}</td>
                        ${isAdmin ? `<td>${actionsHtml}</td>` : ''}
                    </tr>`;
            });
        }
        tableHTML += '</tbody></table></div>';
        document.getElementById('productsTable').innerHTML = tableHTML;

        const addBtn = document.querySelector('#page-products .panel-header .btn-accent');
        if (addBtn) {
            addBtn.style.display = isAdmin ? '' : 'none';
        }
    } catch (err) {
        showError('productsTable', err.message || 'Failed to load products');
        showToast('Failed to load products', 'error');
    }
}

let cachedProducts = [];

async function loadProductsCache(searchTerm = '') {
    try {
        const endpoint = searchTerm ? `products?search=${encodeURIComponent(searchTerm)}` : 'products';
        cachedProducts = await apiGet(endpoint);
    } catch (e) { cachedProducts = []; }
}

function openProductModal(editId = null) {
    openModal('productModalOverlay');
    document.getElementById('productModalTitle').innerHTML = editId
        ? '<i class="fa-solid fa-pen-to-square"></i> Edit Product'
        : '<i class="fa-solid fa-box"></i> Add Product';
    document.getElementById('prodEditId').value = editId || '';

    if (editId) {
        const p = cachedProducts.find(pr => pr.id === editId);
        if (p) {
            document.getElementById('prodName').value = p.name;
            document.getElementById('prodPrice').value = p.unit_price;
            document.getElementById('prodBuyPrice').value = p.buy_price;
            document.getElementById('prodDesc').value = p.description || '';
        }
    } else {
        document.getElementById('productForm').reset();
        document.getElementById('prodEditId').value = '';
    }
}

document.addEventListener('DOMContentLoaded', function() {
    const productSearchInput = document.getElementById('productSearch');
    if (productSearchInput) {
        let searchTimeout;
        productSearchInput.addEventListener('input', function(e) {
            clearTimeout(searchTimeout);
            searchTimeout = setTimeout(() => {
                const searchTerm = e.target.value.trim();
                renderProducts(searchTerm);
                loadProductsCache(searchTerm);
            }, 300);
        });

        productSearchInput.addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                const searchTerm = e.target.value.trim();
                renderProducts(searchTerm);
                loadProductsCache(searchTerm);
            }
        });
    }
});

function closeProductModal() {
    closeModal('productModalOverlay');
}

async function saveProduct(e) {
    e.preventDefault();
    const editId = document.getElementById('prodEditId').value;
    const productData = {
        name: document.getElementById('prodName').value.trim(),
        unit_price: parseFloat(document.getElementById('prodPrice').value) || 0,
        buy_price: parseFloat(document.getElementById('prodBuyPrice').value) || 0,
        description: document.getElementById('prodDesc').value.trim(),
    };

    const submitBtn = e.target.querySelector('button[type="submit"]');
    const originalHTML = submitBtn.innerHTML;
    submitBtn.innerHTML = '<span class="spinner spinner-white"></span> Saving...';
    submitBtn.disabled = true;

    try {
        if (editId) {
            await apiPut('products/' + editId, productData);
        } else {
            await apiPost('products', productData);
        }
        closeProductModal();
        renderProducts();
        showToast(editId ? 'Product updated!' : 'Product added!', 'success');
    } catch (err) {
        showToast('Error: ' + (err.message || 'Failed to save product'), 'error');
        submitBtn.innerHTML = originalHTML;
        submitBtn.disabled = false;
    }
}

function editProduct(id) {
    openProductModal(id);
}

async function deleteProduct(id) {
    if (!confirm('Are you sure you want to delete this product?')) return;
    showLoading('productsTable', 'Deleting...');
    try {
        await apiDelete('products/' + id);
        renderProducts();
        showToast('Product deleted.', 'info');
    } catch (err) {
        showToast('Error: ' + (err.message || 'Failed to delete product'), 'error');
        renderProducts();
    }
}