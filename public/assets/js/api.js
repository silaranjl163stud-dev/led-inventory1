const API_BASE = 'api';

async function fetchAPI(endpoint, options = {}) {
    try {
        const token = localStorage.getItem('auth_token');
        if (token) {
            options.headers = {
                ...options.headers,
                'Authorization': `Bearer ${token}`
            };
        }

        const response = await fetch(`${API_BASE}${endpoint}`, options);
        if (!response.ok) {
            if (response.status === 401) {
                localStorage.removeItem('auth_token');
                window.dispatchEvent(new Event('unauthorized'));
            }
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        return await response.json();
    } catch (error) {
        console.error("API Fetch Error:", error);
        return null;
    }
}

const api = {
    login: async (username, password) => {
        return await fetchAPI('/auth/login', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ username, password })
        });
    },
    register: async (userData) => {
        return await fetchAPI('/auth/register', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(userData)
        });
    },
    logout: async () => {
        return await fetchAPI('/auth/logout', { method: 'POST' });
    },
    getMe: async () => {
        return await fetchAPI('/auth/me');
    },
    getUsers: async () => {
        return await fetchAPI('/users');
    },
    deleteUser: async (id) => {
        return await fetchAPI(`/users/${id}`, { method: 'DELETE' });
    },
    createUser: async (userData) => {
        return await fetchAPI('/users', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(userData)
        });
    },
    updateUser: async (id, userData) => {
        return await fetchAPI(`/users/${id}`, {
            method: 'PUT',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(userData)
        });
    },
    scanItem: async (qrCode) => {
        return await fetchAPI('/scan', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ qr_code: qrCode })
        });
    },
    getCabinets: async () => {
        return await fetchAPI('/cabinets');
    },
    createCabinet: async (data) => {
        return await fetchAPI('/cabinets', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(data)
        });
    },
    getCabinet: async (id) => {
        return await fetchAPI(`/cabinets/${id}`);
    },
    getComponents: async (type) => {
        return await fetchAPI(`/components/${type}`);
    },
    createComponent: async (type, data) => {
        return await fetchAPI(`/components/${type}`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(data)
        });
    },
    getComponent: async (type, id) => {
        return await fetchAPI(`/components/${type}/${id}`);
    },
    getStats: async () => {
        return await fetchAPI('/stats');
    },
    updateStatus: async (type, id, status) => {
        let endpoint = `/cabinets/${id}`;
        if (type !== 'cabinet') {
            if (type === 'power_supply') endpoint = `/components/power_supplies/${id}`;
            else endpoint = `/components/${type}s/${id}`;
        }
        return await fetchAPI(endpoint, {
            method: 'PUT',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ status })
        });
    },
    installComponent: async (cabinetId, componentType, componentId, slot) => {
        return await fetchAPI(`/cabinets/${cabinetId}/components`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ component_type: componentType, component_id: componentId, slot: slot })
        });
    },
    removeComponent: async (cabinetId, componentType, componentId) => {
        return await fetchAPI(`/cabinets/${cabinetId}/components/${componentId}?type=${componentType}`, {
            method: 'DELETE'
        });
    },
    getLogs: async (itemType, itemId) => {
        return await fetchAPI(`/logs?item_type=${itemType}&item_id=${itemId}`);
    },
    getDeployments: async () => {
        return await fetchAPI('/deployments');
    },
    getDeployment: async (id) => {
        return await fetchAPI(`/deployments/${id}`);
    },
    createDeployment: async (data) => {
        return await fetchAPI('/deployments', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(data)
        });
    },
    updateDeploymentStatus: async (id, status) => {
        return await fetchAPI(`/deployments/${id}/status`, {
            method: 'PUT',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ status })
        });
    },
    assignDeploymentItem: async (deploymentId, itemType, itemId) => {
        return await fetchAPI(`/deployments/${deploymentId}/items`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ item_type: itemType, item_id: itemId })
        });
    },
    removeDeploymentItem: async (deploymentId, itemType, itemId) => {
        return await fetchAPI(`/deployments/${deploymentId}/items/${itemId}?type=${itemType}`, {
            method: 'DELETE'
        });
    },
    getRepairs: async () => {
        return await fetchAPI('/repairs');
    },
    getRepair: async (id) => {
        return await fetchAPI(`/repairs/${id}`);
    },
    createRepair: async (data) => {
        return await fetchAPI('/repairs', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(data)
        });
    },
    updateRepairStatus: async (id, status) => {
        return await fetchAPI(`/repairs/${id}/status`, {
            method: 'PUT',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ status })
        });
    },
    decideRepair: async (id, decision) => {
        return await fetchAPI(`/repairs/${id}/decision`, {
            method: 'PUT',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ decision })
        });
    },
    getShopCatalog: async () => {
        return await fetchAPI('/orders/catalog');
    },
    getOrders: async () => {
        return await fetchAPI('/orders');
    },
    createOrderRequest: async (data) => {
        return await fetchAPI('/orders', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(data)
        });
    },
    decideOrder: async (id, decision, adminNotes) => {
        return await fetchAPI(`/orders/${id}/decision`, {
            method: 'PUT',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ decision, admin_notes: adminNotes })
        });
    },
    addRepairAction: async (id, actionData) => {
        return await fetchAPI(`/repairs/${id}/actions`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(actionData)
        });
    },

    // Custom Fields
    getCustomFields: async (type = '') => {
        return await fetchAPI(`/custom-fields/${type}`);
    },
    addCustomField: async (data) => {
        return await fetchAPI('/custom-fields', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(data)
        });
    },
    deleteCustomField: async (id) => {
        return await fetchAPI(`/custom-fields/${id}`, {
            method: 'DELETE'
        });
    }
};
