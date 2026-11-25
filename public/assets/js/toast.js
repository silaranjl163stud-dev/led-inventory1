/**
 * toast.js — Shared toast notification utility
 */
const Toast = {
    _timer: null,

    /**
     * Show a toast notification.
     * @param {string} message
     * @param {'success'|'error'|'warning'} type
     */
    show(message, type = 'success') {
        let toast = document.getElementById('toast');
        if (!toast) {
            toast = document.createElement('div');
            toast.id = 'toast';
            document.body.appendChild(toast);
        }

        toast.textContent = message;
        // Reset classes
        toast.className = 'toast';
        if (type === 'error') {
            toast.style.backgroundColor = 'var(--danger)';
            toast.style.color = '#fff';
        } else if (type === 'warning') {
            toast.style.backgroundColor = '#f59e0b';
            toast.style.color = '#000';
        } else {
            toast.style.backgroundColor = 'var(--success)';
            toast.style.color = '#000';
        }

        clearTimeout(this._timer);
        this._timer = setTimeout(() => {
            toast.classList.add('hidden');
        }, 3000);
    }
};
