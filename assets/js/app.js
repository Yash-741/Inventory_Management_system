/**
 * Inventory Management System - Frontend Application
 * Handles live data fetching and DOM manipulation
 */

// Configuration
const API_ENDPOINT = 'api/inventory.php';
const REFRESH_INTERVAL = 30000; // 30 seconds
let refreshTimer;
const pendingStockUpdates = new Set();
let inventoryCache = [];
let quantityModalState = null;

/**
 * Initialize the application on page load
 */
document.addEventListener('DOMContentLoaded', function() {
    console.log('Dashboard initializing...');
    
    // Check authentication first
    checkAuthentication();
    
    // Setup user info after short delay to ensure session data is loaded
    setTimeout(() => {
        setupUserInfo();
        setupLogout();
        setupAddProductForm();
        setupInventorySearch();
        setupQuantityModal();
        fetchInventoryData();
        
        // Set up auto-refresh
        refreshTimer = setInterval(fetchInventoryData, REFRESH_INTERVAL);
    }, 500);
});

/**
 * Check if user is authenticated
 */
function checkAuthentication() {
    const authenticated = localStorage.getItem('authenticated') === 'true';
    
    if (!authenticated) {
        console.log('User not authenticated, redirecting to login');
        window.location.href = 'login.html';
        return;
    }
    
    // User is authenticated, load their info
    sessionStorage.setItem('authenticated', 'true');
    sessionStorage.setItem('user_id', localStorage.getItem('user_id'));
    sessionStorage.setItem('username', localStorage.getItem('username'));
    sessionStorage.setItem('role', localStorage.getItem('role'));
}

/**
 * Setup user info display
 */
function setupUserInfo() {
    const username = sessionStorage.getItem('username') || 'User';
    const role = sessionStorage.getItem('role') || '';
    const userDisplay = document.getElementById('userDisplay');
    
    if (userDisplay) {
        userDisplay.textContent = `${username}${role ? ' (' + role + ')' : ''}`;
    }
}

/**
 * Setup logout button
 */
function setupLogout() {
    const logoutBtn = document.getElementById('logoutBtn');
    if (logoutBtn) {
        logoutBtn.addEventListener('click', function(e) {
            e.preventDefault();
            
            // Clear auth storage
            localStorage.clear();
            sessionStorage.clear();
            
            // Redirect to login
            window.location.href = 'login.html';
        });
    }
}

/**
 * Fetch inventory data from the server
 */
function fetchInventoryData() {
    fetch(`${API_ENDPOINT}?action=get_all_inventory`, {
        method: 'GET',
        headers: {
            'Content-Type': 'application/json'
        }
    })
        .then(response => {
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            return response.json();
        })
        .then(data => {
            console.log('API Response:', data);
            if (data.success) {
                inventoryCache = Array.isArray(data.data.inventory) ? data.data.inventory : [];
                updateLiveStats(data.data.stats);
                renderInventoryTable();
                updateLastModified();
            } else {
                console.error('API Error:', data.message);
                showErrorMessage(data.message);
            }
        })
        .catch(error => {
            console.error('Fetch error:', error);
            showErrorMessage('Failed to load inventory data. Please check your connection.');
        });
}

/**
 * Update live statistics cards
 * @param {Object} stats - Statistics object from API
 */
function updateLiveStats(stats) {
    const elements = {
        'total-products': stats.total_products || 0,
        'in-stock': stats.in_stock || 0,
        'low-stock': stats.low_stock || 0,
        'out-of-stock': stats.out_of_stock || 0
    };

    for (const [elementId, value] of Object.entries(elements)) {
        const element = document.getElementById(elementId);
        if (element) {
            // Animate number change
            animateNumberChange(element, value);
        }
    }
}

/**
 * Animate number changes in stat cards
 * @param {HTMLElement} element - Target element
 * @param {number} targetValue - Target value to animate to
 */
