/**
 * scanner-page.js — Scanner page logic
 * Intercepts item-scanned events from scanner.js and redirects to profile.php
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

    Nav.init('scanner', user);

    // Focus hardware input on any click when on scanner page
    document.addEventListener('click', (e) => {
        const hwInput = document.getElementById('hardware-scanner-input');
        if (hwInput && e.target !== hwInput) hwInput.focus();
    });

    // Override scanner.js onScanSuccess to redirect
    window.addEventListener('item-scanned', async (e) => {
        const qrCode = e.detail;
        Toast.show(`Scanning: ${qrCode}…`);

        const result = await api.scanItem(qrCode);
        if (result && result.found) {
            Toast.show('Item found! Redirecting…');
            // Redirect to profile with item type + id
            window.location.href = `profile.php?type=${encodeURIComponent(result.item_type)}&id=${encodeURIComponent(result.item.id)}`;
        } else {
            Toast.show('Item not found in database', 'error');
        }
    });

    // Start camera
    if (window.Scanner) {
        window.Scanner.startCamera();
    }
})();
