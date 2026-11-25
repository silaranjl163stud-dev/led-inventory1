/**
 * deployments.js — Deployments list page logic
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

async function loadDeployments() {
    const list = document.getElementById('deployments-list');
    list.innerHTML = '<li style="padding:20px;text-align:center;color:var(--text-secondary);">Loading…</li>';

    const deployments = await api.getDeployments();
    if (!deployments || !deployments.length) {
        list.innerHTML = '<li style="padding:20px;text-align:center;color:var(--text-secondary);">No deployments found.</li>';
        return;
    }

    list.innerHTML = deployments.map(d => `
        <li class="list-item" onclick="window.location.href='deployment-detail.php?id=${d.id}'">
            <div style="display:flex;justify-content:space-between;align-items:center;">
                <div class="list-item-title">${d.name}</div>
                ${statusBadge(d.status)}
            </div>
            <div class="list-item-sub">
                <ion-icon name="location-outline" style="vertical-align:middle;"></ion-icon>
                ${d.location}
            </div>
            <div class="list-item-sub" style="margin-top:3px;font-size:0.75rem;">
                ${d.start_date || 'TBD'} → ${d.end_date || 'TBD'}
            </div>
        </li>
    `).join('');
}

(async () => {
    const user = await AuthGuard.check();
    if (!user) return;
    initMobileSidebar();
    Nav.init('deployments', user);

    // Create form toggle
    const newBtn = document.getElementById('new-deploy-btn');
    const panel  = document.getElementById('panel-create');
    const cancelBtn = document.getElementById('cancel-create-btn');

    newBtn?.addEventListener('click', () => {
        panel.style.display = panel.style.display === 'none' ? '' : 'none';
    });
    cancelBtn?.addEventListener('click', () => { panel.style.display = 'none'; });

    document.getElementById('create-deployment-form')?.addEventListener('submit', async (e) => {
        e.preventDefault();
        const data = {
            name:       document.getElementById('deploy-name').value.trim(),
            location:   document.getElementById('deploy-location').value.trim(),
            start_date: document.getElementById('deploy-start').value,
            end_date:   document.getElementById('deploy-end').value,
        };
        const res = await api.createDeployment(data);
        if (res) {
            Toast.show('Deployment created!');
            e.target.reset();
            panel.style.display = 'none';
            loadDeployments();
        } else {
            Toast.show('Failed to create deployment', 'error');
        }
    });

    document.getElementById('refresh-btn')?.addEventListener('click', loadDeployments);
    loadDeployments();
})();
