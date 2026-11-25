<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Settings — JMACX LED Inventory</title>
    <meta name="description" content="User settings and custom field management.">
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
                <h1 class="page-title">Settings</h1>
            </header>

            <main class="page-content" id="main-content">
                <!-- Current User -->
                <div class="section-card">
                    <div class="section-card-title">Account</div>
                    <div class="profile-row">
                        <span>Username</span>
                        <span id="settings-username">—</span>
                    </div>
                    <div class="profile-row">
                        <span>Role</span>
                        <span id="settings-role">—</span>
                    </div>
                    <div style="margin-top:16px;">
                        <button class="btn btn-danger" id="logout-btn">
                            <ion-icon name="log-out-outline"></ion-icon> Sign Out
                        </button>
                    </div>
                </div>

                <!-- Custom Fields (admin only) -->
                <div class="section-card" id="custom-fields-section" style="display:none;">
                    <div class="section-card-title">Custom Fields Management</div>
                    <div style="display:flex;flex-direction:column;gap:12px;">
                        <div class="form-group">
                            <label class="form-label" for="cf-item-type">Item Type</label>
                            <select class="form-select" id="cf-item-type">
                                <option value="cabinets">Cabinets</option>
                                <option value="modules">Modules</option>
                                <option value="power_supplies">Power Supplies</option>
                                <option value="receiving_cards">Receiving Cards</option>
                                <option value="hubs">Hubs</option>
                                <option value="video_processors">Video Processors</option>
                            </select>
                        </div>
                        <div style="display:flex;gap:8px;flex-wrap:wrap;">
                            <input class="form-input" type="text" id="cf-field-name"
                                   placeholder="Field name (e.g. wattage)"
                                   style="flex:1;min-width:140px;">
                            <select class="form-select" id="cf-field-type" style="width:auto;">
                                <option value="text">Text</option>
                                <option value="number">Number</option>
                                <option value="date">Date</option>
                            </select>
                            <button class="btn primary" id="cf-add-btn">
                                <ion-icon name="add-outline"></ion-icon> Add
                            </button>
                        </div>
                        <div id="cf-list"></div>
                    </div>
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
    <script src="assets/js/pages/settings.js"></script>
</body>
</html>
