/**
 * repair-detail.js — Repair ticket detail and review
 * Reads ?id= from URL
 */
function initMobileSidebar() {
    const menuToggle = document.getElementById('menu-toggle');
    const sidebar = document.getElementById('sidebar');
    const overlay = document.getElementById('sidebar-overlay');
    menuToggle?.addEventListener('click', () => { sidebar.classList.toggle('open'); overlay.classList.toggle('visible'); });
    overlay?.addEventListener('click', () => { sidebar.classList.remove('open'); overlay.classList.remove('visible'); });
}

function escapeHTML(value) {
    const element = document.createElement('span');
    element.textContent = value == null ? '' : String(value);
    return element.innerHTML;
}

function statusBadge(status) {
    const label = String(status || '').replace(/_/g, ' ');
    return `<span class="badge badge-${escapeHTML(status)}">${escapeHTML(label)}</span>`;
}

(async () => {
    const user = await AuthGuard.check();
    if (!user) return;
    initMobileSidebar();
    Nav.init('repairs', user);

    const canReview = ['admin', 'staff'].includes(user.role);
    const params = new URLSearchParams(window.location.search);
    const id = params.get('id');
    if (!id) { window.location.href = 'repairs.php'; return; }

    const container = document.getElementById('repair-detail-content');

    async function load() {
        const repair = await api.getRepair(id);
        if (!repair) {
            container.innerHTML = '<div style="padding:40px;text-align:center;color:var(--text-secondary);">Ticket not found or you do not have access to it.</div>';
            return;
        }

        const canWork = canReview && ['accepted', 'open', 'in_progress'].includes(repair.status);
        const statusOptions = repair.status === 'in_progress'
            ? ['resolved', 'cancelled']
            : ['in_progress', 'resolved', 'cancelled'];

        container.innerHTML = `
            <div class="profile-card">
                <div class="profile-title">Ticket ${escapeHTML(repair.ticket || `#${repair.id}`)}</div>
                <div class="profile-row"><span>Equipment</span><span>${escapeHTML(repair.item_type)} · <span style="font-family:monospace;">${escapeHTML(repair.qr_code)}</span></span></div>
                <div class="profile-row"><span>Filed by</span><span>${escapeHTML(repair.requester || 'Unknown')}</span></div>
                <div class="profile-row"><span>Opened</span><span>${escapeHTML(new Date(repair.created_at).toLocaleString())}</span></div>
                <div class="profile-row"><span>Status</span>${statusBadge(repair.status)}</div>
                ${repair.reviewer ? `<div class="profile-row"><span>Reviewed by</span><span>${escapeHTML(repair.reviewer)}${repair.reviewed_at ? ` · ${escapeHTML(new Date(repair.reviewed_at).toLocaleString())}` : ''}</span></div>` : ''}
                <div class="profile-row" style="flex-direction:column;align-items:flex-start;gap:6px;">
                    <span>Reason</span>
                    <div style="padding:10px;background:rgba(0,0,0,0.3);border-radius:8px;width:100%;font-size:0.875rem;white-space:pre-wrap;">${escapeHTML(repair.reason)}</div>
                </div>
                <div class="profile-row" style="flex-direction:column;align-items:flex-start;gap:6px;">
                    <span>Description</span>
                    <div style="padding:10px;background:rgba(0,0,0,0.3);border-radius:8px;width:100%;font-size:0.875rem;white-space:pre-wrap;">${escapeHTML(repair.issue_description)}</div>
                </div>
                ${canReview && repair.status === 'pending' ? `
                    <div style="display:flex;gap:10px;border-bottom:none;padding-top:12px;">
                        <button class="btn primary" id="accept-repair-btn" type="button">Accept ticket</button>
                        <button class="btn btn-danger" id="decline-repair-btn" type="button">Decline ticket</button>
                    </div>
                ` : ''}
                ${canWork ? `
                    <div class="profile-row" style="flex-direction:column;align-items:flex-start;gap:8px;border-bottom:none;">
                        <span>Repair workflow</span>
                        <div style="display:flex;gap:8px;width:100%;">
                            <select class="form-select" id="repair-status-select" required>
                                <option value="" selected disabled>Choose next status</option>
                                ${statusOptions.map(status => `<option value="${status}">${escapeHTML(status.replace('_', ' '))}</option>`).join('')}
                            </select>
                            <button class="btn primary" id="update-repair-status-btn" type="button">Update</button>
                        </div>
                    </div>
                ` : ''}
            </div>

            ${canWork ? `
                <div class="profile-card">
                    <div class="profile-title">Technician Actions</div>
                    <div style="display:flex;flex-direction:column;gap:10px;margin-bottom:20px;padding-bottom:16px;border-bottom:1px solid var(--border-glass);">
                        <input class="form-input" type="text" id="repair-action-input" maxlength="100" placeholder="Action taken (e.g. Replaced capacitor)">
                        <input class="form-input" type="text" id="repair-notes-input" placeholder="Additional notes (optional)">
                        <button class="btn primary" id="add-action-btn" type="button">
                            <ion-icon name="add-outline"></ion-icon> Log Action
                        </button>
                    </div>
                    <div id="repair-actions-list"></div>
                </div>
            ` : ''}
        `;

        ['accepted', 'declined'].forEach(decision => {
            const button = document.getElementById(`${decision === 'accepted' ? 'accept' : 'decline'}-repair-btn`);
            button?.addEventListener('click', async () => {
                if (!window.confirm(`${decision === 'accepted' ? 'Accept' : 'Decline'} this ticket?`)) return;
                button.disabled = true;
                const result = await api.decideRepair(id, decision);
                if (result) {
                    Toast.show(`Ticket ${decision}`);
                    await load();
                } else {
                    button.disabled = false;
                    Toast.show('Could not update this ticket', 'error');
                }
            });
        });

        document.getElementById('update-repair-status-btn')?.addEventListener('click', async () => {
            const newStatus = document.getElementById('repair-status-select').value;
            if (!newStatus) return Toast.show('Choose a status', 'error');
            const result = await api.updateRepairStatus(id, newStatus);
            if (result) {
                Toast.show('Status updated');
                await load();
            } else {
                Toast.show('Failed to update status', 'error');
            }
        });

        document.getElementById('add-action-btn')?.addEventListener('click', async () => {
            const action = document.getElementById('repair-action-input').value.trim();
            const notes = document.getElementById('repair-notes-input').value.trim();
            if (!action) return Toast.show('Action description required', 'error');
            const result = await api.addRepairAction(id, { action, notes });
            if (result) { Toast.show('Action logged'); await load(); }
            else Toast.show('Failed to log action', 'error');
        });

        const actionsList = document.getElementById('repair-actions-list');
        if (actionsList && repair.actions?.length) {
            actionsList.innerHTML = '<ul style="list-style:none;padding:0;margin:0;display:flex;flex-direction:column;gap:10px;">' +
                repair.actions.map(action => `
                    <li style="padding:12px;background:var(--bg-glass);border-radius:8px;border:1px solid var(--border-glass);">
                        <div style="font-weight:600;font-size:0.9rem;">${escapeHTML(action.action)}</div>
                        ${action.notes ? `<div style="font-size:0.82rem;color:#bbb;margin-top:3px;">${escapeHTML(action.notes)}</div>` : ''}
                        <div style="font-size:0.72rem;color:var(--text-secondary);margin-top:6px;">${escapeHTML(new Date(action.created_at).toLocaleString())}</div>
                    </li>
                `).join('') + '</ul>';
        } else if (actionsList) {
            actionsList.innerHTML = '<div style="color:var(--text-secondary);font-size:0.875rem;">No actions logged yet.</div>';
        }
    }

    load();
})();
