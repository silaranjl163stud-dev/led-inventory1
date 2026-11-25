# JMACX LED Inventory System: Product Requirements Document (As-Built)

| | |
|---|---|
| **Document type** | PRD / as-built system specification |
| **Describes** | The code currently in this repository (not the target design) |
| **Date** | 2026-10-09 |
| **Related docs** | `requirements.md` (target requirements), `DATABASE_SCHEMA.md` (data dictionary), `usecase.md`, `features.md` (older SPA design, partly outdated) |

This document describes how the application works today: what each feature does, which files implement it, how the backend and frontend functions behave, and the business rules the code enforces. Section 9 traces the original requirements to what is built, and Section 10 lists known defects. Line numbers are given as `file:line` and refer to the code at the time of writing.

---

## 1. Product overview

### 1.1 Purpose

A mobile-first web application for tracking LED video-wall hardware through its lifecycle: receiving stock, assembling cabinets, sending equipment to events (deployments), repairing defective units, and auditing every change. Every physical unit gets a QR label that staff scan with a phone camera or a USB scanner to pull up its record.

### 1.2 Hardware tracked

| Type | DB value (`inventory_items.item_type`) | API / UI value | Serial prefix | Type-specific data |
|---|---|---|---|---|
| Cabinet | `cabinet` | `cabinets` | `CAB-` | Size (`spec_cabinets`), installed components |
| Module | `module` | `modules` | `MOD-` | Pixel pitch (`spec_modules`), size (in `metadata`) |
| Power supply | `power_supply` | `power_supplies` | `PWR-` | Voltage, current (`spec_power_supplies`) |
| Receiving card | `receiving_card` | `receiving_cards` | `RCV-` | None |
| Hub | `hub` | `hubs` | `HUB-` | None |
| Video processor | `video_processor` | `video_processors` | `VID-` | Spec row exists but holds no captured fields |

Serials have the format `PREFIX-YYYYMMDD-NNNN` and are also used as the QR code value.

### 1.3 Users and roles

The schema defines five roles: `admin`, `manager`, `warehouse`, `technician`, `staff` (`schema.sql:24`). Only **admin** behaves differently in code. The other four roles have identical access.

| Capability | Admin | Any other logged-in role |
|---|:---:|:---:|
| Sign in / sign out, view dashboard, scan, stocks, deployments, repairs | ✅ | ✅ |
| Create stock, change item status, install/remove components | ✅ | ✅ |
| Create/update deployments and repair tickets | ✅ | ✅ |
| Manage users (list, create, delete) | ✅ | ❌ (403) |
| Create/delete custom fields | ✅ | ❌ (403) |
| View custom-field definitions | ✅ | ✅ |

### 1.4 Technology stack

| Layer | Technology |
|---|---|
| Frontend | Plain HTML pages, CSS custom properties, classic (non-module) JavaScript. No framework, no build step. |
| Icons / fonts | Ionicons 7.1.0 (unpkg), Inter (Google Fonts) |
| QR generation | QRious 4.0.2 (cdnjs), loaded on `stocks.html` and `profile.html` |
| QR decoding | html5-qrcode (unpkg, unpinned), loaded on `scanner.html` |
| Backend | PHP 8.3, no framework, PDO |
| Database | MySQL 8.0+ / MariaDB 10.5+ (`led_inventory`, utf8mb4) |
| Production hosting | Nginx + PHP-FPM behind a Cloudflare Tunnel (`nginx.conf`, host `inventory.jmacxled.com`) |
| Local dev | `php -S localhost:8000 router.php` |

---

## 2. System architecture

### 2.1 Request flow

```
Browser page (e.g. stocks.html)
  └─ assets/js/pages/stocks.js  ──calls──▶  api.* (assets/js/api.js → fetchAPI)
                                                │  Authorization: Bearer <token from localStorage>
                                                ▼
                                   /api/<resource>/<id>/<sub>/<subId>
                                                │  (nginx try_files, or router.php locally)
                                                ▼
                                   api/index.php  ── switch(resource)
                                                │  authenticateRequest() for protected resources
                                                ▼
                                   api/controllers/<X>Controller.php → processRequest($method, $uriParts)
                                                │  PDO prepared statements
                                                ▼
                                   MySQL led_inventory
```

### 2.2 Backend structure

- **`api/index.php`**: the single router. It strips `/api`, splits the path into `$uriParts`, and `switch`es on `$uriParts[0]`. Each case loads one controller and calls `processRequest($method, $uriParts)`. By convention `$uriParts[1]` is the id, `$uriParts[2]` a sub-resource and `$uriParts[3]` a sub-id.
- **Authentication is applied in the router**, not in controllers. The exceptions are `UserController`, which calls `authenticateRequest(true)` in its constructor, and `AuthController`, which calls it inside `logout()` and `me()`.
- **`api/config/auth.php` → `authenticateRequest($requireAdmin)`**: reads `Authorization: Bearer <token>` and looks up `users.auth_token`. It returns 401 if the token is missing or unknown, and 403 if admin is required and the role is not admin. It returns `{id, username, role}`, but every router case discards that value, so no controller knows who is acting.
- **`api/config/database.php` → `Database::getConnection()`**: hardcoded credentials. Every controller (and `authenticateRequest`) opens its own PDO connection, with `ERRMODE_EXCEPTION`.
- **Controllers** echo JSON directly with `http_response_code()`. There is no shared response helper. Most controllers have a private `logAction()` that writes to `inventory_logs` and swallows any error.

### 2.3 Frontend structure

- **Multi-page application.** Each screen is its own HTML file and loads the same ordered set of classic scripts, which share global objects:

  | Script | Global | Role |
  |---|---|---|
  | `assets/js/api.js` | `api`, `fetchAPI` | All HTTP calls. Adds the bearer token; returns parsed JSON, or `null` on **any** error |
  | `assets/js/auth.js` | `AuthGuard` | Page guard (`check(requireAdmin)`), `logout()` |
  | `assets/js/toast.js` | `Toast` | `Toast.show(msg, 'success' \| 'error' \| 'warning')`, auto-hides after 3 s |
  | `assets/js/nav.js` | `Nav` | Renders the sidebar (`#sidebar`) and mobile bottom nav (`#bottom-nav`) |
  | `assets/js/print.js` | `Print` | QR label printing (stocks and profile pages only) |
  | `assets/js/scanner.js` | `Scanner` | Camera and USB scanner input (scanner page only) |
  | `assets/js/pages/<page>.js` | — | Page logic, usually an async IIFE |