function animateNumberChange(element, targetValue) {
    const currentValue = parseInt(element.textContent) || 0;
    
    if (currentValue === targetValue) return;

    const difference = targetValue - currentValue;
    const steps = 30;
    let currentStep = 0;
    const stepValue = difference / steps;

    const animationInterval = setInterval(() => {
        currentStep++;
        const newValue = Math.round(currentValue + stepValue * currentStep);
        element.textContent = newValue;

        if (currentStep >= steps) {
            element.textContent = targetValue;
            clearInterval(animationInterval);
        }
    }, 15);
}

/**
 * Update inventory table with product data
 * @param {Array} inventory - Array of inventory items
 */
function updateInventoryTable(inventory, emptyMessage = 'No inventory items found.') {
    const tbody = document.getElementById('inventory-tbody');
    
    if (!Array.isArray(inventory) || inventory.length === 0) {
        tbody.innerHTML = `
            <tr>
                <td colspan="7" class="loading-message">${escapeHtml(emptyMessage)}</td>
            </tr>
        `;
        return;
    }

    tbody.innerHTML = inventory.map(item => `
        <tr>
            <td>${escapeHtml(item.product_id)}</td>
            <td>
                <strong>${escapeHtml(item.product_name)}</strong>
                ${item.sku ? `<br><small style="color: #999;">SKU: ${escapeHtml(item.sku)}</small>` : ''}
            </td>
            <td>
                <div class="quantity-control">
                    <span class="quantity-display" id="qty-${item.product_id}" style="color: ${getQuantityColor(item.quantity, item.min_stock_level)}; font-weight: bold;">
                        ${item.quantity}
                    </span>
                </div>
            </td>
            <td>${item.min_stock_level || 'N/A'}</td>
            <td>
                <span class="status-badge ${getStatusClass(item.quantity, item.min_stock_level)}">
                    ${getStatusText(item.quantity, item.min_stock_level)}
                </span>
            </td>
            <td>
                <small>${formatDateTime(item.last_updated)}</small>
            </td>
            <td>
                <div class="action-buttons">
                    <button class="qty-edit-btn" onclick="editQuantity(${item.product_id}, '${escapeJsString(item.product_name)}', ${item.quantity}, this)">Update</button>
                    <button class="qty-delete-btn" onclick="deleteProduct(${item.product_id}, '${escapeJsString(item.product_name)}', this)">Delete</button>
                </div>
            </td>
        </tr>
    `).join('');
}

function renderInventoryTable() {
    const query = getInventorySearchQuery();

    if (!query) {
        updateInventoryTable(inventoryCache);
        return;
    }

    const filteredInventory = inventoryCache.filter(item => {
        const haystack = [
            item.product_id,
            item.product_name,
            item.sku,
            item.quantity,
            item.min_stock_level
        ]
            .filter(value => value !== null && typeof value !== 'undefined')
            .join(' ')
            .toLowerCase();

        return haystack.includes(query);
    });

    updateInventoryTable(filteredInventory, 'No matching inventory items found.');
}

function setupInventorySearch() {
    const searchInput = document.getElementById('inventorySearch');
    if (!searchInput) {
        return;
    }

    searchInput.addEventListener('input', renderInventoryTable);
}

function getInventorySearchQuery() {
    const searchInput = document.getElementById('inventorySearch');
    return searchInput ? searchInput.value.trim().toLowerCase() : '';
}

