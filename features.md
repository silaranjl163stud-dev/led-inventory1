# JMACX-CGT-ASSETS LED Inventory System - Project Analysis

## Overview
This directory (`for_inventory`) contains the codebase for a local network, mobile-first web application designed for comprehensive LED equipment inventory management. The application operates primarily via a centralized server configured under an isolated, local HTTPS NGINX proxy, enforcing strict security while being easy to deploy.

## Technology Stack
- **Frontend**: Vanilla HTML/JS/CSS. Formatted as a Single Page Application (SPA), utilizing CSS variables, responsive layouts, and Ionicons for visual assets. 
- **Backend API**: Vanilla PHP. Represents a lightweight RESTful API structure enforcing clean separation of concerns.
- **Database Engine**: MySQL. Outlined via connection configurations (`database.php`) and table migrations (`database_migrations.sql`), though a separate local `schema.db` SQLite storage file exists in the directory.
- **Server Infrastructure**: NGINX with PHP-FPM. Includes complete production-grade setup to aggressively redirect HTTP to HTTPS, provision self-signed certificates for local deployment, block dotfile enumeration, and establish strict Content Security Policies.
- **Scanning Technology**: Utilizes `html5-qrcode` handling software-based camera decoding while running isolated JS DOM events for hardware barcode/laser scanners.

## Project Structure

- **`/api`**: Houses the PHP backend core logic.
  - **`/config`**: Contains database connections and authorization handler rules (`auth.php`).
  - **`/controllers`**: Specialized modules parsing API endpoints to specific database tasks:
    - `AuthController.php`: Issues login validations and generates tokens.
    - `UserController.php`: Performs granular CRUD operations to administer staff vs admin.
    - `CabinetController.php` & `ComponentController.php`: Handlers specifically tracking LED cabinet metrics and their installed secondary hardware items.
    - `ScanController.php`: Master hub cross-matching raw hardware QR queries against six different hardware domains simultaneously.
  - **`index.php`**: System-wide API router that parses URLs into the correct Controller logic.

- **`/assets`**: Manages the application aesthetic and browser dynamics.
  - **`/css`**: Modular stylesheets handling system colors (`variables.css`), grids/structural layouts (`layout.css`), and elements (`components.css`). It exhibits modern dark-mode aesthetics defined by "glassmorphism" blur panels.
  - **`/js`**: DOM interactions. `app.js` runs state workflows, view switching, and navigation. `api.js` connects browser forms to the backend. `scanner.js` integrates device camera hooks.

- **Root Files**:
  - `index.html`: Master presentation file structured with hidden `<section>` tags dynamically swapped into view using JavaScript. 
  - `er.xlsx`: Database Entity-Relationship structural planning spreadsheet.
  - `database_migrations.sql`: Base queries seeding dynamic administrator accounts and role relationships.

---

## Core Features & Functionalities

### 1. Robust Role-Based Access Control (RBAC)
- Token-authenticated sessions cached securely in modern browser storage environments.
- Support for complex roles (Administrative vs. Staff logic) evaluating UI availability. For example, the **Admin Dashboard** is functionally hidden and forbidden unless the verified session token correlates to an `admin` domain database hit.
- Administrative capabilities to provision secondary generic active system users manually and revoke access dynamically.

### 2. Universal Hybrid Scanning Workflows
- **PWA-Like Hardware Integrations**: A tailored scanning interface merges input domains. Workers utilizing mobile endpoints rely easily on their back-facing cameras. Meanwhile, workers stationed at fixed warehouse kiosks with specialized USB scanners rely on isolated hidden text fields ("Ready for physical scanner") natively auto-focusing to process physical inputs without UI interruptions.
- **Aggregated Record Retrieval**: Rather than explicit queries limiting queries to explicit tables, the backend intelligently queries every active asset database independently (`cabinets`, `modules`, `power_supplies`, `receiving_cards`, `hubs`, and `video_processors`) to return the exact item matrix matching the incoming string hash, mitigating user error completely.

### 3. Dynamic Composition & Inventory Relational States
- Detailed status tracking profiles loaded instantly via JSON representations of retrieved scanning hits.
- **Drill-Down Matrix Reporting**: Unique items like LED `Cabinets` inherently load interactive components. An individual examining a cabinet can optionally expand details, triggering an XHR request to reveal every specific sub-component (down to exact receiving card hashes) mechanically installed inside said cabinet.
- **Audit Logging**: Successful item queries passively record timestamped workflow validations (`action: status_change`) automatically into isolated log records (`inventory_logs`) outlining transparent location tracking natively.

### 4. Advanced Frontend UX Architecture
- Formats visual queues utilizing modern non-blocking "Toasts" for alert management.
- Dynamic global statistics grids delivering immediate visibility of total infrastructure scales (`204 Cabinets`, `1802 Modules`).
- Implements Floating 'Bottom Navigation' Action bars optimized exceptionally well for dense mobile touchscreen layouts, highlighting immediate quick-action "Scan" buttons bridging standard workflows organically.