- **Standard page boot sequence:** `const user = await AuthGuard.check()` → `initMobileSidebar()` → `Nav.init('<page>', user)` → bind events → load data.
- **Styling:** `assets/css/variables.css` (theme tokens), `layout.css` (shell, sidebar, bottom nav), `components.css` (buttons, cards, lists, badges, toast, print area), `pages/stocks.css`. Dark glassmorphism theme.
- **Legacy:** `assets/js/app.js.bak` is the old single-page app. No page loads it.

### 2.4 Pages

| Page | Script | Guard | Purpose |
|---|---|---|---|
| `index.html` | inline | none (redirects if already signed in) | Login |
| `dashboard.html` | `pages/dashboard.js` | any user | Stat counters, quick actions |
| `scanner.html` | `pages/scanner-page.js` + `scanner.js` | any user | Camera / USB scan → item profile |
| `stocks.html` | `pages/stocks.js` | any user | Add stock, browse/filter lists, bulk print |
| `profile.html` | `pages/profile.js` | any user | **Item** profile (not a user profile): details, status, defect report, label, assembly, history |
| `deployments.html` | `pages/deployments.js` | any user | List and create events |
| `deployment-detail.html` | `pages/deployment-detail.js` | any user | Event status, assign/remove items |
| `repairs.html` | `pages/repairs.js` | any user | Ticket list with status filter |
| `repair-detail.html` | `pages/repair-detail.js` | any user | Ticket status, technician actions |
| `settings.html` | `pages/settings.js` | any user (custom fields: admin) | Account info, sign out, custom-field admin |
| `admin.html` | `pages/admin.js` | admin | User management |

---

## 3. Feature catalogue

Each feature lists what the user can do, the source files, the API calls, how the functions behave, and the rules enforced.

### F1. Sign in

**User story:** As a staff member, I sign in with a username and password and land on the dashboard.

**Files:** `index.html` (form and inline script), `assets/js/api.js` (`api.login`), `api/controllers/AuthController.php` (`login`), `api/index.php` (`auth` case).

**Flow**
1. On load, if `localStorage.auth_token` exists, the inline script calls `GET /api/auth/me`. On success it redirects to `/dashboard.html`; otherwise it removes the token (`index.html:53-67`).
2. On submit, the button is disabled and `api.login(username.trim(), password)` is called (`index.html:69-87`).
3. If the response has a `token`, it is saved to `localStorage.auth_token` and the user is redirected to the dashboard. Otherwise the page shows "Invalid username or password" for any failure, including server outages.

**Backend `AuthController::login()`** (`AuthController.php:28-62`)
1. Requires `username` and `password` with `isset()`; otherwise 400.
2. Selects the user by `username`.
3. Checks the password with `password_verify()`.
4. Generates `bin2hex(random_bytes(32))` (64 hex characters) and stores it in `users.auth_token`, overwriting any previous token.
5. Returns `{message, token, user:{id, username, role}}`; on failure 401 "Invalid credentials".

**Rules:** one active token per user (a new login signs out other devices). Tokens never expire. Passwords are hashed with `PASSWORD_DEFAULT` (bcrypt). The login query does **not** check `is_active` or `deleted_at` (see Section 10).

### F2. Session guard and sign out

**Files:** `assets/js/auth.js` (`AuthGuard`), `api/controllers/AuthController.php` (`me`, `logout`), `settings.html` / `pages/settings.js` (Sign Out button).

- **`AuthGuard.check(requireAdmin=false)`** (`auth.js:13-46`): with no token, redirects to `/index.html`. Otherwise calls `GET /api/auth/me` with a direct `fetch`. If that fails, it removes the token and redirects to login. If `requireAdmin` is set and the role is not admin, it redirects to `/dashboard.html`. It returns the `user` object and caches it in `AuthGuard._user`.
- **`AuthGuard.logout()`** (`auth.js:54-66`): sends `POST /api/auth/logout` (errors ignored), removes the token and redirects to login.
- **`AuthController::logout()`**: authenticates the caller, then sets `auth_token = NULL`.
- **`AuthController::me()`**: returns `{user:{id, username, role}}` for the token.
- `fetchAPI` dispatches a `window` `unauthorized` event on any 401 and clears the token, but no active page listens for that event.

### F3. Navigation shell

**Files:** `assets/js/nav.js`, `assets/css/layout.css`, plus a copied `initMobileSidebar()` in each page script.

- **`Nav.init(activePage, user)`** renders:
  - **Sidebar** (`_renderSidebar`, `nav.js:30-73`): brand; links to Dashboard, Scanner, Stocks, Deployments, Repairs and Settings; an Admin link only when `user.role === 'admin'` (`_getItems`, `nav.js:15-21`); and a footer with the username, role and a settings cog.
  - **Bottom nav** (`_renderBottomNav`, `nav.js:75-91`): Dashboard, Stocks, a center Scanner floating button, Deployments and Repairs. Settings and Admin are not in the mobile bottom nav.
- **`initMobileSidebar()`** (copied in every page script) toggles `.open` on `#sidebar` and `.visible` on `#sidebar-overlay` when the hamburger button is pressed.
- **`Toast.show(message, type)`** (`toast.js:12-38`) creates `#toast` if it is missing, sets `textContent` (safe), colours it by type, and hides it after 3 seconds.

### F4. User administration (admin)

**User story:** As an admin, I create staff accounts, see all users and deactivate users.

**Files:** `admin.html`, `assets/js/pages/admin.js`, `assets/js/api.js` (`getUsers`, `createUser`, `deleteUser`), `api/controllers/UserController.php`.

**Frontend (`admin.js`)**
- Boot calls `AuthGuard.check(true)`, so non-admins are redirected.
- `loadUsers()` (`:12-33`) calls `api.getUsers()` and renders `username`, `role` and a Delete button into `#users-list`. An error looks the same as an empty list ("No users found.").
- The create form (`:52-67`) reads `#new-username`, `#new-password` and `#new-role` and calls `api.createUser`. If the response has an `id`, it shows a toast, resets the form and reloads the list. The role dropdown offers only **Staff** and **Admin**.
- `deleteUser(id, username)` (`:35-43`, exposed on `window`) asks `confirm()` and then calls `api.deleteUser(id)`.

**Backend (`UserController.php`)**: the constructor requires admin.