function setupAddProductForm() {
    const form = document.getElementById('addProductForm');
    const submitBtn = document.getElementById('addProductBtn');

    if (!form || !submitBtn) {
        return;
    }

    form.addEventListener('submit', function(e) {
        e.preventDefault();

        const formData = new FormData(form);
        const name = (formData.get('product_name') || '').toString().trim();
        const sku = (formData.get('sku') || '').toString().trim();
        const unitPrice = (formData.get('unit_price') || '0').toString().trim();
        const quantity = (formData.get('quantity') || '0').toString().trim();
        const minStock = (formData.get('min_stock_level') || '0').toString().trim();

        if (!name || !sku) {
            showError('Product name and SKU are required');
            return;
        }

        if (!/^\d+(\.\d{1,2})?$/.test(unitPrice)) {
            showError('Unit price must be a valid number');
            return;
        }

        if (!/^\d+$/.test(quantity) || !/^\d+$/.test(minStock)) {
            showError('Quantity and minimum stock must be whole numbers');
            return;
        }

        submitBtn.disabled = true;
        submitBtn.textContent = 'Adding...';

        const payload = new URLSearchParams();
        payload.append('product_name', name);
        payload.append('sku', sku);
        payload.append('unit_price', unitPrice);
        payload.append('quantity', quantity);
        payload.append('min_stock_level', minStock);
        payload.append('description', (formData.get('description') || '').toString().trim());

        fetch(`${API_ENDPOINT}?action=add_product`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded'
            },
            body: payload.toString()
        })
        .then(response => {
            if (!response.ok) {
                throw new Error(`HTTP ${response.status}`);
            }
            return response.json();
        })
        .then(data => {
            if (!data.success) {
                throw new Error(data.message || 'Failed to add product');
            }
            showSuccess(`Product added: ${name}`);
            form.reset();
            document.getElementById('unitPrice').value = '0';
            document.getElementById('productQuantity').value = '0';
            document.getElementById('minStockLevel').value = '0';
            fetchInventoryData();
        })
        .catch(error => {
            showError(error.message || 'Failed to add product');
        })
        .finally(() => {
            submitBtn.disabled = false;
            submitBtn.textContent = 'Add Product';
        });
    });
}

function deleteProduct(productId, productName, triggerButton = null) {
    if (pendingStockUpdates.has(productId)) {
        showError('An operation is already in progress for this product');
        return;
    }

    const confirmed = window.confirm(`Delete product "${productName}"?\nThis action cannot be undone.`);
    if (!confirmed) {
        return;
    }

    pendingStockUpdates.add(productId);
    if (triggerButton) {
        triggerButton.disabled = true;
        triggerButton.textContent = 'Deleting...';
    }

    const payload = new URLSearchParams();
    payload.append('product_id', productId);

    fetch(`${API_ENDPOINT}?action=delete_product`, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded'
        },
        body: payload.toString()
    })
    .then(response => {
        if (!response.ok) {
            throw new Error(`HTTP ${response.status}`);
        }
        return response.json();
    })
    .then(data => {
        if (!data.success) {
            throw new Error(data.message || 'Failed to delete product');
        }
        showSuccess(`Deleted: ${productName}`);
        fetchInventoryData();
    })
    .catch(error => {
        showError(error.message || 'Failed to delete product');
    })
    .finally(() => {
        pendingStockUpdates.delete(productId);
        if (triggerButton) {
            triggerButton.disabled = false;
            triggerButton.textContent = 'Delete';
        }
    });
}

/**
 * Determine status class based on stock level
 * @param {number} quantity - Current quantity
 * @param {number} minStock - Minimum stock level
 * @returns {string} CSS class name
 */
function getStatusClass(quantity, minStock) {
    if (quantity === 0) return 'status-out-of-stock';
    if (quantity <= minStock) return 'status-low-stock';
    return 'status-in-stock';
}

/**
 * Get status text based on stock level
 * @param {number} quantity - Current quantity
 * @param {number} minStock - Minimum stock level
 * @returns {string} Status text
 */
function getStatusText(quantity, minStock) {
    if (quantity === 0) return 'Out of Stock';
    if (quantity <= minStock) return 'Low Stock';
    return 'In Stock';
}

/**
 * Get color for quantity display
 * @param {number} quantity - Current quantity
 * @param {number} minStock - Minimum stock level
 * @returns {string} Hex color code
 */
function getQuantityColor(quantity, minStock) {
    if (quantity === 0) return '#f4a0a0';
    if (quantity <= minStock) return '#f7c97d';
    return '#95dfb7';
}

/**
 * Update last modified timestamp
 */
