<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard — JMACX LED Inventory</title>
    <meta name="description" content="LED Inventory dashboard — real-time overview of cabinet, module, and repair statistics.">
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
                <h1 class="page-title">Dashboard</h1>
                <button class="btn btn-sm" id="refresh-btn" title="Refresh stats">
                    <ion-icon name="refresh-outline"></ion-icon>
                </button>
            </header>

            <main class="page-content" id="main-content">
                <!-- Stats -->
                <div class="stat-grid">
                    <div class="stat-card">
                        <div class="stat-value" id="stat-cabinets">—</div>
                        <div class="stat-label">Cabinets</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-value" id="stat-modules">—</div>
                        <div class="stat-label">Modules</div>
                    </div>
                    <div class="stat-card alert">
                        <div class="stat-value" id="stat-repairs">—</div>
                        <div class="stat-label">Repairs Pending</div>
                    </div>
                </div>

                <!-- Quick Actions -->
                <div class="section-card">
                    <div class="section-card-title">Quick Actions</div>
                    <div style="display:flex;gap:10px;flex-wrap:wrap;">
                        <a href="scanner.php" class="btn primary">
                            <ion-icon name="scan-outline"></ion-icon> Scan Item
                        </a>
                        <a href="stocks.php" class="btn">
                            <ion-icon name="add-circle-outline"></ion-icon> Add Stock
                        </a>
                        <a href="deployments.php" class="btn">
                            <ion-icon name="airplane-outline"></ion-icon> Deployments
                        </a>
                        <a href="repairs.php" class="btn">
                            <ion-icon name="construct-outline"></ion-icon> Repairs
                        </a>
                    </div>
                </div>

                <!-- Recent Activity -->
                <div class="section-card">
                    <div class="section-card-title">System Status</div>
                    <div id="status-content" style="color:var(--text-secondary);font-size:0.875rem;">
                        Loading…
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
    <script src="assets/js/pages/dashboard.js"></script>
</body>
</html>
