/**
 * admin.js — Admin Dashboard page (admin-only)
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

let users = [];

async function loadUsers() {
    const list = document.getElementById('users-list');
    list.innerHTML = '<li style="padding:20px;text-align:center;color:var(--text-secondary);">Loading…</li>';
    const result = await api.getUsers();
    if (!result) {
        list.innerHTML = '<li style="padding:20px;text-align:center;color:var(--danger);">Could not load users. Please try again.</li>';
        return;
    }
    users = result;
    if (!users.length) {
        list.innerHTML = '<li style="padding:20px;text-align:center;color:var(--text-secondary);">No users found.</li>';
        return;
    }
    list.innerHTML = users.map(u => `
        <li class="list-item" style="cursor:default;">
            <div style="display:flex;justify-content:space-between;align-items:center;">
                <div>
                    <div style="font-weight:600;">${escapeHTML(u.full_name)} · ${escapeHTML(u.username)}</div>
                    <div style="font-size:0.78rem;color:var(--text-secondary);text-transform:capitalize;">
                        ${escapeHTML(u.role)} · ${u.is_active ? 'Active' : 'Inactive'}${u.email ? ` · ${escapeHTML(u.email)}` : ''}
                    </div>
                </div>
                <div style="display:flex;gap:8px;flex-shrink:0;">
                <button class="btn btn-sm" type="button" data-edit-user="${Number(u.id)}">
                    <ion-icon name="create-outline"></ion-icon> Edit
                </button>
                <button class="btn btn-sm btn-danger" type="button" data-delete-user="${Number(u.id)}">
                    <ion-icon name="ban-outline"></ion-icon> Deactivate
                </button>
                </div>
            </div>
        </li>
    `).join('');
}

function editUser(id) {
    const user = users.find(candidate => Number(candidate.id) === Number(id));
    if (!user) return;
    document.getElementById('edit-user-id').value = user.id;
    document.getElementById('edit-username').value = user.username;
    document.getElementById('edit-full-name').value = user.full_name || '';
    document.getElementById('edit-email').value = user.email || '';
    document.getElementById('edit-role').value = user.role;
    document.getElementById('edit-password').value = '';
    document.getElementById('edit-active').checked = Boolean(Number(user.is_active));
    const card = document.getElementById('edit-user-card');
    card.hidden = false;
    card.scrollIntoView({ behavior: 'smooth', block: 'start' });
}

async function deleteUser(id, username) {
    if (!confirm(`Deactivate user "${username}"? They will no longer be able to sign in.`)) return;
    const res = await api.deleteUser(id);
    if (res) { Toast.show('User deactivated'); loadUsers(); }
    else Toast.show('Failed to delete user', 'error');
}

(async () => {
    // requireAdmin = true — redirects non-admins to their permitted landing page
    const user = await AuthGuard.check(true);
    if (!user) return;
    initMobileSidebar();
    Nav.init('admin', user);

    document.getElementById('create-user-form')?.addEventListener('submit', async (e) => {
        e.preventDefault();
        const data = {
            username: document.getElementById('new-username').value.trim(),
            password: document.getElementById('new-password').value,
            full_name: document.getElementById('new-full-name').value.trim(),
            email: document.getElementById('new-email').value.trim() || null,
            role:     document.getElementById('new-role').value,
        };
        const res = await api.createUser(data);
        if (res?.id) {
            Toast.show('User created successfully');
            e.target.reset();
            loadUsers();
        } else {
            Toast.show('Failed to create user', 'error');
        }
    });

    document.getElementById('users-list')?.addEventListener('click', event => {
        const editButton = event.target.closest('[data-edit-user]');
        if (editButton) {
            editUser(editButton.dataset.editUser);
            return;
        }

        const deleteButton = event.target.closest('[data-delete-user]');
        if (deleteButton) {
            const targetUser = users.find(candidate => Number(candidate.id) === Number(deleteButton.dataset.deleteUser));
            if (targetUser) deleteUser(targetUser.id, targetUser.username);
        }
    });

    document.getElementById('edit-user-form')?.addEventListener('submit', async event => {
        event.preventDefault();
        const password = document.getElementById('edit-password').value;
        const data = {
            username: document.getElementById('edit-username').value.trim(),
            full_name: document.getElementById('edit-full-name').value.trim(),
            email: document.getElementById('edit-email').value.trim(),
            role: document.getElementById('edit-role').value,
            is_active: document.getElementById('edit-active').checked,
        };
        if (password) data.password = password;

        const result = await api.updateUser(document.getElementById('edit-user-id').value, data);
        if (!result) {
            Toast.show('Failed to update user', 'error');
            return;
        }
        Toast.show('User updated');
        document.getElementById('edit-user-card').hidden = true;
        event.target.reset();
        await loadUsers();
    });

    document.getElementById('cancel-edit-user')?.addEventListener('click', () => {
        document.getElementById('edit-user-card').hidden = true;
        document.getElementById('edit-user-form').reset();
    });

    document.getElementById('refresh-users-btn')?.addEventListener('click', loadUsers);
    loadUsers();
})();
