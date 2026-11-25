# LED Inventory Management System Requirements

Based on the engineering specifications and schema analysis for the LED Inventory Management System (Version 2.0), the following Functional and Non-Functional requirements have been derived.

## 1. Functional Requirements (FR)

### 1.1 User & Access Management
*   **FR-1.1:** The system shall support role-based access control with predefined roles including `admin`, `manager`, `warehouse`, and `technician`.
*   **FR-1.2:** The system shall track the active status of users and support soft deletion to preserve accountability and historical audit trails.
*   **FR-1.3:** The system shall record user accountability for all critical actions, including item creation, updates, stock movements, state changes, and repairs.

### 1.2 Asset & Inventory Tracking
*   **FR-2.1:** The system shall manage a unified asset registry supporting diverse hardware types including cabinets, modules, power supplies, receiving cards, hubs, and video processors.
*   **FR-2.2:** The system shall enforce a unique QR code and track optional serial numbers for every physical inventory item.
*   **FR-2.3:** The system shall track type-specific hardware specifications (e.g., pixel pitch and resolution for modules, voltage and wattage for power supplies, size and weight for cabinets).
*   **FR-2.4:** The system shall track LED batch/lot IDs to facilitate color and brightness matching across deployments.
*   **FR-2.5:** The system shall track warranty information per item, including purchase dates, expiration dates, and associated suppliers.
*   **FR-2.6:** The system shall allow the storage of dynamic, unstructured metadata (e.g., firmware versions, MAC addresses, calibration data) for inventory items.

### 1.3 Physical Hierarchy & Assembly
*   **FR-3.1:** The system shall support mapping hardware components in an arbitrarily deep parent-child physical hierarchy (e.g., Screen → Cabinet → Module → Receiving Card).
*   **FR-3.2:** The system shall enforce component compatibility rules to ensure only valid hardware models can be assembled together.
*   **FR-3.3:** The system shall define cabinet templates (Bill of Materials) to validate the completeness of assemblies (e.g., verifying required counts of modules, power supplies, and cards).

### 1.4 Status & Lifecycle Management
*   **FR-4.1:** The system shall track the operational status of all items (`available`, `installed`, `deployed`, `defective`, `under_repair`, `retired`).
*   **FR-4.2:** The system shall strictly enforce valid state transitions using a predefined lookup table to prevent illogical data changes (e.g., preventing a `retired` item from transitioning directly to `deployed`).

### 1.5 Movements, Auditing, & Scanning
*   **FR-5.1:** The system shall maintain a dedicated log of all physical stock movements between defined locations.
*   **FR-5.2:** The system shall maintain a separate audit log of all status transitions, capturing the before/after state, timestamp, and the responsible user.
*   **FR-5.3:** The system shall record every QR code scan performed, capturing the technician's ID, scan location, and timestamp for anti-loss tracking.

### 1.6 Location Management
*   **FR-6.1:** The system shall manage physical locations hierarchically (e.g., Site → Warehouse → Shelf → Bin).
*   **FR-6.2:** The system shall categorize locations by functional types (e.g., warehouse, event site, repair center, truck, office).

### 1.7 Deployments
*   **FR-7.1:** The system shall track deployments (events/projects), including location, start/end dates, status, and approval workflows.
*   **FR-7.2:** The system shall map assigned inventory items to specific deployments.
*   **FR-7.3:** The system shall record the physical screen positioning (`pos_x`, `pos_y`) of deployed cabinets/modules to facilitate rapid defect localization.

### 1.8 Maintenance & Repairs
*   **FR-8.1:** The system shall track the full lifecycle of repairs, including priority, issue description, repair location, and assigned technician.
*   **FR-8.2:** The system shall log specific repair actions, swapped components, total repair costs, and total downtime hours.
*   **FR-8.3:** The system shall support the scheduling, assignment, and tracking of preventive maintenance tasks for hardware components.

### 1.9 Procurement (ERP-lite)
*   **FR-9.1:** The system shall maintain a registry of hardware manufacturers and suppliers.
*   **FR-9.2:** The system shall track purchase orders, including their lifecycle states (`draft`, `submitted`, `received`, `cancelled`) and associated users.

---

## 2. Non-Functional Requirements (NFR)

### 2.1 Data Integrity & Database Architecture
*   **NFR-1.1:** The database shall eliminate polymorphic associations and rely strictly on enforceable foreign key relationships to prevent orphaned records.
*   **NFR-1.2:** The database shall enforce data validity at the schema level using `CHECK` constraints (e.g., ensuring numeric values like pixel pitch and current are > 0).
*   **NFR-1.3:** The database shall enforce strict `NOT NULL` constraints on mandatory business fields.
*   **NFR-1.4:** The database shall utilize `UNIQUE` constraints to prevent duplicate QR codes, duplicate transition rules, and duplicate model name combinations.

### 2.2 Performance & Scalability
*   **NFR-2.1:** The database shall include optimized indexes on frequently queried fields (`status`, `item_type`, `qr_code`, `serial_number`, timestamps, and locations) to ensure rapid dashboard loading and low-latency barcode scanning.
*   **NFR-2.2:** The system architecture shall support deep physical nesting hierarchies without requiring underlying schema modifications.

### 2.3 Data Preservation
*   **NFR-3.1:** The system shall never execute hard deletes on critical records (users, inventory items). Soft deletion (`deleted_at`, `is_active`) must be implemented to preserve the permanent audit history.
*   **NFR-3.2:** "Retired" shall be enforced as the terminal application state for physical assets; database hard deletes shall be blocked at the application level.

### 2.4 Maintainability & Extensibility
*   **NFR-4.1:** The database schema shall adopt a "unified parent / spec subtable" pattern to cleanly decouple common asset properties from highly type-specific properties, reducing sparse columns.
*   **NFR-4.2:** The system shall utilize native `JSON` column types for peripheral metadata to avoid schema migrations when tracking new minor attributes.

### 2.5 Technology Constraints
*   **NFR-5.1:** The underlying database schema must be fully compatible with, and deployed against, MySQL 8.0 or higher.