function updateLastModified() {
    const now = new Date();
    const timeString = now.toLocaleTimeString('en-US', {
        hour: '2-digit',
        minute: '2-digit',
        second: '2-digit',
        hour12: true
    });
    const dateString = now.toLocaleDateString('en-US', {
        month: 'short',
        day: 'numeric',
        year: 'numeric'
    });
    
    const updateTimeElement = document.getElementById('update-time');
    if (updateTimeElement) {
        updateTimeElement.textContent = `${timeString} on ${dateString}`;
    }
}

/**
 * Format date and time for display
 * @param {string} dateString - ISO datetime string from database
 * @returns {string} Formatted date and time
 */
function formatDateTime(dateString) {
    try {
        const date = new Date(dateString);
        return date.toLocaleString('en-US', {
            month: 'short',
            day: 'numeric',
            hour: '2-digit',
            minute: '2-digit',
            hour12: true
        });
    } catch (e) {
        return dateString || 'N/A';
    }
}

/**
 * Escape HTML to prevent XSS attacks
 * @param {string} text - Text to escape
 * @returns {string} Escaped HTML text
 */
function escapeHtml(text) {
    if (!text) return '';
    const map = {
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#039;'
    };
    return text.toString().replace(/[&<>"']/g, m => map[m]);
}

function escapeJsString(text) {
    return (text || '')
        .toString()
        .replace(/\\/g, '\\\\')
        .replace(/'/g, "\\'")
        .replace(/\r/g, '\\r')
        .replace(/\n/g, '\\n')
        .replace(/</g, '\\x3C')
        .replace(/>/g, '\\x3E');
}

/**
 * Show error message to user
 * @param {string} message - Error message to display
 */
function showErrorMessage(message) {
    const tbody = document.getElementById('inventory-tbody');
    if (tbody) {
        tbody.innerHTML = `
            <tr>
                <td colspan="7" class="loading-message" style="color: #f44336;">
                    Error: ${escapeHtml(message)}
                </td>
            </tr>
        `;
    }
}

/**
 * Handle page visibility to pause refresh when tab is not visible
 */
document.addEventListener('visibilitychange', function() {
    if (document.hidden) {
        // Page is hidden - stop refresh
        clearInterval(refreshTimer);
    } else {
        // Page is visible - resume refresh
        fetchInventoryData();
        refreshTimer = setInterval(fetchInventoryData, REFRESH_INTERVAL);
    }
});

/**
 * Window cleanup on page unload
 */
window.addEventListener('beforeunload', function() {
    if (refreshTimer) {
        clearInterval(refreshTimer);
    }
});

/**
 * Edit product quantity
 * @param {number} productId - Product ID
 * @param {string} productName - Product name
 * @param {number} currentQty - Current quantity
 */
function editQuantity(productId, productName, currentQty, triggerButton = null) {
    if (pendingStockUpdates.has(productId)) {
        showError('An update is already in progress for this product');
        return;
    }

    openQuantityModal(productId, productName, currentQty, triggerButton);
}

/**
 * Update product quantity via API
 * @param {number} productId - Product ID
 * @param {number} newQuantity - New quantity
 */
function updateProductQuantity(productId, newQuantity, triggerButton = null) {
    if (pendingStockUpdates.has(productId)) {
        return;
    }

    pendingStockUpdates.add(productId);
    closeQuantityModal();

    if (triggerButton) {
        triggerButton.disabled = true;
        triggerButton.textContent = 'Saving...';
    }

    const payload = new URLSearchParams();
    payload.append('product_id', productId);
    payload.append('quantity', newQuantity);

    fetch(`${API_ENDPOINT}?action=update_quantity`, {
        method: 'POST',
        body: payload.toString(),
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded'
        }
    })
    .then(response => {
        if (!response.ok) {
            throw new Error(`HTTP ${response.status}`);
        }
        return response.json();
    })
    .then(data => {
        if (data.success) {
            const oldQty = data.data && typeof data.data.old_quantity !== 'undefined' ? data.data.old_quantity : null;
            const newQty = data.data && typeof data.data.new_quantity !== 'undefined' ? data.data.new_quantity : newQuantity;
            const summary = oldQty === null ? `${newQty}` : `${oldQty} -> ${newQty}`;
            showSuccess(`Quantity updated: ${summary}`);
            // Refresh data
            setTimeout(() => {
                fetchInventoryData();
            }, 500);
        } else {
            showError(data.message || 'Failed to update quantity');
        }
    })
    .catch(error => {
        console.error('Update error:', error);
        showError('Failed to update quantity. Please try again.');
    })
    .finally(() => {
        pendingStockUpdates.delete(productId);
        if (triggerButton) {
            triggerButton.disabled = false;
            triggerButton.textContent = 'Update';
        }
    });
}

/**
 * Show error notification
 * @param {string} message - Error message
 */
function showError(message) {
    showToast('Error', message, 'error');
}

/**
 * Show success notification
 * @param {string} message - Success message
 */
function showSuccess(message) {
    showToast('Success', message, 'success');
}

function setupQuantityModal() {
    const modal = document.getElementById('quantityModal');
    const form = document.getElementById('quantityForm');
    const input = document.getElementById('quantityInput');
    const closeButtons = document.querySelectorAll('[data-close-quantity-modal]');

    if (!modal || !form || !input) {
        return;
    }

    closeButtons.forEach(button => {
        button.addEventListener('click', closeQuantityModal);
    });

    modal.addEventListener('click', event => {
        if (event.target === modal) {
            closeQuantityModal();
        }
    });

    document.addEventListener('keydown', event => {
        if (event.key === 'Escape' && modal.classList.contains('is-open')) {
            closeQuantityModal();
        }
    });

    form.addEventListener('submit', event => {
        event.preventDefault();

        if (!quantityModalState) {
            return;
        }

        const normalized = input.value.trim();
        if (!/^\d+$/.test(normalized)) {
            showError('Please enter a whole number (0 or greater)');
            input.focus();
            return;
        }

        const newQuantity = Number(normalized);
        if (newQuantity < 0) {
            showError('Quantity cannot be negative');
            input.focus();
            return;
        }

        if (newQuantity === quantityModalState.currentQty) {
            closeQuantityModal();
            return;
        }

        updateProductQuantity(
            quantityModalState.productId,
            newQuantity,
            quantityModalState.triggerButton
        );
    });
}

function openQuantityModal(productId, productName, currentQty, triggerButton = null) {
    const modal = document.getElementById('quantityModal');
    const input = document.getElementById('quantityInput');
    const title = document.getElementById('quantityModalTitle');
    const description = document.getElementById('quantityModalDescription');
    const productField = document.getElementById('quantityProductId');

    if (!modal || !input || !title || !description || !productField) {
        return;
    }

    quantityModalState = { productId, productName, currentQty, triggerButton };
    title.textContent = `Update quantity for ${productName}`;
    description.textContent = `Current quantity: ${currentQty}. Enter the new stock count below.`;
    productField.value = String(productId);
    input.value = String(currentQty);
    modal.classList.add('is-open');
    modal.setAttribute('aria-hidden', 'false');
    document.body.style.overflow = 'hidden';
    setTimeout(() => {
        input.focus();
        input.select();
    }, 0);
}

function closeQuantityModal() {
    const modal = document.getElementById('quantityModal');
    if (!modal) {
        return;
    }

    modal.classList.remove('is-open');
    modal.setAttribute('aria-hidden', 'true');
    document.body.style.overflow = '';
    quantityModalState = null;
}

function showToast(title, message, variant) {
    const toastRegion = document.getElementById('toastRegion');
    if (!toastRegion) {
        return;
    }

    const toast = document.createElement('div');
    toast.className = `toast toast-${variant}`;
    toast.innerHTML = `
        <div class="toast-title">${escapeHtml(title)}</div>
        <div class="toast-message">${escapeHtml(message)}</div>
    `;

    toastRegion.appendChild(toast);

    setTimeout(() => {
        toast.remove();
    }, 4000);
}