| Method | Behavior |
|---|---|
| `getAllUsers()` (`:41-45`) | `SELECT id, username, role, created_at FROM users ORDER BY created_at DESC`. Includes soft-deleted users. |
| `createUser()` (`:47-69`) | Requires `username` and `password` (`isset`). `role` defaults to `warehouse`; `full_name` defaults to the username; `email` is optional. Hashes the password and inserts. Returns 201 `{id}`. Duplicate usernames and invalid roles return 500 with the raw database error. |
| `deleteUser($id)` (`:71-81`) | Soft delete: sets `deleted_at = NOW()` and `is_active = FALSE`. Does not clear `auth_token`. Always reports success. |

**Rules:** there is no self-registration, password change, password reset, user edit or reactivation. Admins can delete themselves or the last admin.

### F5. Dashboard

**User story:** As any user, I see headline counts and jump to common tasks.

**Files:** `dashboard.html`, `assets/js/pages/dashboard.js`, `assets/js/api.js` (`getStats`), `api/controllers/StatsController.php`.

- **`loadStats()`** (`dashboard.js:23-51`) calls `GET /api/stats` and fills `#stat-cabinets`, `#stat-modules` and `#stat-repairs`. It also renders a "System Status" card that repeats the same three numbers. The Refresh button reloads them.
- **Quick actions:** links to Scanner, Stocks, Deployments and Repairs.
- **`StatsController::processRequest`** (`:13-43`), GET only:
  - `cabinets` = `COUNT(*) WHERE item_type='cabinet'`
  - `modules` = `COUNT(*) WHERE item_type='module'`
  - `repairs_pending` = `COUNT(*) FROM repairs WHERE status IN ('open','in_progress')`

  The counts include retired and soft-deleted items. Other hardware types are not counted.

### F6. Add stock (bulk receiving)

**User story:** As warehouse staff, I register N new identical units of a hardware type, and each gets a unique serial and QR code.

**Files:** `stocks.html` (Add tab), `assets/js/pages/stocks.js` (`_bindForm`, `renderDynamicFields`), `assets/js/api.js` (`createCabinet`, `createComponent`, `getCustomFields`), `api/controllers/CabinetController.php` (`createCabinet`, `getOrCreateManufacturer`, `getOrCreateModel`), `api/controllers/ComponentController.php` (`createComponent`, `getInternalType`, same helpers).

**Form fields by type** (`renderDynamicFields`, `stocks.js:122-153`)

| Type | Built-in fields |
|---|---|
| Cabinet | Model, Size, Manufacturer |
| Module | Model, Size, Pixel pitch |
| Power supply | Model, Voltage, Current |
| Receiving card, Hub, Video processor | Model |
| All | Quantity, plus any custom fields defined for the type (named `custom_<field>`; `required` if `is_required`) |

**Submit** (`_bindForm`, `stocks.js:57-90`): fields that start with `custom_` go into `custom_properties`; the rest go at the top level. The page then calls `api.createCabinet(data)` or `api.createComponent(type, data)`. On success the form resets, which also sets the type back to Cabinet.

**Backend `createCabinet()` / `createComponent($type)`**
1. `quantity` defaults to 1, with a minimum of 1 and no maximum.
2. Builds the prefix (`CAB-`, `MOD-`, `PWR-`, `RCV-`, `HUB-`, `VID-`) plus `YYYYMMDD-`, and reads the last serial with that prefix to start the counter. This read happens before the transaction and without a lock.
3. Begins a transaction.
4. `getOrCreateManufacturer(name)`: an empty name becomes "Unknown Manufacturer". The function finds the row by name or inserts it.
5. `getOrCreateModel(item_type, manufacturer_id, model)`: an empty model becomes "Unknown Model". The function finds the row by (type, manufacturer, model) or inserts it.
6. For each unit: it increments the counter, sets serial = QR = `prefix + 4-digit counter`, inserts the `inventory_items` row (status defaults to `available`; `metadata` = JSON of the custom properties), and inserts the spec row:
   - cabinet → `spec_cabinets(size)`
   - module → `spec_modules(pixel_pitch, default 1.0)`, with size stored in `metadata`
   - power supply → `spec_power_supplies(voltage_in, current_a, default 1.0)`
   - video processor → an empty `spec_video_processors` row
   - receiving card and hub have no spec table
7. Writes an `inventory_logs` row with action `create`.
8. Commits and returns 201 `{message:"N X(s) created", id:<first id>}`. On any error it rolls back and returns 500 with the database message.

**Type name mapping:** `ComponentController::getInternalType()` (`:40-46`) converts the plural route name to the singular DB value by stripping the trailing `s`, with a special case for `power_supplies`. The component routes reject `cabinets`, so cabinets have their own controller.

### F7. Browse, search and filter stock

**User story:** As any user, I list every unit of a type, narrow the list down and open a unit's profile.

**Files:** `stocks.html` (View tab), `assets/js/pages/stocks.js` (`loadStocks`, `applyFilters`, `renderPage`, `_bindFilters`), `assets/css/pages/stocks.css`, `CabinetController::getAllCabinets`, `ComponentController::getAllComponents`.

- **`loadStocks()`** (`stocks.js:155-169`) calls `api.getCabinets()` or `api.getComponents(type)` and stores the result in `loadedData`. It also resets the filters.
- **`applyFilters()`** (`:171-193`) does all filtering in the browser:
  - text search over `qr_code`, `serial_number` and `model`
  - a status filter
  - sorting by newest, oldest, QR A-Z or QR Z-A
- **`renderPage()`** (`:195-229`) paginates 10, 25 or 50 rows per page and shows QR, model, serial, a status badge and the created date. Clicking a row opens `profile.html?type=<singular>&id=<id>`.
- **Backend list queries** join `item_models`, `manufacturers` and the type's spec table, and sort by `updated_at DESC`. There is no server-side pagination or filtering. `custom_properties` is returned as a JSON string.

### F8. QR label printing

**Files:** `assets/js/print.js`, `stocks.html` (Bulk Print button), `profile.html` (Print Label button), `assets/css/components.css` (`#print-area` and the `@media print` block), QRious from the CDN.

- **`Print.getLabelConfig(type)`** sets the label width by type: cabinet 80 mm, video processor 60 mm, module 50 mm, others 40 mm.
- **`Print.label(type, item)`** renders one label into `#print-area` (QR canvas, QR text, type name, `S/N`), draws the QR code with QRious, and calls `window.print()`.
- **`Print.bulk(type, items)`** renders one label per item with a page break after each, then prints. On the stocks page this prints only the **current page** of the filtered list.

### F9. Item profile

