/**
 * stocks.js — Stock Management page logic
 */

// ---- Shared mobile sidebar helper ----
function initMobileSidebar() {
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
}

// ---- Badge helper ----
function statusBadge(status) {
    return `<span class="badge badge-${status}">${status.replace(/_/g,' ')}</span>`;
}

// ---- State ----
const StocksPage = {
    loadedData: [],
    filteredData: [],
    currentPage: 1,
    itemsPerPage: 10,
    currentType: 'cabinet',

    async init(user) {
        initMobileSidebar();
        Nav.init('stocks', user);
        this._bindTabs();
        this._bindForm();
        this._bindFilters();
        await this.renderDynamicFields();
    },

    _bindTabs() {
        document.getElementById('tab-add')?.addEventListener('click', () => {
            document.getElementById('tab-add').classList.add('active');
            document.getElementById('tab-view').classList.remove('active');
            document.getElementById('panel-add').style.display = '';
            document.getElementById('panel-view').style.display = 'none';
        });
        document.getElementById('tab-view')?.addEventListener('click', () => {
            document.getElementById('tab-view').classList.add('active');
            document.getElementById('tab-add').classList.remove('active');
            document.getElementById('panel-view').style.display = '';
            document.getElementById('panel-add').style.display = 'none';
        });
    },

    _bindForm() {
        document.getElementById('stock-type')?.addEventListener('change', () => this.renderDynamicFields());

        document.getElementById('add-stock-form')?.addEventListener('submit', async (e) => {
            e.preventDefault();
            const type = document.getElementById('stock-type').value;
            const quantity = parseInt(document.getElementById('stock-quantity').value) || 1;
            const data = { quantity, custom_properties: {} };

            document.getElementById('dynamic-fields-container').querySelectorAll('input').forEach(input => {
                if (input.value.trim()) {
                    if (input.name.startsWith('custom_')) {
                        data.custom_properties[input.name.replace('custom_', '')] = input.value.trim();
                    } else {
                        data[input.name] = input.value.trim();
                    }
                }
            });

            if (!Object.keys(data.custom_properties).length) delete data.custom_properties;

            const res = type === 'cabinets'
                ? await api.createCabinet(data)
                : await api.createComponent(type, data);

            if (res && res.id) {
                Toast.show(`${quantity} item(s) added successfully`);
                e.target.reset();
                await this.renderDynamicFields();
            } else {
                Toast.show('Failed to add stock', 'error');
            }
        });
    },

    _bindFilters() {
        document.getElementById('load-stocks-btn')?.addEventListener('click', () => this.loadStocks());
        document.getElementById('stock-search')?.addEventListener('input', () => this.applyFilters());
        document.getElementById('stock-filter-status')?.addEventListener('change', () => this.applyFilters());
        document.getElementById('stock-sort')?.addEventListener('change', () => this.applyFilters());
        document.getElementById('stock-items-per-page')?.addEventListener('change', (e) => {
            this.itemsPerPage = parseInt(e.target.value);
            this.currentPage = 1;
            this.renderPage();
        });
        document.getElementById('stock-page-prev')?.addEventListener('click', () => {
            if (this.currentPage > 1) { this.currentPage--; this.renderPage(); }
        });
        document.getElementById('stock-page-next')?.addEventListener('click', () => {
            const max = Math.ceil(this.filteredData.length / this.itemsPerPage);
            if (this.currentPage < max) { this.currentPage++; this.renderPage(); }
        });
        document.getElementById('bulk-print-btn')?.addEventListener('click', () => {
            const pageData = this.filteredData.slice(
                (this.currentPage - 1) * this.itemsPerPage,
                this.currentPage * this.itemsPerPage
            );
            if (pageData.length) {
                Print.bulk(this.currentType, pageData);
            } else {
                Toast.show('No items to print', 'error');
            }
        });
    },

    async renderDynamicFields() {
        const container = document.getElementById('dynamic-fields-container');
        if (!container) return;
        const type = document.getElementById('stock-type')?.value || 'cabinets';

        const fieldMap = {
            cabinets:       [{name:'model',placeholder:'Model'},{name:'size',placeholder:'Size (e.g. 500x500)'},{name:'manufacturer',placeholder:'Manufacturer'}],
            modules:        [{name:'model',placeholder:'Model'},{name:'size',placeholder:'Size'},{name:'pixel_pitch',placeholder:'Pixel Pitch (e.g. 2.9)'}],
            power_supplies: [{name:'model',placeholder:'Model'},{name:'voltage',placeholder:'Voltage (e.g. 5V)'},{name:'current',placeholder:'Current (e.g. 40A)'}],
        };
        const fields = fieldMap[type] || [{name:'model',placeholder:'Model'}];

        let html = fields.map(f =>
            `<div class="form-group"><label class="form-label">${f.placeholder}</label>
             <input class="form-input" type="text" name="${f.name}" placeholder="${f.placeholder}"></div>`
        ).join('');

        try {
            const customFields = await api.getCustomFields(type);
            if (customFields?.length) {
                html += customFields.map(f => {
                    const typeAttr = f.field_type === 'number' ? 'type="number" step="any"' : f.field_type === 'date' ? 'type="date"' : 'type="text"';
                    return `<div class="form-group">
                        <label class="form-label">${f.field_name} <span style="color:var(--accent-glow);font-size:0.7rem;">(Custom)</span></label>
                        <input class="form-input" ${typeAttr} name="custom_${f.field_name}" ${f.is_required ? 'required' : ''}>
                    </div>`;
                }).join('');
            }
        } catch (e) { /* silent */ }

        container.innerHTML = html;
    },

    async loadStocks() {
        const type = document.getElementById('view-stock-type')?.value || 'cabinets';
        const items = type === 'cabinets' ? await api.getCabinets() : await api.getComponents(type);

        // Map type → singular
        const singMap = {cabinets:'cabinet',modules:'module',power_supplies:'power_supply',receiving_cards:'receiving_card',hubs:'hub',video_processors:'video_processor'};
        this.currentType = singMap[type] || type;
        this.loadedData = items || [];

        document.getElementById('stock-search').value = '';
        document.getElementById('stock-filter-status').value = 'all';
        document.getElementById('stock-sort').value = 'newest';

        this.applyFilters();
    },

    applyFilters() {
        const q = (document.getElementById('stock-search')?.value || '').toLowerCase();
        const status = document.getElementById('stock-filter-status')?.value || 'all';
        const sort   = document.getElementById('stock-sort')?.value || 'newest';

        let data = this.loadedData.filter(i => {
            const matchQ = !q || [i.qr_code, i.serial_number, i.model].some(v => v?.toLowerCase().includes(q));
            const matchS = status === 'all' || i.status === status;
            return matchQ && matchS;
        });

        data.sort((a, b) => {
            if (sort === 'newest') return new Date(b.created_at||0) - new Date(a.created_at||0);
            if (sort === 'oldest') return new Date(a.created_at||0) - new Date(b.created_at||0);
            if (sort === 'qr_asc')  return (a.qr_code||'').localeCompare(b.qr_code||'');
            if (sort === 'qr_desc') return (b.qr_code||'').localeCompare(a.qr_code||'');
            return 0;
        });

        this.filteredData = data;
        this.currentPage = 1;
        this.renderPage();
    },

    renderPage() {
        const tbody = document.getElementById('stocks-tbody');
        const pagination = document.getElementById('stock-pagination');
        if (!tbody) return;

        if (!this.filteredData.length) {
            tbody.innerHTML = '<tr><td colspan="5" class="table-empty"><ion-icon name="cube-outline"></ion-icon>No items found.</td></tr>';
            if (pagination) pagination.style.display = 'none';
            return;
        }

        const total = this.filteredData.length;
        const pages = Math.ceil(total / this.itemsPerPage);
        if (this.currentPage > pages) this.currentPage = pages;

        const start = (this.currentPage - 1) * this.itemsPerPage;
        const slice = this.filteredData.slice(start, start + this.itemsPerPage);

        tbody.innerHTML = slice.map(i => `
            <tr onclick="window.location.href='profile.php?type=${encodeURIComponent(this.currentType)}&id=${encodeURIComponent(i.id)}'">
                <td class="qr-code-cell">${i.qr_code}</td>
                <td>${i.model || '—'}</td>
                <td style="font-size:0.8rem;font-family:monospace;">${i.serial_number || '—'}</td>
                <td>${statusBadge(i.status)}</td>
                <td style="font-size:0.78rem;color:var(--text-secondary);">${i.created_at ? new Date(i.created_at).toLocaleDateString() : '—'}</td>
            </tr>
        `).join('');

        if (pagination) {
            pagination.style.display = 'flex';
            document.getElementById('stock-page-info').textContent = `Page ${this.currentPage} of ${pages} (${total} total)`;
            document.getElementById('stock-page-prev').disabled = this.currentPage <= 1;
            document.getElementById('stock-page-next').disabled = this.currentPage >= pages;
        }
    }
};

(async () => {
    const user = await AuthGuard.check();
    if (!user) return;
    StocksPage.init(user);
})();
