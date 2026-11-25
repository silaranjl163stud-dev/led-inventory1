<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Stock Management — JMACX LED Inventory</title>
    <meta name="description" content="Add and view LED hardware stock — cabinets, modules, power supplies, and more.">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700&display=swap" rel="stylesheet">
    <script type="module" src="https://unpkg.com/ionicons@7.1.0/dist/ionicons/ionicons.esm.js"></script>
    <script nomodule src="https://unpkg.com/ionicons@7.1.0/dist/ionicons/ionicons.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/qrious/4.0.2/qrious.min.js"></script>
    <link rel="stylesheet" href="assets/css/variables.css">
    <link rel="stylesheet" href="assets/css/layout.css">
    <link rel="stylesheet" href="assets/css/components.css">
    <link rel="stylesheet" href="assets/css/pages/stocks.css">
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
                <h1 class="page-title">Stock Management</h1>
            </header>

            <main class="page-content" id="main-content">
                <!-- Tab bar -->
                <div class="tab-bar" role="tablist">
                    <button class="tab-btn active" id="tab-add" role="tab" aria-selected="true">
                        <ion-icon name="add-outline"></ion-icon> Add Stock
                    </button>
                    <button class="tab-btn" id="tab-view" role="tab" aria-selected="false">
                        <ion-icon name="list-outline"></ion-icon> View Stocks
                    </button>
                </div>

                <!-- ADD STOCK panel -->
                <div id="panel-add" class="section-card">
                    <div class="section-card-title">Add New Stock</div>
                    <form id="add-stock-form">
                        <div class="form-group">
                            <label class="form-label" for="stock-type">Item Type</label>
                            <select class="form-select" id="stock-type">
                                <option value="cabinets">Cabinet</option>
                                <option value="modules">Module</option>
                                <option value="power_supplies">Power Supply</option>
                                <option value="receiving_cards">Receiving Card</option>
                                <option value="hubs">Hub</option>
                                <option value="video_processors">Video Processor</option>
                            </select>
                        </div>
                        <div id="dynamic-fields-container"></div>
                        <div class="form-group">
                            <label class="form-label" for="stock-quantity">Quantity</label>
                            <input class="form-input" type="number" id="stock-quantity" value="1" min="1">
                        </div>
                        <button type="submit" class="btn primary">
                            <ion-icon name="checkmark-circle-outline"></ion-icon> Create Asset(s)
                        </button>
                    </form>
                </div>

                <!-- VIEW STOCKS panel -->
                <div id="panel-view" class="section-card" style="display:none;">
                    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;flex-wrap:wrap;gap:10px;">
                        <div class="section-card-title" style="margin-bottom:0;">Current Stocks</div>
                        <button class="btn btn-sm" id="bulk-print-btn">
                            <ion-icon name="print-outline"></ion-icon> Bulk Print
                        </button>
                    </div>

                    <!-- Filters -->
                    <div class="filter-row">
                        <select class="form-select" id="view-stock-type">
                            <option value="cabinets">Cabinets</option>
                            <option value="modules">Modules</option>
                            <option value="power_supplies">Power Supplies</option>
                            <option value="receiving_cards">Receiving Cards</option>
                            <option value="hubs">Hubs</option>
                            <option value="video_processors">Video Processors</option>
                        </select>
                        <button class="btn primary" id="load-stocks-btn">
                            <ion-icon name="search-outline"></ion-icon> Load
                        </button>
                    </div>
                    <div class="filter-row">
                        <input class="form-input" type="text" id="stock-search" placeholder="Search QR, S/N, or model…">
                        <select class="form-select" id="stock-filter-status">
                            <option value="all">All Statuses</option>
                            <option value="available">Available</option>
                            <option value="installed">Installed</option>
                            <option value="deployed">Deployed</option>
                            <option value="defective">Defective</option>
                            <option value="under_repair">Under Repair</option>
                            <option value="retired">Retired</option>
                            <option value="sold">Sold</option>
                        </select>
                        <select class="form-select" id="stock-sort">
                            <option value="newest">Newest First</option>
                            <option value="oldest">Oldest First</option>
                            <option value="qr_asc">QR (A–Z)</option>
                            <option value="qr_desc">QR (Z–A)</option>
                        </select>
                    </div>

                    <!-- Table -->
                    <div class="data-table-wrapper">
                        <table class="data-table" id="stocks-table">
                            <thead>
                                <tr>
                                    <th>QR Code</th>
                                    <th>Model</th>
                                    <th>Serial Number</th>
                                    <th>Status</th>
                                    <th>Created</th>
                                </tr>
                            </thead>
                            <tbody id="stocks-tbody">
                                <tr><td colspan="5" class="table-empty">Select type and click Load</td></tr>
                            </tbody>
                        </table>
                    </div>

                    <!-- Pagination -->
                    <div class="pagination-bar" id="stock-pagination" style="display:none;">
                        <span class="pagination-info" id="stock-page-info">Page 1 of 1</span>
                        <div class="pagination-controls">
                            <select class="form-select" id="stock-items-per-page" style="width:auto;">
                                <option value="10">10 / page</option>
                                <option value="25">25 / page</option>
                                <option value="50">50 / page</option>
                            </select>
                            <button class="btn btn-sm" id="stock-page-prev">
                                <ion-icon name="chevron-back-outline"></ion-icon>
                            </button>
                            <button class="btn btn-sm" id="stock-page-next">
                                <ion-icon name="chevron-forward-outline"></ion-icon>
                            </button>
                        </div>
                    </div>
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
    <script src="assets/js/pages/stocks.js"></script>
</body>
</html>