**User story:** After scanning or clicking an item, I see everything about it and can act on it.

**Files:** `profile.html`, `assets/js/pages/profile.js`, `assets/js/api.js` (`getCabinet`, `getComponent`, `updateStatus`, `createRepair`, `scanItem`, `installComponent`, `removeComponent`, `getLogs`), `print.js`.

**`loadItem()`** (`profile.js:43-175`) reads `?type=<singular>&id=`, then:
1. Fetches the item: `api.getCabinet(id)` for cabinets, or `api.getComponent(plural, id)` for other types (`pluralMap`). Both backends accept either the numeric id or the QR code.
2. Renders the details: every field except internal ones, then custom properties parsed from `metadata` and tagged "(Custom)".
3. Renders the actions: a status selector, **Report Defect** (F14), **Print Label** (F8), the **Cabinet Assembly** card for cabinets (F11), and **Activity History** (F13).

### F10. Manual status change

**Files:** `profile.js` (`#update-status-btn` handler, `:135-140`), `api.js` (`updateStatus`, `:91-102`, which maps the singular type to its route), `CabinetController::updateCabinetStatus` (`:176-192`), `ComponentController::updateComponentStatus` (`:211-227`).

- The UI offers all six statuses: `available`, `installed`, `deployed`, `defective`, `under_repair`, `retired`.
- The backend requires `status`. It reads the old status, runs `UPDATE inventory_items SET status=? WHERE id=?` (not scoped to the item type), and writes an `inventory_logs` `status_change` row with the from and to statuses.
- **There is no transition check.** The seeded `status_transitions` table is never read, so any status can change to any other, including from `retired`.

### F11. Cabinet assembly (install / remove components)

**User story:** On a cabinet's profile, I scan a module, PSU, card or hub into a slot, and remove it later.

**Files:** `profile.js` (`#install-comp-btn` handler `:160-170`, `loadCabinetComponents` `:177-206`), `api.js` (`installComponent`, `removeComponent`), `CabinetController::installComponent` (`:194-234`), `::removeComponent` (`:236-255`), `::getCabinet` (includes `components`).

**Install**
1. The frontend calls `api.scanItem(qr)` to resolve the QR code, then rejects it on the client if it is not found, is a cabinet, or is not `available`.
2. `POST /api/cabinets/{id}/components {component_id, slot}`. In one transaction the backend:
   - checks that the component's status is `available` (400 otherwise)
   - inserts `asset_hierarchy(parent_id, child_id, slot_info, status='installed')`
   - sets the component to `installed`
   - writes two log rows
3. The backend does **not** check that the parent is a cabinet or that the child is not a cabinet.

**Remove:** `DELETE /api/cabinets/{id}/components/{componentId}`. In one transaction the backend sets the link to `removed` (with `removed_at`), sets the component to `available` (unconditionally), and writes the logs.

**Listing:** `getCabinet()` returns `components[]` (`component_id`, `slot`, `component_type`, `comp_qr_code`) for links whose status is `installed`.

**Schema constraint:** `asset_hierarchy` has `UNIQUE (child_id, status)` (`schema.sql:249`). A component can therefore have only one `removed` row, so the **second removal** of the same component fails with a duplicate-key error (500).

### F12. QR / barcode scanning

**User story:** I point my phone at a label, or fire a USB scanner, and the item's profile opens.

**Files:** `scanner.html`, `assets/js/scanner.js` (`Scanner`), `assets/js/pages/scanner-page.js`, `api.js` (`scanItem`), `api/controllers/ScanController.php`.

**Input methods (`scanner.js`)**
- **Camera:** `startCamera()` creates `Html5Qrcode("reader")` with the rear camera, 10 fps and a 250×250 scan box. On a decode it calls `onScanSuccess(text)` and then `stopCamera()`.
- **USB scanner:** `initHardwareScanner()` handles the hidden input `#hardware-scanner-input`, which submits on Enter. A document-level `keydown` listener focuses that input when keys arrive less than 50 ms apart, which is typical of scanner bursts. `scanner-page.js` also refocuses the input on any click.
- `onScanSuccess(text)` dispatches a `window` `CustomEvent('item-scanned')`.

**Page logic (`scanner-page.js:31-43`):** on `item-scanned`, it shows a "Scanning…" toast and calls `api.scanItem(code)`. If the result is found, it redirects to `profile.html?type=<item_type>&id=<id>`; otherwise it shows "Item not found".

**Backend `ScanController::processRequest`** (POST only)
1. Requires `qr_code`.
2. Looks up `inventory_items` (joined with model and manufacturer) by `qr_code`.
3. If found, inserts `qr_scan_logs(item_id, action='Item Scanned via App')`, ignoring errors, and returns `{found:true, item_type, item}`.
4. Otherwise returns 404 `{found:false}`.

**Notes:**
- The scan endpoint also does the QR lookup for the cabinet-assembly (F11) and deployment-assignment (F15) flows, so those flows write scan logs too.
- `scanned_by` and `location_id` are never recorded.
- Nothing reads `qr_scan_logs`.
- The camera does not restart after a not-found result; reload the page to scan again.

### F13. Activity history (audit timeline)

**Files:** `profile.js` (`loadLogs`, `timelineIcon`), `api.js` (`getLogs`), `api/controllers/LogController.php`.

- `GET /api/logs?item_id=<id>` (the `item_type` parameter is sent but ignored) returns `inventory_logs` rows as `{action, notes, created_at, from_status, to_status}`, newest first.
- `loadLogs()` renders a timeline with an icon per action.

**What writes `inventory_logs`**

| Event | Action | Writer |
|---|---|---|
| Stock created | `create` | Cabinet/ComponentController |
| Manual status change, install, remove | `status_change` | Cabinet/ComponentController |
| Repair opened | `repair` | RepairController |
| Repair resolved | `status_change` | RepairController |
| Assigned to deployment | `deploy` | DeploymentController |
| Removed from deployment | `return` | DeploymentController |

`performed_by` is never set, so the log records what happened but not who did it.

### F14. Repairs

**User story:** I report a defective unit, a technician logs work on the ticket, and resolving the ticket returns the unit to stock.

**Files:** `profile.js` (Report Defect, `:143-149`), `repairs.html`, `assets/js/pages/repairs.js`, `repair-detail.html`, `assets/js/pages/repair-detail.js`, `api.js` (`getRepairs`, `getRepair`, `createRepair`, `updateRepairStatus`, `addRepairAction`), `api/controllers/RepairController.php`.

