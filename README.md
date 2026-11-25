# JMACX LED Inventory

A Laravel web application for tracking LED display hardware through stock, deployments, repairs, and retirement, with QR-code scanning.

**Stack:** Laravel 13 · PHP 8.3+ · MySQL 8 / MariaDB 10.5+ · Blade views with the existing vanilla JavaScript and CSS assets.

## Features

- Inventory management for cabinets, modules, power supplies, receiving cards, hubs, and video processors
- QR-code lookup, cabinet assembly, deployments, repairs, and audit history
- Dashboard statistics, custom fields, role-based administration, and customer self-registration
- Customer purchase requests for available stock, admin quotes/review, sold-item tracking, and customer-owned repair tickets
- Staff repair-ticket review and admin user management

## Requirements

- PHP 8.3+ with `pdo_mysql`
- Composer 2
- MySQL 8.0+ or MariaDB 10.5+

## Local setup

1. Start MySQL. Apache is optional when using Laravel's built-in development server.
2. Create an empty MySQL database matching `DB_DATABASE` in `.env` (the default is `led_inventory`).
3. Install dependencies and configure Laravel:

   ```powershell
   composer install
   Copy-Item .env.example .env
   php artisan key:generate
   ```

4. Set `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, and `DB_PASSWORD` in `.env`.
5. Run Laravel migrations to create or update the schema without dropping existing tables or records:

   ```powershell
   php artisan migrate
   ```

   This replaces the need to import `schema.sql` for normal setup. It creates or updates the inventory, customer, repair, assembly, and customer-order schema. Existing tables and records are retained.

6. Load demo data into a disposable/demo database, or skip this step to preserve an existing inventory:

   ```powershell
   php artisan db:seed
   ```

   **Warning:** `php artisan db:seed` refreshes the demo dataset and deletes existing records from the demo tables it seeds, including inventory, users, deployments, repairs, and customer orders. Run it only on a disposable/demo database. Do not run it against data you need to keep.

   To add or refresh just the demo customer on an existing database without refreshing the rest of the demo data:

   ```powershell
   php artisan db:seed --class="Database\Seeders\CustomerSeeder"
   ```

   To restore only the default admin account without refreshing application data:

   ```powershell
   php artisan db:seed --class="Database\Seeders\AdminSeeder"
   ```

7. Start Laravel:

   ```powershell
   php artisan serve
   ```

   Open <http://127.0.0.1:8000>. `GET /api/schema-check` reports the status of the existing inventory tables.

For a manual SQL setup, create the database, import `schema.sql`, then import `seed.sql` to load the inventory demo records and staff accounts. Run `php artisan db:seed --class="Database\Seeders\CustomerSeeder"` to add the demo customer and its example repair ticket. The Laravel seeder requires the `.env` database connection to point at that database.

> **Do not run `database.sql`** on a database you need; it drops existing tables.

### Demo accounts

The full `php artisan db:seed` demo seeder creates all accounts below. Passwords are for local testing only; change them before deployment.

| Username | Password | Name | Role / status |
| --- | --- | --- | --- |
| `admin` | `admin123` | System Admin | Administrator |
| `customer.demo` | `customer123` | Demo Customer | Customer |
| `maria.santos` | `password123` | Maria Santos | Manager |
| `jose.reyes` | `password123` | Jose Reyes | Warehouse |
| `ana.cruz` | `password123` | Ana Cruz | Technician |
| `mark.dizon` | `password123` | Mark Dizon | Technician |
| `lea.garcia` | `password123` | Lea Garcia | Staff; can review repair tickets |
| `paolo.lim` | `password123` | Paolo Lim | Technician; **inactive demo account**, cannot sign in |

`seed.sql` creates the five active staff accounts and inactive Paolo; `schema.sql` provides the admin; Laravel's `CustomerSeeder` adds the customer. A full Laravel seed combines these records and demo inventory.

## Using the system

1. Start the app with `php artisan serve` and open <http://127.0.0.1:8000> (or the host/port shown by Artisan).
2. Sign in with one of the demo accounts above. Use the sidebar on desktop or the bottom navigation on mobile; use **Log out** to end the session.
3. Use the sections below for the main workflows. Pages and API actions are restricted by role:

| Role | Main capabilities |
| --- | --- |
| Admin | Manage inventory, users, repairs, deployments, custom fields, and customer purchase requests. Only admins can create/edit users or review customer orders. |
| Manager, warehouse, technician | Use inventory, scanner, and deployment pages; submit and view their own repair tickets. These roles cannot manage user accounts or review all repair tickets/customer orders. |
| Staff | Use operational pages and accept/decline pending repair tickets. Staff cannot manage users or customer orders. |
| Customer | Browse the Shop, submit purchase requests, see only their own requests and repair tickets, and submit repair tickets. Customers cannot access staff inventory or user-management APIs. |

Public registrations are assigned the Customer role.

### Inventory and QR codes

1. Open **Stocks** (`stocks.php`) and choose a hardware type.
2. Use **Add Stock** to register units; each physical unit receives its own QR code. Use **View Stocks** to search and filter by model, QR code, serial number, or status.
3. Open an item's profile to review its details and activity, print its label, or update its operational status. Sold items are read-only in the normal status controls.
4. Open **Scanner** (`scanner.php`) to scan or enter a QR code and open the matching item profile. Camera scanning requires localhost or HTTPS.
5. For cabinet assemblies, open a cabinet profile and install available compatible components into the assembly.

### Deployments

1. Open **Deployments** (`deployments.php`) and create a deployment with its name, location, and dates.
2. Open the deployment to assign inventory items and maintain the deployment status.
3. Remove or return assigned items through the deployment detail page when the event is complete. Item changes are reflected in inventory and audit history.

### Repairs

1. Open **Repairs** (`repairs.php`) and submit a ticket with the item's QR code, reason, and description. Customers can submit tickets too; each customer only sees their own tickets.
2. A user with the **Staff** or **Admin** role reviews pending tickets and accepts or declines them.
3. After acceptance, staff/admin can update the repair status and record repair actions in the ticket detail page.

### Customer purchase request flow

1. Sign in as `customer.demo` and open **Shop** (`shop.php`).
2. Choose quantities from the displayed available stock and submit the request. This is a request for a quote/review, not an online checkout: no payment is collected and stock is not reserved while the request is pending.
3. An admin opens **Customer Orders** (`orders.php`), reviews the requested models and quantities, enters an optional quote/update in the notes, then accepts or declines the request.
4. On acceptance, the system allocates the requested available units in one transaction, marks them **sold**, and records the inventory movement and status audit. If there is not enough stock at review time, nothing is partially sold and the request stays pending.
5. The customer checks **My Orders** to see the status and any quote or update from the admin. A declined request does not change stock.

### Users and settings

- Admins open **Admin** to create, edit, activate/deactivate, or change the roles/passwords of user accounts.
- **Settings** provides system configuration such as custom inventory fields; custom-field changes are admin-only.
- Customers cannot use inventory, deployment, user-management, or admin order-review APIs even if they manually enter a URL.

Change all demo passwords before deploying. Camera-based QR scanning works on `localhost`; other devices require HTTPS.

## Laravel structure

```text
app/                    Laravel controllers, models, and providers
bootstrap/               Laravel application bootstrap
config/                 Framework configuration (database settings come from .env)
database/                Laravel database tooling
database/migrations/     Laravel schema migrations (`php artisan migrate`)
database/seeders/        Laravel demo and customer seeders
public/                  Web document root, CSS/JS assets, and QR sample images
resources/views/pages/   Blade-rendered application pages
routes/                  Laravel web and API routes
api/controllers/         Existing API resource handlers
schema.sql               Existing MySQL schema and initial admin account
seed.sql                 Optional demo data
```

Web pages are served by Laravel from Blade views through `.php` routes. Legacy `.html` requests redirect to the corresponding `.php` URL. API requests are routed through Laravel at `/api/*`; the resource handlers retain their existing response contract and use Laravel's configured database connection. Their PDO-based implementation is kept for compatibility with the current API and schema.

## Tests

Run the Laravel feature tests with:

```powershell
php artisan test
```

To load or refresh the complete demo dataset after running `php artisan migrate` (only on a disposable/demo database):

```powershell
php artisan db:seed
```

For preserving existing data, use the customer-only or admin-only seeder instead of the full demo seeder. `php artisan migrate` applies the customer-role, repair-review, assembly, and customer-order schema updates to older databases.

## Production

Point the web server document root to the project's `public/` directory. The provided `nginx.conf` routes requests through Laravel's front controller. Configure database credentials and `APP_URL` in the deployment environment.

## Documentation

- `requirements.md` — functional and non-functional requirements
- `usecase.md` — use cases
- `DATABASE_SCHEMA.md` — tables and columns
- `docs/PRD.md` — product requirements
