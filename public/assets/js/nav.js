/**
 * nav.js — Renders sidebar (desktop) and bottom nav (mobile) on every protected page.
 * Call Nav.init(activePage, user) after AuthGuard.check().
 */
const Nav = {
    _items: [
        { id: 'dashboard',   label: 'Dashboard',   icon: 'home-outline',      href: 'dashboard.php' },
        { id: 'scanner',     label: 'Scanner',     icon: 'scan-outline',      href: 'scanner.php'   },
        { id: 'stocks',      label: 'Stocks',      icon: 'cube-outline',      href: 'stocks.php'    },
        { id: 'deployments', label: 'Deployments',  icon: 'airplane-outline', href: 'deployments.php' },
        { id: 'repairs',     label: 'Repairs',     icon: 'construct-outline', href: 'repairs.php'   },
        { id: 'settings',    label: 'Settings',    icon: 'settings-outline',  href: 'settings.php'  },
    ],

    _getItems(user) {
        if (user && user.role === 'customer') {
            return [
                { id: 'shop', label: 'Shop', icon: 'cart-outline', href: 'shop.php' },
                { id: 'orders', label: 'My Orders', icon: 'receipt-outline', href: 'orders.php' },
                { id: 'repairs', label: 'Repair Tickets', icon: 'construct-outline', href: 'repairs.php' }
            ];
        }
        const items = [...this._items];
        if (user && user.role === 'admin') {
            items.push({ id: 'orders', label: 'Customer Orders', icon: 'receipt-outline', href: 'orders.php' });
            items.push({ id: 'admin', label: 'Admin', icon: 'shield-outline', href: 'admin.php' });
        }
        return items;
    },

    init(activePage, user) {
        this._active = activePage;
        this._user   = user;
        this._renderSidebar();
        this._renderBottomNav();
    },

    _renderSidebar() {
        const sidebar = document.getElementById('sidebar');
        if (!sidebar) return;
        const items = this._getItems(this._user);
        const user  = this._user;

        sidebar.innerHTML = `
            <div class="sidebar-brand">
                <div class="brand-icon"><ion-icon name="layers-outline"></ion-icon></div>
                <div class="brand-text">
                    <div class="brand-name">JMACX LED</div>
                    <div class="brand-sub">Inventory System</div>
                </div>
            </div>

            <nav class="sidebar-nav">
                ${items.map(item => `
                    <a href="${item.href}"
                       class="sidebar-nav-item ${this._active === item.id ? 'active' : ''}"
                       id="snav-${item.id}">
                        <ion-icon name="${item.icon}"></ion-icon>
                        <span>${item.label}</span>
                    </a>
                `).join('')}
            </nav>

            <div class="sidebar-footer">
                ${user ? `
                    <div class="sidebar-user">
                        <div class="user-avatar-icon">
                            <ion-icon name="person-circle-outline"></ion-icon>
                        </div>
                        <div class="user-meta">
                            <div class="user-name">${user.username}</div>
                            <div class="user-role">${user.role}</div>
                        </div>
                    </div>
                    ${user.role !== 'customer' ? `<a href="settings.php" class="sidebar-logout-link" title="Settings">
                        <ion-icon name="cog-outline"></ion-icon>
                    </a>` : ''}
                    <a href="index.php" class="sidebar-logout-link" id="sidebar-logout-btn" title="Log out" aria-label="Log out">
                        <ion-icon name="log-out-outline"></ion-icon>
                    </a>
                ` : ''}
            </div>
        `;
        this._bindLogout(document.getElementById('sidebar-logout-btn'));
    },

    _renderBottomNav() {
        const nav = document.getElementById('bottom-nav');
        if (!nav) return;
        const mobileItems = this._user?.role === 'customer'
            ? this._getItems(this._user)
            : this._getItems(this._user).filter(i => ['dashboard','scanner','stocks','deployments','repairs','orders'].includes(i.id));
        mobileItems.push({ id: 'logout', label: 'Log out', icon: 'log-out-outline', href: 'index.php' });

        nav.innerHTML = mobileItems.map(item => `
            <a href="${item.href}"
               class="nav-item ${this._active === item.id ? 'active' : ''}"
               id="bnav-${item.id}">
                ${item.id === 'scanner'
                    ? `<div class="scan-fab"><ion-icon name="${item.icon}"></ion-icon></div>`
                    : `<ion-icon name="${item.icon}"></ion-icon><span>${item.label}</span>`
                }
            </a>
        `).join('');
        this._bindLogout(document.getElementById('bnav-logout'));
    },

    _bindLogout(link) {
        link?.addEventListener('click', event => {
            event.preventDefault();
            AuthGuard.logout();
        });
    }
};