**Create (only from the item profile):** "Report Defect" opens a `prompt()` for the issue text and calls `api.createRepair({item_id, issue_description, ...})`. `RepairController::createRepair()` does the following in one transaction:
- inserts the `repairs` row (status `open`, priority defaults to `medium`)
- sets the item to `under_repair` (unconditionally)
- logs `repair`

**List (`repairs.js`):** loads all tickets and filters by status in the browser (the default filter is `open`). Each row shows the ticket number, a status badge, the QR code and the first 80 characters of the issue, and opens `repair-detail.html?id=`.

**Detail (`repair-detail.js`)**
- Shows the ticket, item QR code, opened time and issue text.
- **Status update:** a selector with `open`, `in_progress`, `resolved` and `cancelled` calls `PUT /api/repairs/{id}/status`. `updateStatus()` sets the status. When the new status is `resolved`, it also sets `resolved_at`, sets the item to `available` and logs `status_change`. The other statuses do not touch the item.
- **Technician actions:** an action (required) and notes call `POST /api/repairs/{id}/actions`, which inserts into `repair_actions`. The list is shown in time order.

**Ordering:** the backend sorts by status (open → in_progress → resolved → cancelled), then by `created_at DESC`.

**Rules and gaps**
- Any status can be set from any status.
- Cancelling leaves the item stuck in `under_repair`.
- Resolving one ticket marks the item `available` even if other tickets for it are still open.
- Priority, cost, downtime, technician, repair location and swapped components exist in the schema but are never written or shown.

### F15. Deployments (events)

**User story:** I create an event, assign hardware to it by scanning, track its status, and return hardware afterwards.

**Files:** `deployments.html`, `assets/js/pages/deployments.js`, `deployment-detail.html`, `assets/js/pages/deployment-detail.js`, `api.js` (`getDeployments`, `getDeployment`, `createDeployment`, `updateDeploymentStatus`, `assignDeploymentItem`, `removeDeploymentItem`, `scanItem`), `api/controllers/DeploymentController.php`.

**List and create (`deployments.js`)**
- `loadDeployments()` lists events with a status badge, location and dates (TBD when empty), newest first.
- "New Event" shows a form for name, location (free text), start date and end date, which calls `api.createDeployment`.
- `createDeployment()` requires a name and location. `getOrCreateLocation(name)` finds or inserts a `locations` row with `type='event_site'`. The deployment is inserted with status `planned`.

**Detail (`deployment-detail.js`, `load()`)**
- Shows event info and a status selector (`planned`, `active`, `completed`, `cancelled`). `updateStatus()` saves any value, with no transition rules and no effect on the assigned items.
- **Assign:** the user types or scans a QR code. The page calls `api.scanItem` and blocks the item on the client if its status is already `deployed`, then calls `POST /api/deployments/{id}/items {item_id}`. `assignItem()` does the following in one transaction:
  - rejects a duplicate in the same event
  - inserts `deployment_items`
  - sets the item to `deployed` (unconditionally)
  - logs `deploy`
- **Remove / return:** `confirm()`, then `DELETE /api/deployments/{id}/items/{item_id}`. `removeItem()` **hard-deletes** the link, sets the item to `available` (unconditionally) and logs `return`.
- The items list shows the type and QR code.

**Rules and gaps**
- The server does not stop an item from being assigned to several events, or assigning items that are retired, defective, under repair or installed in a cabinet.
- Screen positions (`screen_name`, `pos_x`, `pos_y`) are read by the API but never written or displayed.
- `returned_at`, `stock_movements`, `created_by` and `approved_by` are never written.
- Blank dates are sent as `''`. Under MySQL strict mode this can make event creation fail; check this against the server's `sql_mode`.

### F16. Custom fields

**User story:** As an admin, I define extra attributes per hardware type (for example a firmware version), and staff fill them in when adding stock.

**Files:** `settings.html` (Custom Fields card), `assets/js/pages/settings.js` (`loadCustomFieldsList`, `deleteCustomField`, add handler), `stocks.js` (`renderDynamicFields`), `api.js` (`getCustomFields`, `addCustomField`, `deleteCustomField`), `api/controllers/CustomFieldController.php`, `api/index.php` (`custom-fields` case).

- **Manage (admin only, `settings.js:62-84`):** pick a type, list its fields, add a field (name plus type `text`, `number` or `date`) and delete fields.
- **Backend:**
  - `GET /api/custom-fields[/{item_type}]` is open to any logged-in user.
  - `POST` and `DELETE` require admin (enforced in the router).
  - `createField()` inserts `(item_type, field_name, field_type, is_required)`. The pair (type, name) is unique; a duplicate returns 500.
  - `deleteField()` hard-deletes the definition. Values already stored in item `metadata` stay.
- **Use:** see F6. Values are stored in `inventory_items.metadata` and shown on the profile tagged "(Custom)".
- **Rules:** custom fields are keyed by the **plural** type (`cabinets`, `modules`, …), although the schema comment says singular. `is_required` is enforced only by the HTML `required` attribute. The schema's `boolean` type has no input in the UI.

### F17. Operations endpoints

| Endpoint | Auth | Purpose |
|---|---|---|
| `GET /api/schema-check` | **none** | Reports EXISTS or MISSING for 25 expected tables |
| `ANY /api/migrate` | **none** | Despite the name, it only checks that 6 key tables exist. It runs no migrations. |

---

## 4. API reference

All paths are prefixed with `/api`. "User" means any valid bearer token.

