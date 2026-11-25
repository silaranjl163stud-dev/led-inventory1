<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Deployments — JMACX LED Inventory</title>
    <meta name="description" content="Manage LED hardware deployment events.">
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
                <h1 class="page-title">Deployments</h1>
                <button class="btn btn-sm primary" id="new-deploy-btn">
                    <ion-icon name="add-outline"></ion-icon> New Event
                </button>
            </header>

            <main class="page-content" id="main-content">
                <!-- Create Deployment (collapsible) -->
                <div id="panel-create" class="section-card" style="display:none;">
                    <div class="section-card-title">Create New Deployment Event</div>
                    <form id="create-deployment-form">
                        <div class="form-group">
                            <label class="form-label" for="deploy-name">Event Name</label>
                            <input class="form-input" type="text" id="deploy-name" placeholder="e.g. JMACX CON 2026" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label" for="deploy-location">Location</label>
                            <input class="form-input" type="text" id="deploy-location" placeholder="e.g. SM Mall of Asia Arena" required>
                        </div>
                        <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
                            <div class="form-group">
                                <label class="form-label" for="deploy-start">Start Date</label>
                                <input class="form-input" type="date" id="deploy-start">
                            </div>
                            <div class="form-group">
                                <label class="form-label" for="deploy-end">End Date</label>
                                <input class="form-input" type="date" id="deploy-end">
                            </div>
                        </div>
                        <div style="display:flex;gap:10px;">
                            <button type="submit" class="btn primary">
                                <ion-icon name="checkmark-circle-outline"></ion-icon> Create
                            </button>
                            <button type="button" class="btn" id="cancel-create-btn">Cancel</button>
                        </div>
                    </form>
                </div>

                <!-- Deployments List -->
                <div class="section-card">
                    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;">
                        <div class="section-card-title" style="margin-bottom:0;">Active Events</div>
                        <button class="btn btn-sm" id="refresh-btn">
                            <ion-icon name="refresh-outline"></ion-icon> Refresh
                        </button>
                    </div>
                    <ul class="item-list" id="deployments-list">
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
    <script src="assets/js/pages/deployments.js"></script>
</body>
</html>
