/**
 * deployment-detail.js — Deployment detail page
 * Reads ?id= from URL
 */
function initMobileSidebar() {
    const menuToggle = document.getElementById('menu-toggle');
    const sidebar = document.getElementById('sidebar');
    const overlay = document.getElementById('sidebar-overlay');
    menuToggle?.addEventListener('click', () => { sidebar.classList.toggle('open'); overlay.classList.toggle('visible'); });
    overlay?.addEventListener('click', () => { sidebar.classList.remove('open'); overlay.classList.remove('visible'); });
}

function statusBadge(status) {
    return `<span class="badge badge-${status}">${status}</span>`;
}

(async () => {
    const user = await AuthGuard.check();
    if (!user) return;
    initMobileSidebar();
    Nav.init('deployments', user);

    const params = new URLSearchParams(window.location.search);
    const id = params.get('id');
    if (!id) {
        window.location.href = 'deployments.php';
        return;
    }

    const container = document.getElementById('deployment-detail-content');

    async function load() {
        const d = await api.getDeployment(id);
        if (!d) {
            container.innerHTML = '<div style="padding:40px;text-align:center;color:var(--text-secondary);">Deployment not found.</div>';
            return;
        }

        document.getElementById('page-title-text').textContent = d.name;

        const statuses = ['planned','active','completed','cancelled'];
        const statusOpts = statuses.map(s => `<option value="${s}" ${d.status===s?'selected':''}>${s}</option>`).join('');

        container.innerHTML = `
            <div class="profile-card">
                <div class="profile-title">Deployment Info</div>
                <div class="profile-row"><span>Event Name</span><span>${d.name}</span></div>
                <div class="profile-row"><span>Location</span><span>${d.location}</span></div>
                <div class="profile-row"><span>Start Date</span><span>${d.start_date || 'TBD'}</span></div>
                <div class="profile-row"><span>End Date</span><span>${d.end_date || 'TBD'}</span></div>
                <div class="profile-row" style="flex-direction:column;align-items:flex-start;gap:8px;border-bottom:none;">
                    <span>Status</span>
                    <div style="display:flex;gap:8px;width:100%;">
                        <select class="form-select" id="deploy-status-select">${statusOpts}</select>
                        <button class="btn primary" id="update-deploy-status-btn">Update</button>
                    </div>
                </div>
            </div>

            <div class="profile-card">
                <div class="profile-title">Assign Hardware</div>
                <div style="display:flex;gap:8px;margin-bottom:16px;">
                    <input class="form-input" type="text" id="deploy-assign-qr" placeholder="Scan or type QR code…">
                    <button class="btn primary" id="deploy-assign-btn">Assign</button>
                </div>
                <div id="deploy-items-list"></div>
            </div>
        `;

        document.getElementById('update-deploy-status-btn')?.addEventListener('click', async () => {
            const newStatus = document.getElementById('deploy-status-select').value;
            const res = await api.updateDeploymentStatus(id, newStatus);
            if (res) Toast.show('Status updated!');
            else Toast.show('Failed to update status', 'error');
        });

        document.getElementById('deploy-assign-btn')?.addEventListener('click', async () => {
            const qr = document.getElementById('deploy-assign-qr').value.trim();
            if (!qr) return Toast.show('Enter item QR', 'error');
            const scanRes = await api.scanItem(qr);
            if (!scanRes?.found) return Toast.show('Item not found', 'error');
            if (scanRes.item.status === 'deployed') return Toast.show('Already deployed', 'warning');
            const res = await api.assignDeploymentItem(id, scanRes.item_type, scanRes.item.id);
            if (res) { Toast.show('Item assigned!'); load(); }
            else Toast.show('Failed to assign', 'error');
        });

        // Render items
        const itemsList = document.getElementById('deploy-items-list');
        if (d.items?.length) {
            itemsList.innerHTML = '<div style="display:flex;flex-direction:column;gap:8px;">' +
                d.items.map(i => `
                    <div class="glass" style="display:flex;justify-content:space-between;align-items:center;padding:10px;border-radius:8px;">
                        <div>
                            <div style="font-size:0.72rem;color:var(--accent-glow);">${i.item_type.toUpperCase()}</div>
                            <div style="font-weight:600;">${i.qr_code}</div>
                        </div>
                        <button class="btn btn-sm btn-danger remove-item-btn" data-type="${i.item_type}" data-id="${i.item_id}">Remove</button>
                    </div>
                `).join('') + '</div>';

            document.querySelectorAll('.remove-item-btn').forEach(btn => {
                btn.addEventListener('click', async (e) => {
                    if (!confirm('Remove this item from deployment?')) return;
                    const res = await api.removeDeploymentItem(id, btn.dataset.type, btn.dataset.id);
                    if (res) { Toast.show('Item removed'); load(); }
                    else Toast.show('Failed to remove', 'error');
                });
            });
        } else {
            itemsList.innerHTML = '<div style="color:var(--text-secondary);font-size:0.875rem;">No items assigned yet.</div>';
        }
    }

    load();
})();