| Method | Path | Auth | Controller → function | Body / query | Success |
|---|---|---|---|---|---|
| POST | `/auth/login` | none | Auth → `login` | `{username, password}` | 200 `{token, user}` |
| POST | `/auth/logout` | user | Auth → `logout` | — | 200 |
| GET | `/auth/me` | user | Auth → `me` | — | 200 `{user}` |
| GET | `/users` | admin | User → `getAllUsers` | — | 200 `[users]` |
| POST | `/users` | admin | User → `createUser` | `{username, password, role?, full_name?, email?}` | 201 `{id}` |
| DELETE | `/users/{id}` | admin | User → `deleteUser` | — | 200 (soft delete) |
| GET | `/stats` | user | Stats → `processRequest` | — | 200 `{cabinets, modules, repairs_pending}` |
| GET | `/cabinets` | user | Cabinet → `getAllCabinets` | — | 200 `[cabinets]` |
| GET | `/cabinets/{id\|qr}` | user | Cabinet → `getCabinet` | — | 200 cabinet + `components[]` |
| POST | `/cabinets` | user | Cabinet → `createCabinet` | `{quantity?, model?, manufacturer?, size?, custom_properties?}` | 201 `{id}` |
| PUT | `/cabinets/{id}` | user | Cabinet → `updateCabinetStatus` | `{status}` | 200 |
| POST | `/cabinets/{id}/components` | user | Cabinet → `installComponent` | `{component_id, slot?}` | 200 |
| DELETE | `/cabinets/{id}/components/{cid}` | user | Cabinet → `removeComponent` | — | 200 |
| GET | `/components/{type}` | user | Component → `getAllComponents` | type ∈ modules, power_supplies, receiving_cards, hubs, video_processors | 200 `[items]` |
| GET | `/components/{type}/{id\|qr}` | user | Component → `getComponent` | — | 200 item |
| POST | `/components/{type}` | user | Component → `createComponent` | `{quantity?, model?, manufacturer?, size?, pixel_pitch?, voltage?, current?, custom_properties?}` | 201 `{id}` |
| PUT | `/components/{type}/{id}` | user | Component → `updateComponentStatus` | `{status}` | 200 |
| POST | `/scan` | user | Scan → `processRequest` | `{qr_code}` | 200 `{found, item_type, item}` / 404 |
| GET | `/logs` | user | Log → `getLogs` | `?item_id=` | 200 `[logs]` |
| GET | `/deployments` | user | Deployment → `getDeployments` | — | 200 `[deployments]` |
| GET | `/deployments/{id}` | user | Deployment → `getDeployment` | — | 200 deployment + `items[]` |
| POST | `/deployments` | user | Deployment → `createDeployment` | `{name, location, start_date?, end_date?}` | 200 `{id}` |
| PUT | `/deployments/{id}/status` | user | Deployment → `updateStatus` | `{status}` | 200 |
| POST | `/deployments/{id}/items` | user | Deployment → `assignItem` | `{item_id}` | 200 |
| DELETE | `/deployments/{id}/items/{itemId}` | user | Deployment → `removeItem` | — | 200 |
| GET | `/repairs` | user | Repair → `getRepairs` | — | 200 `[repairs]` |
| GET | `/repairs/{id}` | user | Repair → `getRepair` | — | 200 repair + `actions[]` |
| POST | `/repairs` | user | Repair → `createRepair` | `{item_id, issue_description}` | 200 `{id}` |
| PUT | `/repairs/{id}/status` | user | Repair → `updateStatus` | `{status}` | 200 |
| POST | `/repairs/{id}/actions` | user | Repair → `addAction` | `{action, notes?}` | 200 |
| GET | `/custom-fields[/{type}]` | user | CustomField → `getAllFields` / `getFieldsByType` | — | 200 `[fields]` |
| POST | `/custom-fields` | admin | CustomField → `createField` | `{item_type, field_name, field_type?, is_required?}` | 201 `{id}` |
| DELETE | `/custom-fields/{id}` | admin | CustomField → `deleteField` | — | 200 |
| ANY | `/schema-check`, `/migrate` | none | inline in `index.php` | — | table existence report |

**Error shape:** `{"message": "..."}`. Unknown method/path combinations inside a controller return 405. An unknown resource returns 404. On the frontend, `fetchAPI` turns **every** non-2xx response into `null`, so server messages never reach the user.

---

## 5. Data model

The full data dictionary is in `DATABASE_SCHEMA.md`. The authoritative DDL is `schema.sql` (idempotent). `database.sql` drops every table first, so never run it against real data.

### 5.1 Core design

- **Unified registry:** `inventory_items` holds every physical unit (`item_type`, unique `qr_code`, `serial_number`, `model_id`, `status`, `metadata` JSON, `batch_id`, soft-delete columns), with optional 1:1 spec tables.
- **Assembly:** `asset_hierarchy(parent_id, child_id, slot_info, status installed|removed)`.
- **Lifecycle:** `inventory_items.status` ∈ `available`, `installed`, `deployed`, `defective`, `under_repair`, `retired`. The allowed changes are seeded in `status_transitions`, but nothing enforces them.

### 5.2 Table usage by the application

| Table | Read | Written | Used by |
|---|:---:|:---:|---|
| `users` | ✅ | ✅ | Auth, User admin |
| `inventory_items` | ✅ | ✅ | Everything |
| `item_models`, `manufacturers` | ✅ | ✅ (find-or-create) | Stock creation, lists |
| `spec_cabinets`, `spec_modules`, `spec_power_supplies`, `spec_video_processors` | ✅ | ✅ | Stock creation, lists |
| `asset_hierarchy` | ✅ | ✅ | Cabinet assembly |
| `inventory_logs` | ✅ | ✅ | Audit timeline |
| `qr_scan_logs` | ❌ | ✅ | Scan (write-only) |
| `custom_fields` | ✅ | ✅ | Custom fields |
| `deployments`, `deployment_items` | ✅ | ✅ | Deployments |
| `locations` | ✅ | ✅ (event sites only) | Deployments |
| `repairs`, `repair_actions` | ✅ | ✅ | Repairs |
| `status_transitions` | ❌ | ❌ | Seeded, not enforced |
| `stock_movements` | ❌ | ❌ | Unused |
| `warranties`, `suppliers`, `purchase_orders` | ❌ | ❌ | Unused |
| `model_compatibility`, `cabinet_templates` | ❌ | ❌ | Unused |
| `maintenance_schedules` | ❌ | ❌ | Unused |

Columns that exist in the schema but are never set: `performed_by`, `created_by`, `updated_by`, `approved_by`, `technician_id`, `repair_location_id`, `cost`, `downtime_hours`, `priority` (left at its default), `returned_at`, `pos_x`, `pos_y`, `screen_name`, `batch_id`, `scanned_by`, and `spec_modules` resolution and `spec_cabinets` weight.

---

## 6. Item lifecycle as implemented

```
             create stock
                 │
                 ▼
  ┌──────────► available ◄──────────────┬──────────────┬────────────────┐
  │              │                      │              │                │
  │   install    │   assign to event    │ remove from  │ return from    │ resolve
  │   into cab.  │                      │ cabinet      │ deployment     │ repair
  │              ▼                      │              │                │
  │   installed ─┘   deployed ──────────┘──────────────┘                │
  │                                                                     │
  │   report defect (from ANY status) ──► under_repair ─────────────────┘
  │
  └── manual status change: ANY → ANY (no transition rules)
```

