/**
 * repairs.js — Repair ticket submission and review
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
    return `<span class="badge badge-${escapeHTML(status)}">${escapeHTML(String(status).replace(/_/g, ' '))}</span>`;
}

let allRepairs = [];
let currentUser = null;

async function loadRepairs() {
    const list = document.getElementById('repairs-list');
    list.innerHTML = '<li style="padding:20px;text-align:center;color:var(--text-secondary);">Loading…</li>';
    const repairs = await api.getRepairs();
    if (!repairs) {
        list.innerHTML = '<li style="padding:20px;text-align:center;color:var(--danger);">Could not load repair tickets. Please try again.</li>';
        return;
    }
    allRepairs = repairs;
    renderRepairs();
}

function renderRepairs() {
    const list = document.getElementById('repairs-list');
    const statusFilter = document.getElementById('repair-filter-status')?.value || 'all';
    const filtered = statusFilter === 'all' ? allRepairs : allRepairs.filter(r => r.status === statusFilter);
    const canReview = currentUser && ['admin', 'staff'].includes(currentUser.role);

    if (!filtered.length) {
        list.innerHTML = '<li style="padding:20px;text-align:center;color:var(--text-secondary);">No tickets found.</li>';
        return;
    }

    list.innerHTML = filtered.map(r => {
        const description = String(r.issue_description || '');
        const summary = description.length > 120 ? `${description.substring(0, 120)}…` : description;
        const decisionButtons = canReview && r.status === 'pending'
            ? `<div style="display:flex;gap:8px;margin-top:12px;">
                    <button class="btn primary" type="button" data-ticket-id="${escapeHTML(r.id)}" data-decision="accepted">Accept</button>
                    <button class="btn btn-danger" type="button" data-ticket-id="${escapeHTML(r.id)}" data-decision="declined">Decline</button>
               </div>`
            : '';

        return `
            <li class="list-item" onclick="window.location.href='repair-detail.php?id=${encodeURIComponent(r.id)}'">
                <div style="display:flex;justify-content:space-between;align-items:center;gap:8px;">
                    <div class="list-item-title">Ticket ${escapeHTML(r.ticket || `#${r.id}`)}</div>
                    ${statusBadge(r.status)}
                </div>
                <div class="list-item-sub">
                    <ion-icon name="qr-code-outline" style="vertical-align:middle;"></ion-icon>
                    ${escapeHTML(r.qr_code)} · ${escapeHTML(r.item_type)}
                </div>
                ${r.requester ? `<div class="list-item-sub">Filed by ${escapeHTML(r.requester)}</div>` : ''}
                <div class="list-item-sub" style="margin-top:4px;"><strong>Reason:</strong> ${escapeHTML(r.reason)}</div>
                <div class="list-item-sub" style="margin-top:4px;"><strong>Description:</strong> ${escapeHTML(summary)}</div>
                ${decisionButtons}
            </li>
        `;
    }).join('');

    list.querySelectorAll('[data-decision]').forEach(button => {
        button.addEventListener('click', async event => {
            event.stopPropagation();
            const decision = button.dataset.decision;
            const ticketId = button.dataset.ticketId;
            if (!window.confirm(`${decision === 'accepted' ? 'Accept' : 'Decline'} this repair ticket?`)) return;

            button.disabled = true;
            const result = await api.decideRepair(ticketId, decision);
            if (result) {
                Toast.show(`Ticket ${decision}`);
                await loadRepairs();
            } else {
                button.disabled = false;
                Toast.show('Could not update this ticket', 'error');
            }
        });
    });
}

(async () => {
    currentUser = await AuthGuard.check();
    if (!currentUser) return;
    initMobileSidebar();
    Nav.init('repairs', currentUser);

    document.getElementById('refresh-btn')?.addEventListener('click', loadRepairs);
    document.getElementById('repair-filter-status')?.addEventListener('change', renderRepairs);
    document.getElementById('create-repair-form')?.addEventListener('submit', async event => {
        event.preventDefault();
        const form = event.currentTarget;
        const button = document.getElementById('submit-repair-btn');
        const itemQr = document.getElementById('repair-item-qr').value.trim();
        const reason = document.getElementById('repair-reason').value.trim();
        const description = document.getElementById('repair-description').value.trim();

        if (!itemQr || !reason || !description) {
            Toast.show('Complete all ticket fields', 'error');
            return;
        }

        button.disabled = true;
        const result = await api.createRepair({
            item_qr: itemQr,
            reason,
            description
        });
        button.disabled = false;

        if (!result) {
            Toast.show('Could not submit ticket. Check the QR code and try again.', 'error');
            return;
        }

        form.reset();
        document.getElementById('repair-filter-status').value = 'pending';
        Toast.show(`Ticket ${result.ticket || `#${result.id}`} submitted for review`);
        await loadRepairs();
    });

    loadRepairs();
})();
