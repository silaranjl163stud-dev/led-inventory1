<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard — JMACX LED Inventory</title>
    <meta name="description" content="Admin panel for managing system users.">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700&display=swap" rel="stylesheet">
    <script type="module" src="https://unpkg.com/ionicons@7.1.0/dist/ionicons/ionicons.esm.js"></script>
    <script nomodule src="https://unpkg.com/ionicons@7.1.0/dist/ionicons/ionicons.js"></script>
    <link rel="stylesheet" href="assets/css/variables.css">
    <link rel="stylesheet" href="assets/css/layout.css">
    <link rel="stylesheet" href="assets/css/components.css">
</head>
<body>
    <div class="app-shell">
        <aside class="sidebar glass" id="sidebar"></aside>
        <div class="sidebar-overlay" id="sidebar-overlay"></div>

        <div class="main-wrapper">
            <header class="page-header glass">
                <button class="menu-toggle" id="menu-toggle" aria-label="Open menu">
                    <ion-icon name="menu-outline"></ion-icon>
                </button>
                <h1 class="page-title">Admin Dashboard</h1>
            </header>

            <main class="page-content" id="main-content">
                <!-- Create User -->
                <div class="section-card">
                    <div class="section-card-title">Create New User</div>
                    <form id="create-user-form">
                        <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
                            <div class="form-group">
                                <label class="form-label" for="new-username">Username</label>
                                <input class="form-input" type="text" id="new-username" required autocomplete="off">
                            </div>
                            <div class="form-group">
                                <label class="form-label" for="new-password">Password</label>
                                <input class="form-input" type="password" id="new-password" required autocomplete="new-password">
                            </div>
                        </div>
                        <div class="form-group">
                            <label class="form-label" for="new-full-name">Full name</label>
                            <input class="form-input" type="text" id="new-full-name" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label" for="new-email">Email</label>
                            <input class="form-input" type="email" id="new-email">
                        </div>
                        <div class="form-group">
                            <label class="form-label" for="new-role">Role</label>
                            <select class="form-select" id="new-role">
                                <option value="staff">Staff</option>
                                <option value="customer">Customer</option>
                                <option value="manager">Manager</option>
                                <option value="warehouse">Warehouse</option>
                                <option value="technician">Technician</option>
                                <option value="admin">Admin</option>
                            </select>
                        </div>
                        <button type="submit" class="btn primary">
                            <ion-icon name="person-add-outline"></ion-icon> Create User
                        </button>
                    </form>
                </div>

                <!-- Users List -->
                <div class="section-card">
                    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;">
                        <div class="section-card-title" style="margin-bottom:0;">System Users</div>
                        <button class="btn btn-sm" id="refresh-users-btn">
                            <ion-icon name="refresh-outline"></ion-icon>
                        </button>
                    </div>

                    <div class="section-card" id="edit-user-card" hidden>
                        <div class="section-card-title">Edit User</div>
                        <form id="edit-user-form">
                            <input type="hidden" id="edit-user-id">
                            <div class="form-group">
                                <label class="form-label" for="edit-username">Username</label>
                                <input class="form-input" id="edit-username" maxlength="50" required>
                            </div>
                            <div class="form-group">
                                <label class="form-label" for="edit-full-name">Full name</label>
                                <input class="form-input" id="edit-full-name" required>
                            </div>
                            <div class="form-group">
                                <label class="form-label" for="edit-email">Email</label>
                                <input class="form-input" id="edit-email" type="email">
                            </div>
                            <div class="form-group">
                                <label class="form-label" for="edit-role">Role</label>
                                <select class="form-select" id="edit-role">
                                    <option value="admin">Admin</option>
                                    <option value="manager">Manager</option>
                                    <option value="warehouse">Warehouse</option>
                                    <option value="technician">Technician</option>
                                    <option value="staff">Staff</option>
                                    <option value="customer">Customer</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label class="form-label" for="edit-password">New password (leave blank to keep current)</label>
                                <input class="form-input" id="edit-password" type="password" minlength="8" autocomplete="new-password">
                            </div>
                            <label class="form-label" style="display:flex;align-items:center;gap:8px;margin-bottom:16px;">
                                <input type="checkbox" id="edit-active"> Active account
                            </label>
                            <button class="btn primary" type="submit">Save Changes</button>
                            <button class="btn" type="button" id="cancel-edit-user">Cancel</button>
                        </form>
                    </div>
                    <ul class="item-list" id="users-list">
                        <li style="padding:20px;text-align:center;color:var(--text-secondary);">Loading…</li>
                    </ul>
                </div>
            </main>
        </div>
    </div>

    <nav class="bottom-nav glass" id="bottom-nav"></nav>
    <div id="toast" class="toast hidden"></div>

    <script src="assets/js/api.js"></script>
    <script src="assets/js/auth.js"></script>
    <script src="assets/js/toast.js"></script>
    <script src="assets/js/nav.js"></script>
    <script src="assets/js/pages/admin.js"></script>
</body>
</html>
