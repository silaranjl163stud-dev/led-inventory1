function initOrdersSidebar() {
    const menuToggle = document.getElementById('menu-toggle');
    const sidebar = document.getElementById('sidebar');
    const overlay = document.getElementById('sidebar-overlay');
    menuToggle?.addEventListener('click', () => { sidebar.classList.toggle('open'); overlay.classList.toggle('visible'); });
    overlay?.addEventListener('click', () => { sidebar.classList.remove('open'); overlay.classList.remove('visible'); });
}

function escapeOrderHTML(value) {
    const element = document.createElement('span');
    element.textContent = value == null ? '' : String(value);
    return element.innerHTML;
}

function orderStatusBadge(status) {
    const label = escapeOrderHTML(String(status || '').replace(/_/g, ' '));
    return `<span class="badge badge-${escapeOrderHTML(status)}">${label}</span>`;
}

let ordersUser;

async function loadCustomerOrders() {
    const container = document.getElementById('orders-list');
    container.innerHTML = '<p class="orders-empty">Loading purchase requests…</p>';
    const orders = await api.getOrders();
    if (!orders) {
        container.innerHTML = '<p class="orders-error">Could not load purchase requests. Please refresh and try again.</p>';
        return;
    }
    if (!orders.length) {
        container.innerHTML = '<section class="section-card orders-empty">No purchase requests yet.</section>';
        return;
    }

    const isAdmin = ordersUser.role === 'admin';
    container.innerHTML = orders.map(order => {
        const items = (order.items || []).map(item => `
            <li>${escapeOrderHTML(item.quantity)} × ${escapeOrderHTML(item.manufacturer)} ${escapeOrderHTML(item.model_name)}
                <span class="order-item-type">${escapeOrderHTML(String(item.item_type).replace(/_/g, ' '))}</span>
            </li>
        `).join('');
        const adminControls = isAdmin && order.status === 'pending' ? `
            <div class="order-review">
                <label class="form-label" for="order-notes-${escapeOrderHTML(order.id)}">Quote / note for customer</label>
                <textarea class="form-input" rows="3" maxlength="4000" id="order-notes-${escapeOrderHTML(order.id)}"
                    placeholder="Add a quote or update (optional)"></textarea>
                <div class="order-review-actions">
                    <button class="btn primary" type="button" data-order-decision="accepted" data-order-id="${escapeOrderHTML(order.id)}">Accept &amp; mark sold</button>
                    <button class="btn btn-danger" type="button" data-order-decision="declined" data-order-id="${escapeOrderHTML(order.id)}">Decline</button>
                </div>
            </div>
        ` : '';
        const customer = isAdmin ? `<p class="order-customer">${escapeOrderHTML(order.customer_name)} · ${escapeOrderHTML(order.customer_email)}</p>` : '';
        const notes = order.admin_notes
            ? `<div class="order-notes"><strong>Admin quote / note:</strong><p>${escapeOrderHTML(order.admin_notes)}</p></div>`
            : '';
        return `
            <section class="section-card order-card">
                <div class="order-heading">
                    <div>
                        <h2>${escapeOrderHTML(order.request_number)}</h2>
                        <p>${escapeOrderHTML(new Date(order.created_at).toLocaleString())}</p>
                    </div>
                    ${orderStatusBadge(order.status)}
                </div>
                ${customer}
                <ul class="order-items">${items}</ul>
                ${notes}
                ${adminControls}
            </section>
        `;
    }).join('');

    if (isAdmin) {
        container.querySelectorAll('[data-order-decision]').forEach(button => {
            button.addEventListener('click', async () => {
                const id = button.dataset.orderId;
                const decision = button.dataset.orderDecision;
                const notes = document.getElementById(`order-notes-${id}`)?.value || '';
                if (!window.confirm(`${decision === 'accepted' ? 'Accept this request and mark its units sold' : 'Decline this request'}?`)) return;
                button.disabled = true;
                const result = await api.decideOrder(id, decision, notes);
                if (!result) {
                    button.disabled = false;
                    Toast.show('Could not review this request. Stock may have changed; refresh and try again.', 'error');
                    return;
                }
                Toast.show(`Purchase request ${decision}`);
                await loadCustomerOrders();
            });
        });
    }
}

(async () => {
    ordersUser = await AuthGuard.check();
    if (!ordersUser) return;
    if (!['customer', 'admin'].includes(ordersUser.role)) {
        window.location.href = 'dashboard.php';
        return;
    }

    initOrdersSidebar();
    Nav.init('orders', ordersUser);
    if (ordersUser.role === 'admin') {
        document.getElementById('orders-title').textContent = 'Customer Purchase Requests';
        document.getElementById('orders-intro').remove();
    }
    document.getElementById('refresh-orders-btn').addEventListener('click', loadCustomerOrders);
    await loadCustomerOrders();
})();