The automatic changes are: install → `installed`, remove → `available`, assign → `deployed`, return → `available`, report defect → `under_repair`, resolve → `available`. None of them checks the item's current status on the server, except install, which requires `available`. `defective` and `retired` can only be set manually.

---

## 7. Security model as implemented

- **Authentication:** an opaque 64-hex bearer token, stored in plain text in `users.auth_token` and in the browser's `localStorage`. It has no expiry and is checked with a database lookup on every request.
- **Authorization:** checked on the server only for admin endpoints (`/users`, custom-field writes). The other four roles are equivalent. Hiding the Admin link in the UI is cosmetic; the server-side check is what protects those endpoints.
- **SQL:** prepared statements throughout. (The table names in `SHOW TABLES LIKE '$tbl'` come from a hardcoded list.)
- **Output encoding:** most renderers put API data into `innerHTML` without escaping. Only `Toast` and page titles use `textContent`.
- **Transport and hosting:** Cloudflare Tunnel → Nginx on localhost. Nginx blocks dotfiles; every `*.php` file under the web root can be executed.
- **Secrets:** database credentials are hardcoded in `api/config/database.php`. The seed admin uses a documented default password.

---

## 8. Non-functional characteristics

| Area | Current state |
|---|---|
| Performance | No pagination anywhere: lists load every row and filter in the browser. `users.auth_token` is not indexed. |
| Reliability | Multi-step writes (stock creation, install/remove, assign/return, repair create/resolve) use transactions. Audit log writes swallow errors. |
| Error handling | The backend often returns raw PDO messages. The frontend collapses all errors to `null` and shows generic toasts. |
| Offline / network | Depends on CDNs (Ionicons, Google Fonts, QRious, html5-qrcode). html5-qrcode is unpinned and has no SRI. |
| Mobile | Responsive shell with a bottom nav and a camera scanner; USB scanner support for kiosks. |
| Testability | No automated tests, linting or build. |

---

## 9. Requirements traceability

Status of each requirement in `requirements.md` against the current code. ✅ implemented, 🟡 partial, ❌ not implemented.

| ID | Requirement | Status | Notes |
|---|---|:---:|---|
| FR-1.1 | Role-based access (admin, manager, warehouse, technician) | 🟡 | Roles are stored; only admin is enforced. The UI can create only staff or admin. |
| FR-1.2 | Active status and soft delete for users | 🟡 | Soft delete is written, but login and token checks ignore it. |
| FR-1.3 | User accountability for critical actions | ❌ | `performed_by` / `created_by` are never set. |
| FR-2.1 | Unified registry of all hardware types | ✅ | `inventory_items` + `item_type` |
| FR-2.2 | Unique QR and optional serial per item | ✅ | Auto-generated; QR = serial |
| FR-2.3 | Type-specific specs | 🟡 | Pixel pitch, voltage/current and size only; resolution, weight and wattage are not captured |
| FR-2.4 | LED batch/lot tracking | ❌ | `batch_id` column unused |
| FR-2.5 | Warranty tracking | ❌ | `warranties` unused |
| FR-2.6 | Dynamic metadata | ✅ | Custom fields → `metadata` JSON |
| FR-3.1 | Arbitrary-depth hierarchy | 🟡 | Schema supports it; UI supports cabinet → component only |
| FR-3.2 | Compatibility rules | ❌ | `model_compatibility` unused; install doesn't check types |
| FR-3.3 | Cabinet templates (BOM) | ❌ | `cabinet_templates` unused |
| FR-4.1 | Operational status tracking | ✅ | Six statuses |
| FR-4.2 | Enforced state transitions | ❌ | `status_transitions` seeded, never read |
| FR-5.1 | Stock movement log | ❌ | `stock_movements` unused |
| FR-5.2 | Status audit log with before/after and user | 🟡 | `inventory_logs` written; no user; some rows lack from/to |
| FR-5.3 | Scan log with technician and location | 🟡 | Row written; no user or location |
| FR-6.1 | Hierarchical locations | ❌ | Only flat `event_site` rows auto-created |
| FR-6.2 | Location types | 🟡 | Only `event_site` used |
| FR-7.1 | Deployments with dates, status and approval | 🟡 | No approval workflow; no edit or delete |
| FR-7.2 | Items mapped to deployments | ✅ | |
| FR-7.3 | Screen positions (pos_x, pos_y) | ❌ | Columns read, never written or shown |
| FR-8.1 | Repair lifecycle with priority, location, technician | 🟡 | Status and issue only |
| FR-8.2 | Repair actions, swapped parts, cost, downtime | 🟡 | Actions only |
| FR-8.3 | Preventive maintenance | ❌ | `maintenance_schedules` unused |
| FR-9.1 | Manufacturers and suppliers | 🟡 | Manufacturers find-or-created; suppliers unused |
| FR-9.2 | Purchase orders | ❌ | `purchase_orders` unused |
| NFR-1.x | FK, CHECK, NOT NULL, UNIQUE constraints | ✅ | In `schema.sql` |
| NFR-2.1 | Indexes on hot fields | 🟡 | Present on most; `users.auth_token` unindexed |
| NFR-3.1 | No hard deletes of critical records | 🟡 | Users and items soft-deleted; deployment links and custom fields hard-deleted |
| NFR-3.2 | `retired` is terminal | ❌ | Any status can leave `retired` |
| NFR-4.x | Spec subtables, JSON metadata | ✅ | |
| NFR-5.1 | MySQL 8.0+ | ✅ | |

---

## 10. Known issues

The subagent reviews found these; I spot-checked the items marked ✔︎ against the source. Ordered by severity.

### Critical / security

| # | Issue | Location |
|---|---|---|
| S1 ✔︎ | Soft-deleted users can still sign in: the login query doesn't check `is_active` / `deleted_at` | `AuthController.php:37` |
| S2 | Deleted users' existing tokens remain valid (token check ignores status; delete doesn't clear the token) | `auth.php:22`, `UserController.php:74` |
| S3 | `update_admin.php` resets the admin password to a known value with no auth, and Nginx executes any `*.php` under the web root. Don't deploy it (or `hash.php`). | `update_admin.php`, `nginx.conf:52-57` |
| S4 | Stored XSS: usernames, item fields, notes, issue text, deployment names and custom-field names are rendered with `innerHTML` without escaping; tokens in `localStorage` are reachable by injected script | `admin.js:24,27`, `nav.js:63-64`, `stocks.js:215-219`, `profile.js`, `repairs.js`, `repair-detail.js`, `deployments.js`, `deployment-detail.js`, `settings.js:25`, `print.js:21,23` |
| S5 | `/api/schema-check` and `/api/migrate` are unauthenticated | `api/index.php:61-99` |
| S6 | No login rate limiting; tokens never expire; tokens stored in plain text | `AuthController.php` |
| S7 | Raw database error messages are returned to clients | most controllers, `database.php:20` |
| S8 | Hardcoded DB credentials (root) | `api/config/database.php` |

