<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Item Profile — JMACX LED Inventory</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700&display=swap" rel="stylesheet">
    <script type="module" src="https://unpkg.com/ionicons@7.1.0/dist/ionicons/ionicons.esm.js"></script>
    <script nomodule src="https://unpkg.com/ionicons@7.1.0/dist/ionicons/ionicons.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/qrious/4.0.2/qrious.min.js"></script>
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
                <button class="btn btn-sm" onclick="history.back()">
                    <ion-icon name="arrow-back-outline"></ion-icon> Back
                </button>
                <h1 class="page-title" id="profile-page-title">Item Profile</h1>
            </header>

            <main class="page-content" id="main-content">
                <div id="profile-content">
                    <div style="text-align:center;padding:40px;color:var(--text-secondary);">Loading item…</div>
                </div>
            </main>
        </div>
    </div>

    <nav class="bottom-nav glass" id="bottom-nav"></nav>
    <div id="toast" class="toast hidden"></div>
    <div id="print-area"></div>

    <script src="assets/js/api.js"></script>
    <script src="assets/js/auth.js"></script>
    <script src="assets/js/toast.js"></script>
    <script src="assets/js/nav.js"></script>
    <script src="assets/js/print.js"></script>
    <script src="assets/js/pages/profile.js"></script>
</body>
</html>
