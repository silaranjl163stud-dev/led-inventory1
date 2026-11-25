/**
 * dashboard.js — Dashboard page logic
 */
(async () => {
    const user = await AuthGuard.check();
    if (!user) return;

    // Mobile sidebar toggle
    const menuToggle = document.getElementById('menu-toggle');
    const sidebar = document.getElementById('sidebar');
    const overlay = document.getElementById('sidebar-overlay');
    menuToggle?.addEventListener('click', () => {
        sidebar.classList.toggle('open');
        overlay.classList.toggle('visible');
    });
    overlay?.addEventListener('click', () => {
        sidebar.classList.remove('open');
        overlay.classList.remove('visible');
    });

    Nav.init('dashboard', user);

    async function loadStats() {
        const stats = await api.getStats();
        if (stats) {
            const el = (id) => document.getElementById(id);
            if (el('stat-cabinets')) el('stat-cabinets').textContent = stats.cabinets ?? '—';
            if (el('stat-modules'))  el('stat-modules').textContent  = stats.modules  ?? '—';
            if (el('stat-repairs'))  el('stat-repairs').textContent  = stats.repairs_pending ?? '—';

            const statusEl = document.getElementById('status-content');
            if (statusEl) {
                statusEl.innerHTML = `
                    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:12px;">
                        <div style="padding:14px;background:var(--bg-glass);border-radius:10px;border:1px solid var(--border-glass);">
                            <div style="font-size:0.75rem;color:var(--text-secondary);margin-bottom:4px;">Total Cabinets</div>
                            <div style="font-weight:700;font-size:1.4rem;color:var(--accent-glow);">${stats.cabinets ?? 0}</div>
                        </div>
                        <div style="padding:14px;background:var(--bg-glass);border-radius:10px;border:1px solid var(--border-glass);">
                            <div style="font-size:0.75rem;color:var(--text-secondary);margin-bottom:4px;">Total Modules</div>
                            <div style="font-weight:700;font-size:1.4rem;color:var(--accent-glow);">${stats.modules ?? 0}</div>
                        </div>
                        <div style="padding:14px;background:var(--bg-glass);border-radius:10px;border:1px solid var(--border-glass);">
                            <div style="font-size:0.75rem;color:var(--text-secondary);margin-bottom:4px;">Open Repairs</div>
                            <div style="font-weight:700;font-size:1.4rem;color:var(--danger);">${stats.repairs_pending ?? 0}</div>
                        </div>
                    </div>
                `;
            }
        }
    }

    document.getElementById('refresh-btn')?.addEventListener('click', loadStats);
    loadStats();
})();