### Data integrity

| # | Issue | Location |
|---|---|---|
| D1 ✔︎ | Status transitions are not enforced anywhere | no reader of `status_transitions` |
| D2 ✔︎ | A component can be removed from a cabinet only once; the second removal hits `UNIQUE(child_id, status)` → 500 | `schema.sql:249`, `CabinetController.php:240` |
| D3 | Assign, return, repair-create and repair-resolve overwrite item status unconditionally (e.g. a defective item returned from an event becomes `available`) | `DeploymentController.php:144,164`, `RepairController.php:88,127` |
| D4 | An item can be assigned to several deployments at once; the server doesn't check status on assign | `DeploymentController.php:135-145` |
| D5 | Install doesn't check that the parent is a cabinet or that the child isn't a cabinet; remove doesn't check that the link exists | `CabinetController.php:194-255` |
| D6 | Status PUT isn't scoped to the item type or validated against the enum; there's no try/catch, so invalid values give non-JSON 500s | `CabinetController.php:176-192`, `ComponentController.php:211-227` |
| D7 ✔︎ | Cabinet create log passes the note in the `to_status` slot, and errors are swallowed, so the log row is probably lost | `CabinetController.php:160` |
| D8 | Serial counter is read outside the transaction; concurrent creates can collide on `qr_code` | `CabinetController.php:129-137`, `ComponentController.php:141-149` |
| D9 | Cancelling a repair leaves the item `under_repair`; resolving one of several open tickets frees the item | `RepairController.php:119-131` |
| D10 | No user attribution in any log or record | all controllers |
| D11 ✔︎ | Blank deployment dates are sent as `''`, which can fail under MySQL strict mode | `deployments.js:64-65`, `DeploymentController.php:107` |
| D12 | Dashboard counts include retired and soft-deleted items | `StatsController.php:23,27` |

### UX / functional

| # | Issue | Location |
|---|---|---|
| U1 | `fetchAPI` turns every error into `null`: outages look like "invalid password", "no items" or "not found" | `api.js:14-25` |
| U2 | Camera scanner stops after the first decode and never restarts; it can fire duplicate scans before stopping | `scanner.js:46-58`, `scanner-page.js:41` |
| U3 | The repair status update and deployment status update don't refresh the page | `repair-detail.js:72-81`, `deployment-detail.js:70-75` |
| U4 | The Add Stock form resets the item type to Cabinet after each submit | `stocks.js:84` |
| U5 | Defect reporting uses `window.prompt()`; tickets can't be created from the Repairs page | `profile.js:143-149` |
| U6 | No edit or delete for deployments, users or tickets; no user password change | — |
| U7 | `initMobileSidebar` / `statusBadge` are copied into each page script | `assets/js/pages/*.js` |

---

## 11. File index

| File | Responsibility | Features |
|---|---|---|
| `index.html` | Login page + inline login script | F1 |
| `dashboard.html`, `assets/js/pages/dashboard.js` | Stats and quick actions | F5 |
| `scanner.html`, `assets/js/scanner.js`, `assets/js/pages/scanner-page.js` | Camera / USB scanning | F12 |
| `stocks.html`, `assets/js/pages/stocks.js`, `assets/css/pages/stocks.css` | Add stock, list/filter, bulk print | F6, F7, F8 |
| `profile.html`, `assets/js/pages/profile.js` | Item profile, status, defect, label, assembly, history | F9, F10, F11, F13, F14 |
| `deployments.html`, `assets/js/pages/deployments.js` | Event list and create | F15 |
| `deployment-detail.html`, `assets/js/pages/deployment-detail.js` | Event status, assign/return | F15 |
| `repairs.html`, `assets/js/pages/repairs.js` | Ticket list | F14 |
| `repair-detail.html`, `assets/js/pages/repair-detail.js` | Ticket status and actions | F14 |
| `settings.html`, `assets/js/pages/settings.js` | Account info, sign out, custom-field admin | F2, F16 |
| `admin.html`, `assets/js/pages/admin.js` | User management | F4 |
| `assets/js/api.js` | HTTP client for every endpoint | all |
| `assets/js/auth.js` | Page guard, logout | F1, F2 |
| `assets/js/nav.js` | Sidebar and bottom nav | F3 |
| `assets/js/toast.js` | Notifications | all |
| `assets/js/print.js` | QR label printing | F8 |
| `assets/css/variables.css`, `layout.css`, `components.css` | Theme, shell, components, print styles | all |
| `api/index.php` | API router and auth gating; schema-check/migrate | all, F17 |
| `api/config/auth.php` | `authenticateRequest()` | F2, all protected |
| `api/config/database.php` | PDO connection | all |
| `api/controllers/AuthController.php` | login, logout, me | F1, F2 |
| `api/controllers/UserController.php` | User CRUD (admin) | F4 |
| `api/controllers/StatsController.php` | Dashboard counts | F5 |
| `api/controllers/CabinetController.php` | Cabinet CRUD, status, install/remove | F6, F7, F10, F11 |
| `api/controllers/ComponentController.php` | Component CRUD, status | F6, F7, F10 |
| `api/controllers/CustomFieldController.php` | Custom-field definitions | F16 |
| `api/controllers/ScanController.php` | QR lookup and scan log | F12 |
| `api/controllers/LogController.php` | Activity history | F13 |
| `api/controllers/DeploymentController.php` | Deployments and assignments | F15 |
| `api/controllers/RepairController.php` | Repair tickets and actions | F14 |
| `schema.sql` | Idempotent production DDL + seeds | — |
| `database.sql` | **Destructive** (DROP + CREATE) schema script | — |
| `DATABASE_SCHEMA.md`, `schema_erd*.mmd` | Data dictionary and ERDs | — |
| `router.php` | Local PHP dev-server router | — |
| `nginx.conf` | Production web server config | — |
| `update_admin.php`, `hash.php` | One-off admin password scripts (do not deploy) | — |
| `api/test.php` | Legacy schema check (references removed tables) | — |
| `assets/js/app.js.bak` | Legacy SPA, unused | — |
