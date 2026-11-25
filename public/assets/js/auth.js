/**
 * auth.js — Shared authentication guard
 * Included on every protected page.
 */
const AuthGuard = {
    _user: null,

    /**
     * Call on DOMContentLoaded on every protected page.
     * @param {boolean} requireAdmin - if true, non-admins are redirected to dashboard
     * @returns {object|null} user object, or null (and redirects) if not authenticated
     */
    async check(requireAdmin = false) {
        const token = localStorage.getItem('auth_token');
        if (!token) {
            window.location.href = 'index.php';
            return null;
        }

        try {
            const res = await fetch('api/auth/me', {
                headers: { 'Authorization': `Bearer ${token}` }
            });

            if (!res.ok) {
                localStorage.removeItem('auth_token');
                window.location.href = 'index.php';
                return null;
            }

            const data = await res.json();
            this._user = data.user;

            const currentPage = window.location.pathname.split('/').pop();
            if (this._user.role === 'customer' && !['repairs.php', 'repair-detail.php', 'shop.php', 'orders.php'].includes(currentPage)) {
                window.location.href = 'repairs.php';
                return null;
            }

            if (requireAdmin && this._user.role !== 'admin') {
                window.location.href = this._user.role === 'customer' ? 'repairs.php' : 'dashboard.php';
                return null;
            }

            return this._user;
        } catch (e) {
            console.error('Auth check failed:', e);
            localStorage.removeItem('auth_token');
            window.location.href = 'index.php';
            return null;
        }
    },

    /** Returns the cached user object (after check() has been called) */
    getUser() {
        return this._user;
    },

    /** Calls logout API and clears local state */
    async logout() {
        try {
            const token = localStorage.getItem('auth_token');
            if (token) {
                await fetch('api/auth/logout', {
                    method: 'POST',
                    headers: { 'Authorization': `Bearer ${token}` }
                });
            }
        } catch (e) { /* silent */ }
        localStorage.removeItem('auth_token');
        window.location.href = 'index.php';
    }
};
