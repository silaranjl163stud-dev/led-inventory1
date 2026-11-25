function initShopSidebar() {
    const menuToggle = document.getElementById('menu-toggle');
    const sidebar = document.getElementById('sidebar');
    const overlay = document.getElementById('sidebar-overlay');
    menuToggle?.addEventListener('click', () => { sidebar.classList.toggle('open'); overlay.classList.toggle('visible'); });
    overlay?.addEventListener('click', () => { sidebar.classList.remove('open'); overlay.classList.remove('visible'); });
}

function escapeShopHTML(value) {
    const element = document.createElement('span');
    element.textContent = value == null ? '' : String(value);
    return element.innerHTML;
}

async function loadShopCatalog() {
    const container = document.getElementById('shop-catalog');
    const submitButton = document.getElementById('submit-order-btn');
    const catalog = await api.getShopCatalog();
    if (!catalog) {
        container.innerHTML = '<p class="orders-error">Could not load available products. Please refresh and try again.</p>';
        return;
    }
    if (!catalog.length) {
        container.innerHTML = '<p class="orders-empty">There are no products in available stock right now.</p>';
        return;
    }

    container.innerHTML = catalog.map(product => `
        <label class="shop-product">
            <span class="shop-product-info">
                <strong>${escapeShopHTML(product.model_name)}</strong>
                <span>${escapeShopHTML(product.manufacturer)} · ${escapeShopHTML(String(product.item_type).replace(/_/g, ' '))}</span>
                <span>${escapeShopHTML(product.available_count)} available</span>
            </span>
            <span class="shop-quantity">
                <span class="form-label">Quantity</span>
                <input class="form-input" type="number" min="0" max="${escapeShopHTML(product.available_count)}"
                    value="0" data-model-id="${escapeShopHTML(product.model_id)}" aria-label="Quantity for ${escapeShopHTML(product.model_name)}">
            </span>
        </label>
    `).join('');
    submitButton.disabled = false;
}

(async () => {
    const user = await AuthGuard.check();
    if (!user) return;
    if (user.role !== 'customer') {
        window.location.href = user.role === 'admin' ? 'orders.php' : 'dashboard.php';
        return;
    }

    initShopSidebar();
    Nav.init('shop', user);
    document.getElementById('shop-form').addEventListener('submit', async event => {
        event.preventDefault();
        const button = document.getElementById('submit-order-btn');
        const items = [];
        let invalidQuantity = false;
        document.querySelectorAll('[data-model-id]').forEach(input => {
            const quantity = Number(input.value);
            const max = Number(input.max);
            if (!Number.isInteger(quantity) || quantity < 0 || quantity > max) {
                invalidQuantity = true;
            } else if (quantity > 0) {
                items.push({ model_id: Number(input.dataset.modelId), quantity });
            }
        });

        if (invalidQuantity) {
            Toast.show('Enter a whole quantity within the available stock', 'error');
            return;
        }
        if (!items.length) {
            Toast.show('Choose at least one product', 'error');
            return;
        }

        button.disabled = true;
        const result = await api.createOrderRequest({ items });
        button.disabled = false;
        if (!result) {
            Toast.show('Could not submit the request. Available stock may have changed; please retry.', 'error');
            return;
        }

        Toast.show(`${result.request_number} submitted for review`);
        document.querySelectorAll('[data-model-id]').forEach(input => { input.value = '0'; });
        window.location.href = 'orders.php';
    });

    await loadShopCatalog();
})();
