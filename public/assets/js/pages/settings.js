/**
 * settings.js — Settings page logic
 */
function initMobileSidebar() {
    const menuToggle = document.getElementById('menu-toggle');
    const sidebar = document.getElementById('sidebar');
    const overlay = document.getElementById('sidebar-overlay');
    menuToggle?.addEventListener('click', () => { sidebar.classList.toggle('open'); overlay.classList.toggle('visible'); });
    overlay?.addEventListener('click', () => { sidebar.classList.remove('open'); overlay.classList.remove('visible'); });
}

async function loadCustomFieldsList(type) {
    const listDiv = document.getElementById('cf-list');
    listDiv.innerHTML = '<div style="color:var(--text-secondary);font-size:0.875rem;">Loading…</div>';
    const fields = await api.getCustomFields(type);
    if (!fields?.length) {
        listDiv.innerHTML = '<div style="color:var(--text-secondary);font-size:0.85rem;">No custom fields defined.</div>';
        return;
    }
    listDiv.innerHTML = '<ul style="list-style:none;padding:0;margin:0;">' +
        fields.map(f => `
            <li style="display:flex;justify-content:space-between;align-items:center;
                       padding:10px 0;border-bottom:1px solid var(--border-glass);">
                <div>
                    <span style="font-weight:600;">${f.field_name}</span>
                    <span style="font-size:0.75rem;color:var(--text-secondary);margin-left:8px;">(${f.field_type})</span>
                </div>
                <button class="btn btn-sm btn-danger" onclick="deleteCustomField(${f.id})">
                    <ion-icon name="trash-outline"></ion-icon>
                </button>
            </li>
        `).join('') + '</ul>';
}

async function deleteCustomField(id) {
    if (!confirm('Delete this custom field? Existing data will be hidden.')) return;
    const res = await api.deleteCustomField(id);
    if (res) {
        Toast.show('Field deleted');
        loadCustomFieldsList(document.getElementById('cf-item-type').value);
    } else {
        Toast.show('Failed to delete field', 'error');
    }
}

window.deleteCustomField = deleteCustomField;

(async () => {
    const user = await AuthGuard.check();
    if (!user) return;
    initMobileSidebar();
    Nav.init('settings', user);

    // Populate user info
    document.getElementById('settings-username').textContent = user.username;
    document.getElementById('settings-role').textContent     = user.role;

    // Logout
    document.getElementById('logout-btn')?.addEventListener('click', () => AuthGuard.logout());

    // Custom fields — admin only
    if (user.role === 'admin') {
        document.getElementById('custom-fields-section').style.display = '';

        loadCustomFieldsList('cabinets');

        document.getElementById('cf-item-type')?.addEventListener('change', (e) => {
            loadCustomFieldsList(e.target.value);
        });

        document.getElementById('cf-add-btn')?.addEventListener('click', async () => {
            const item_type  = document.getElementById('cf-item-type').value;
            const field_name = document.getElementById('cf-field-name').value.trim();
            const field_type = document.getElementById('cf-field-type').value;
            if (!field_name) return Toast.show('Enter field name', 'error');
            const res = await api.addCustomField({ item_type, field_name, field_type });
            if (res) {
                Toast.show('Custom field added');
                document.getElementById('cf-field-name').value = '';
                loadCustomFieldsList(item_type);
            } else {
                Toast.show('Failed to add field', 'error');
            }
        });
    }
})();
