/**
 * profile.js — Item Profile page
 * Reads ?type= and ?id= from URL.
 * Also handles post-scan redirect from scanner.php.
 */
function initMobileSidebar() {
    const menuToggle = document.getElementById('menu-toggle');
    const sidebar = document.getElementById('sidebar');
    const overlay = document.getElementById('sidebar-overlay');
    menuToggle?.addEventListener('click', () => { sidebar.classList.toggle('open'); overlay.classList.toggle('visible'); });
    overlay?.addEventListener('click', () => { sidebar.classList.remove('open'); overlay.classList.remove('visible'); });
}

function statusBadge(status) {
    return `<span class="badge badge-${status}">${status.replace(/_/g,' ')}</span>`;
}

function timelineIcon(action) {
    const map = { create:'add-circle-outline', install:'hardware-chip-outline', remove:'close-circle-outline', status_change:'refresh-outline', deploy:'airplane-outline', repair:'construct-outline' };
    return map[action] || 'ellipse-outline';
}

(async () => {
    const user = await AuthGuard.check();
    if (!user) return;
    initMobileSidebar();

    const params = new URLSearchParams(window.location.search);
    const itemType = params.get('type');
    const itemId   = params.get('id');

    if (!itemType || !itemId) {
        document.getElementById('profile-content').innerHTML =
            '<div style="padding:40px;text-align:center;color:var(--text-secondary);">No item specified. <a href="scanner.php" style="color:var(--accent-glow);">Scan an item</a>.</div>';
        Nav.init('scanner', user);
        return;
    }

    Nav.init('stocks', user);

    const container = document.getElementById('profile-content');

    async function loadItem() {
        let item;
        if (itemType === 'cabinet') {
            item = await api.getCabinet(itemId);
        } else {
            const pluralMap = { module:'modules', power_supply:'power_supplies', receiving_card:'receiving_cards', hub:'hubs', video_processor:'video_processors' };
            item = await api.getComponent(pluralMap[itemType] || itemType+'s', itemId);
        }

        if (!item) {
            container.innerHTML = '<div style="padding:40px;text-align:center;color:var(--text-secondary);">Item not found.</div>';
            return;
        }

        // Build details rows (excluding internal fields)
        const excludeFields = ['id','qr_code','status','created_at','updated_at','cabinet_id','custom_properties'];
        let detailsHtml = '';
        for (const [key, value] of Object.entries(item)) {
            if (!excludeFields.includes(key) && value !== null && value !== '') {
                const label = key.replace(/_/g,' ').replace(/\b\w/g, l => l.toUpperCase());
                detailsHtml += `<div class="profile-row"><span>${label}</span><span>${value}</span></div>`;
            }
        }

        // Custom properties
        if (item.custom_properties) {
            try {
                const cp = typeof item.custom_properties === 'string' ? JSON.parse(item.custom_properties) : item.custom_properties;
                for (const [key, value] of Object.entries(cp)) {
                    if (value !== null && value !== '') {
                        const label = key.replace(/_/g,' ').replace(/\b\w/g, l => l.toUpperCase());
                        detailsHtml += `<div class="profile-row"><span>${label} <span style="color:var(--accent-glow);font-size:0.7rem;">(Custom)</span></span><span>${value}</span></div>`;
                    }
                }
            } catch (e) { /* silent */ }
        }

        const statuses = ['available','installed','deployed','defective','under_repair','retired'];
        const statusOpts = statuses.map(s => `<option value="${s}" ${item.status===s?'selected':''}>${s.replace(/_/g,' ')}</option>`).join('');

        document.getElementById('profile-page-title').textContent = `${itemType.replace(/_/g,' ')} Profile`;

        container.innerHTML = `
            <div class="profile-card">
                <div style="display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:14px;">
                    <div class="profile-title" style="margin-bottom:0;">${itemType.replace(/_/g,' ').toUpperCase()}</div>
                    <div style="display:flex;gap:8px;">
                        <button class="btn btn-sm" id="print-label-btn">
                            <ion-icon name="print-outline"></ion-icon> Print
                        </button>
                        ${item.status !== 'sold' ? `<button class="btn btn-sm btn-danger" id="report-defect-btn">
                            <ion-icon name="construct-outline"></ion-icon> Repair
                        </button>` : ''}
                    </div>
                </div>
                <div class="profile-row"><span>ID</span><span>${item.id}</span></div>
                <div class="profile-row"><span>QR Code</span><span style="font-family:monospace;color:var(--accent-glow);">${item.qr_code}</span></div>
                ${detailsHtml}
                ${item.status === 'sold' ? `
                <div class="profile-row" style="flex-direction:column;align-items:flex-start;gap:8px;border-bottom:none;">
                    <span>Status — ${statusBadge(item.status)}</span>
                    <span style="color:var(--text-secondary);font-size:0.82rem;">Sold items cannot be changed through inventory status controls.</span>
                </div>
                ` : `<div class="profile-row" style="flex-direction:column;align-items:flex-start;gap:8px;border-bottom:none;">
                    <span>Status — ${statusBadge(item.status)}</span>
                    <div style="display:flex;gap:8px;width:100%;">
                        <select class="form-select" id="update-status-select">${statusOpts}</select>
                        <button class="btn primary" id="update-status-btn">Update</button>
                    </div>
                </div>`}
            </div>

            ${itemType === 'cabinet' ? `
            <div class="profile-card" id="cabinet-assembly-card">
                <div class="profile-title">Cabinet Assembly</div>
                <div style="margin-bottom:16px;padding-bottom:16px;border-bottom:1px solid var(--border-glass);">
                    <div style="font-size:0.78rem;color:var(--text-secondary);margin-bottom:8px;">Install Component</div>
                    <div style="font-size:0.78rem;color:var(--text-secondary);margin-bottom:8px;">Scan an available module, power supply, receiving card, or hub to link it to this cabinet. Removing it returns it to available stock.</div>
                    <div style="display:flex;flex-direction:column;gap:8px;">
                        <input class="form-input" type="text" id="install-comp-qr" placeholder="Component QR code…">
                        <div style="display:flex;gap:8px;">
                            <input class="form-input" type="text" id="install-comp-slot" placeholder="Slot (optional)">
                            <button class="btn primary" id="install-comp-btn">Install</button>
                        </div>
                    </div>
                </div>
                <div style="font-size:0.78rem;color:var(--text-secondary);margin-bottom:8px;">Installed Components</div>
                <div id="cabinet-components-list">Loading components…</div>
            </div>
            ` : ''}

            <div class="profile-card">
                <div class="profile-title">Activity History</div>
                <div id="item-logs-list">Loading history…</div>
            </div>
        `;

        // Status update
        document.getElementById('update-status-btn')?.addEventListener('click', async () => {
            const newStatus = document.getElementById('update-status-select').value;
            const res = await api.updateStatus(itemType, item.id, newStatus);
            if (res) { Toast.show('Status updated!'); item.status = newStatus; }
            else Toast.show('Failed to update status', 'error');
        });

        // Report defect
        document.getElementById('report-defect-btn')?.addEventListener('click', async () => {
            const reason = prompt('Reason for repair:');
            if (!reason) return;
            const description = prompt('Describe the issue:');
            if (!description) return;
            const res = await api.createRepair({ item_id: item.id, reason, description });
            if (res) { Toast.show(`Ticket ${res.ticket || `#${res.id}`} submitted for review`); }
            else Toast.show('Failed to submit ticket', 'error');
        });

        // Print
        document.getElementById('print-label-btn')?.addEventListener('click', () => {
            Print.label(itemType, item);
        });

        // Cabinet components
        if (itemType === 'cabinet') {
            loadCabinetComponents(item.id);

            document.getElementById('install-comp-btn')?.addEventListener('click', async () => {
                const qr = document.getElementById('install-comp-qr').value.trim();
                const slot = document.getElementById('install-comp-slot').value.trim();
                if (!qr) return Toast.show('Enter component QR', 'error');
                const scanRes = await api.scanItem(qr);
                if (!scanRes?.found || scanRes.item_type === 'cabinet') return Toast.show('Invalid component QR', 'error');
                if (scanRes.item.status !== 'available') return Toast.show(`Cannot install: Component is ${scanRes.item.status}`, 'error');
                const res = await api.installComponent(item.id, scanRes.item_type, scanRes.item.id, slot);
                if (res) { Toast.show('Component installed!'); document.getElementById('install-comp-qr').value = ''; document.getElementById('install-comp-slot').value = ''; loadCabinetComponents(item.id); }
                else Toast.show('Failed to install', 'error');
            });
        }

        // Logs
        loadLogs(itemType, item.id);
    }

    async function loadCabinetComponents(cabinetId) {
        const listDiv = document.getElementById('cabinet-components-list');
        if (!listDiv) return;
        listDiv.innerHTML = 'Loading…';
        const details = await api.getCabinet(cabinetId);
        if (details?.components?.length) {
            listDiv.innerHTML = '<div style="display:flex;flex-direction:column;gap:8px;">' +
                details.components.map(c => `
                    <div class="glass" style="display:flex;justify-content:space-between;align-items:center;padding:10px;border-radius:8px;">
                        <div>
                            <div style="font-size:0.72rem;color:var(--accent-glow);">${c.component_type.toUpperCase()}</div>
                            <div style="font-weight:600;">${c.comp_qr_code}</div>
                            ${c.slot ? `<div style="font-size:0.78rem;color:var(--text-secondary);">Slot: ${c.slot}</div>` : ''}
                        </div>
                        <button class="btn btn-sm btn-danger remove-comp-btn" data-type="${c.component_type}" data-id="${c.component_id}">Remove</button>
                    </div>
                `).join('') + '</div>';

            document.querySelectorAll('.remove-comp-btn').forEach(btn => {
                btn.addEventListener('click', async () => {
                    if (!confirm('Remove this component?')) return;
                    const res = await api.removeComponent(cabinetId, btn.dataset.type, btn.dataset.id);
                    if (res) { Toast.show('Component removed'); loadCabinetComponents(cabinetId); }
                    else Toast.show('Failed to remove', 'error');
                });
            });
        } else {
            listDiv.innerHTML = '<div style="color:var(--text-secondary);font-size:0.875rem;">No components installed.</div>';
        }
    }

    async function loadLogs(type, id) {
        const listDiv = document.getElementById('item-logs-list');
        if (!listDiv) return;
        const logs = await api.getLogs(type, id);
        if (logs?.length) {
            listDiv.innerHTML = '<ul class="timeline">' +
                logs.map(l => `
                    <li class="timeline-item">
                        <ion-icon name="${timelineIcon(l.action)}" class="timeline-icon"></ion-icon>
                        <div>
                            <div class="timeline-date">${new Date(l.created_at).toLocaleString()}</div>
                            <div class="timeline-action">${l.action.replace(/_/g,' ')}</div>
                            ${l.notes ? `<div class="timeline-notes">${l.notes}</div>` : ''}
                        </div>
                    </li>
                `).join('') + '</ul>';
        } else {
            listDiv.innerHTML = '<div style="color:var(--text-secondary);font-size:0.875rem;">No history found.</div>';
        }
    }

    loadItem();
})();
