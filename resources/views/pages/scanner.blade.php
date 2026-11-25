<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Scanner — JMACX LED Inventory</title>
    <meta name="description" content="Scan QR codes or barcodes to look up LED hardware items.">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700&display=swap" rel="stylesheet">
    <script type="module" src="https://unpkg.com/ionicons@7.1.0/dist/ionicons/ionicons.esm.js"></script>
    <script nomodule src="https://unpkg.com/ionicons@7.1.0/dist/ionicons/ionicons.js"></script>
    <script src="https://unpkg.com/html5-qrcode" type="text/javascript"></script>
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
                <h1 class="page-title">Scan Item</h1>
            </header>

            <main class="page-content" id="main-content">
                <div class="scanner-container">
                    <div id="reader"></div>

                    <div class="divider"><span>OR</span></div>

                    <div class="hardware-scan-area">
                        <ion-icon name="barcode-outline"></ion-icon>
                        <p style="color:var(--text-secondary);font-size:0.875rem;">Ready for physical/USB scanner</p>
                        <input type="text" id="hardware-scanner-input"
                               placeholder="Tap here to focus — or auto-detected on USB scanner input"
                               autocomplete="off">
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
    <script src="assets/js/scanner.js"></script>
    <script src="assets/js/pages/scanner-page.js"></script>
</body>
</html>
