<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Repairs — JMACX LED Inventory</title>
    <meta name="description" content="View and manage active repair tickets for LED hardware.">
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
                <h1 class="page-title">Repairs Dashboard</h1>
                <button class="btn btn-sm" id="refresh-btn">
                    <ion-icon name="refresh-outline"></ion-icon> Refresh
                </button>
            </header>

            <main class="page-content" id="main-content">
                <div class="section-card">
                    <div class="section-card-title">Submit a Repair Ticket</div>
                    <form id="create-repair-form">
                        <div class="form-group">
                            <label class="form-label" for="repair-item-qr">Equipment QR code</label>
                            <input class="form-input" type="text" id="repair-item-qr" maxlength="100" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label" for="repair-reason">Reason</label>
                            <textarea class="form-input" id="repair-reason" rows="2" maxlength="2000" required></textarea>
                        </div>
                        <div class="form-group">
                            <label class="form-label" for="repair-description">Description</label>
                            <textarea class="form-input" id="repair-description" rows="4" maxlength="10000" required></textarea>
                        </div>
                        <button class="btn primary" id="submit-repair-btn" type="submit">
                            <ion-icon name="paper-plane-outline"></ion-icon> Submit Ticket
                        </button>
                        <div class="list-item-sub" style="margin-top:10px;">Ticket numbers are assigned automatically. New tickets stay pending until staff or an admin reviews them.</div>
                    </form>
                </div>

                <!-- Filter bar -->
                <div class="section-card" style="padding:12px 16px;margin-bottom:16px;">
                    <div style="display:flex;gap:10px;align-items:center;flex-wrap:wrap;">
                        <label class="form-label" style="margin:0;white-space:nowrap;">Filter Status:</label>
                        <select class="form-select" id="repair-filter-status" style="width:auto;flex:1;min-width:140px;">
                            <option value="all">All</option>
                            <option value="pending" selected>Pending</option>
                            <option value="accepted">Accepted</option>
                            <option value="in_progress">In Progress</option>
                            <option value="declined">Declined</option>
                            <option value="open">Open (legacy)</option>
                            <option value="resolved">Resolved</option>
                            <option value="cancelled">Cancelled</option>
                        </select>
                    </div>
                </div>

                <div class="section-card">
                    <div class="section-card-title">Repair Tickets</div>
                    <ul class="item-list" id="repairs-list">
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
    <script src="assets/js/pages/repairs.js"></script>
</body>
</html>
