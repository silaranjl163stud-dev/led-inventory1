# LED Inventory Management System -- Database Schema Specification & Data Dictionary

**Document Version:** 2.0  
**Target Engine:** MySQL 8.0+ / MariaDB 10.5+  
**Default Charset:** `utf8mb4`  
**Default Collation:** `utf8mb4_unicode_ci`  

---

## 1. Architectural Overview & Design Patterns

The LED Inventory Management System schema is designed around high-performance tracking of modular electronic display hardware across warehousing, transit, field deployment, repair, and retirement lifecycles.

### Key Architectural Patterns
1. **Single Table Inheritance (STI) with 1:1 Subtable Extensions:**  
   The central `inventory_items` table acts as the unified parent registry for all physical hardware components (`cabinet`, `module`, `power_supply`, `receiving_card`, `hub`, `video_processor`). Hardware-specific engineering specifications are stored in dedicated 1:0..1 extension tables (`spec_modules`, `spec_power_supplies`, `spec_cabinets`, `spec_video_processors`) linked by primary key `item_id` with `ON DELETE CASCADE`. This eliminates empty/null columns on the parent table and guarantees strict schema typing.
2. **Recursive Parent-Child Assembly Model (`asset_hierarchy`):**  
   Replaces legacy polymorphic associations with explicit foreign keys referencing `inventory_items.id`. This allows arbitrary nesting depths (e.g., *Video Wall Screen $\rightarrow$ Cabinet Frame $\rightarrow$ LED Module $\rightarrow$ Driver IC*) while ensuring referential integrity.
3. **Database-Governed Lifecycle Finite State Machine (`status_transitions`):**  
   Prevents invalid asset lifecycle transitions (e.g., moving directly from `retired` to `deployed`) by seeding and enforcing valid state change routes.
4. **Physical Location vs. Status Audit Separation:**  
   - `stock_movements`: Tracks spatial logistics and custody transfers between physical facilities, trucks, and event stages.
   - `inventory_logs`: Tracks operational lifecycle states, status audits, and actor accountability.
5. **Spatial Screen Coordinate Mapping (`deployment_items`):**  
   Enables mapping physical components to exact visual coordinates (`pos_x`, `pos_y`, `screen_name`) on large-scale concert or stage video walls for real-time defect isolation.

---

## 2. Comprehensive Data Dictionary

---

### Section 1: Foundation & Lookup Tables

#### Table: `users`
System user accounts, credentials, and role-based permissions.

| Column | Data Type | Nullable | Key / Constraints | Default | Description |
| :--- | :--- | :---: | :--- | :--- | :--- |
| `id` | `BIGINT` | NO | `PK`, `AUTO_INCREMENT` | *None* | Unique internal user identifier. |
| `username` | `VARCHAR(50)` | NO | `UNIQUE` | *None* | Unique login handle. |
| `password_hash` | `VARCHAR(255)` | NO | | *None* | Bcrypt/Argon2 password hash string. |
| `full_name` | `VARCHAR(100)` | NO | | *None* | Full display name of the operator. |
| `email` | `VARCHAR(150)` | YES | `UNIQUE` | `NULL` | Operator email address for notifications. |
| `role` | `ENUM(...)` | NO | `INDEX (idx_users_role)` | `'customer'` | Access role: `'admin'`, `'manager'`, `'warehouse'`, `'technician'`, `'staff'`, or `'customer'`. Customers may submit and view only their own repair tickets; staff can review tickets; admins can manage all users. |
| `auth_token` | `VARCHAR(255)` | YES | | `NULL` | Active session token or bearer credential. |
| `is_active` | `BOOLEAN` | NO | `INDEX (idx_users_active)` | `TRUE` | Whether the account is active or disabled. |
| `created_at` | `TIMESTAMP` | NO | | `CURRENT_TIMESTAMP` | Timestamp of account registration. |
| `deleted_at` | `TIMESTAMP` | YES | | `NULL` | Soft-delete timestamp for account archiving. |

---

#### Table: `custom_fields`
User-defined dynamic metadata field definitions per hardware category.

| Column | Data Type | Nullable | Key / Constraints | Default | Description |
| :--- | :--- | :---: | :--- | :--- | :--- |
| `id` | `INT` | NO | `PK`, `AUTO_INCREMENT` | *None* | Unique field identifier. |
| `item_type` | `VARCHAR(50)` | NO | `UNIQUE (uq_custom_type_field)` | *None* | Hardware type: `'cabinet'`, `'module'`, `'power_supply'`, etc. |
| `field_name` | `VARCHAR(100)` | NO | `UNIQUE (uq_custom_type_field)` | *None* | Form key name (e.g., `'pcb_version'`, `'calibration_bin'`). |
| `field_type` | `ENUM(...)` | NO | | `'text'` | Input data type: `'text'`, `'number'`, `'boolean'`, `'date'`. |
| `is_required` | `BOOLEAN` | NO | | `FALSE` | Mandatory validation rule in UI forms. |
| `created_at` | `TIMESTAMP` | NO | | `CURRENT_TIMESTAMP` | Timestamp when field was defined. |

---

#### Table: `locations`
Physical warehouses, repair facilities, trucks, and event stages with hierarchical nesting.

| Column | Data Type | Nullable | Key / Constraints | Default | Description |
| :--- | :--- | :---: | :--- | :--- | :--- |
| `id` | `BIGINT` | NO | `PK`, `AUTO_INCREMENT` | *None* | Unique location identifier. |
| `name` | `VARCHAR(100)` | NO | | *None* | Friendly facility or zone name (e.g., `'Warehouse A - Bay 4'`). |
| `type` | `ENUM(...)` | NO | `INDEX (idx_locations_type)` | *None* | Type: `'warehouse'`, `'event_site'`, `'repair_center'`, `'truck'`, `'office'`. |
| `address` | `TEXT` | YES | | `NULL` | Physical address or geo location. |
| `parent_location_id` | `BIGINT` | YES | `FK -> locations(id)` | `NULL` | Self-referencing link to parent facility/zone (`ON DELETE SET NULL`). |
| `is_active` | `BOOLEAN` | NO | | `TRUE` | Active facility status flag. |

---

#### Table: `manufacturers`
Hardware brands and original equipment manufacturers (OEMs).

| Column | Data Type | Nullable | Key / Constraints | Default | Description |
| :--- | :--- | :---: | :--- | :--- | :--- |
| `id` | `BIGINT` | NO | `PK`, `AUTO_INCREMENT` | *None* | Unique manufacturer identifier. |
| `name` | `VARCHAR(100)` | NO | `UNIQUE` | *None* | Manufacturer brand name (e.g., `'Absen'`, `'Unilumin'`, `'Novastar'`). |
| `country` | `VARCHAR(100)` | YES | | `NULL` | Country of manufacture. |
| `support_contact` | `VARCHAR(150)` | YES | | `NULL` | Tech support email or hotline. |
| `website` | `VARCHAR(200)` | YES | | `NULL` | Official corporate web portal. |

---

#### Table: `item_models`
Catalog definitions of hardware models manufactured by vendors.

| Column | Data Type | Nullable | Key / Constraints | Default | Description |
| :--- | :--- | :---: | :--- | :--- | :--- |
| `id` | `BIGINT` | NO | `PK`, `AUTO_INCREMENT` | *None* | Unique model identifier. |
| `item_type` | `ENUM(...)` | NO | `INDEX (idx_models_type)` | *None* | Type: `'cabinet'`, `'module'`, `'power_supply'`, `'receiving_card'`, `'hub'`, `'video_processor'`. |
| `manufacturer_id` | `BIGINT` | NO | `FK -> manufacturers(id)` | *None* | Reference to manufacturer (`ON DELETE RESTRICT`). |
| `model_name` | `VARCHAR(100)` | NO | `UNIQUE (uq_item_model)` | *None* | Model name/number (e.g., `'A3 Pro'`, `'MRV328'`). |
| `specifications` | `JSON` | YES | | `NULL` | Structured model spec payload (e.g., dimensions, nominal power). |

---

#### Table: `model_compatibility`
Rules detailing mechanical and electronic interoperability between models.

| Column | Data Type | Nullable | Key / Constraints | Default | Description |
| :--- | :--- | :---: | :--- | :--- | :--- |
| `id` | `BIGINT` | NO | `PK`, `AUTO_INCREMENT` | *None* | Unique compatibility rule ID. |
| `parent_model_id` | `BIGINT` | NO | `FK -> item_models(id)` | *None* | Parent chassis/cabinet model (`ON DELETE CASCADE`). |
| `child_model_id` | `BIGINT` | NO | `FK -> item_models(id)` | *None* | Child component model (`ON DELETE CASCADE`). |
| `compatibility_type` | `VARCHAR(50)` | NO | | *None* | Interop classification (`'native'`, `'requires_firmware'`, `'mechanical_only'`). |
| `notes` | `TEXT` | YES | | `NULL` | Technical constraints or firmware version requirements. |

---

#### Table: `cabinet_templates`
Bill of Materials (BOM) assembly recipes specifying required component counts for cabinets.

| Column | Data Type | Nullable | Key / Constraints | Default | Description |
| :--- | :--- | :---: | :--- | :--- | :--- |
| `id` | `BIGINT` | NO | `PK`, `AUTO_INCREMENT` | *None* | Unique template identifier. |
| `name` | `VARCHAR(100)` | NO | | *None* | Template layout name (e.g., `'500x500mm Standard 4-Module'`). |
| `model_id` | `BIGINT` | YES | `FK -> item_models(id)` | `NULL` | Associated cabinet chassis model (`ON DELETE SET NULL`). |
| `required_module_count` | `TINYINT UNSIGNED` | NO | | `0` | Number of LED modules required for full assembly. |
| `required_psu_count` | `TINYINT UNSIGNED` | NO | | `0` | Number of power supply units required. |
| `required_hub_count` | `TINYINT UNSIGNED` | NO | | `0` | Number of hub boards required. |
| `required_card_count` | `TINYINT UNSIGNED` | NO | | `0` | Number of receiving cards required. |
| `notes` | `TEXT` | YES | | `NULL` | Assembly notes, cable harness requirements. |

---

#### Table: `status_transitions`
Deterministic Finite State Machine lookup defining allowed operational transitions.

| Column | Data Type | Nullable | Key / Constraints | Default | Description |
| :--- | :--- | :---: | :--- | :--- | :--- |
| `id` | `BIGINT` | NO | `PK`, `AUTO_INCREMENT` | *None* | Unique transition rule ID. |
| `from_status` | `VARCHAR(50)` | NO | `UNIQUE (uq_status_transition)` | *None* | Current origin lifecycle status. |
| `to_status` | `VARCHAR(50)` | NO | `UNIQUE (uq_status_transition)` | *None* | Permitted destination lifecycle status. |

---

#### Table: `suppliers`
Hardware distributors, procurement partners, and warranty service centers.

| Column | Data Type | Nullable | Key / Constraints | Default | Description |
| :--- | :--- | :---: | :--- | :--- | :--- |
| `id` | `BIGINT` | NO | `PK`, `AUTO_INCREMENT` | *None* | Unique supplier identifier. |
| `name` | `VARCHAR(100)` | NO | | *None* | Corporate supplier name. |
| `contact_name` | `VARCHAR(100)` | YES | | `NULL` | Primary sales representative. |
| `contact_email` | `VARCHAR(150)` | YES | | `NULL` | Business contact email. |
| `contact_phone` | `VARCHAR(50)` | YES | | `NULL` | Contact phone number. |
| `address` | `TEXT` | YES | | `NULL` | Corporate headquarters or warehouse address. |

---

#### Table: `purchase_orders`
Inbound procurement orders tracking incoming batches and hardware lots.

| Column | Data Type | Nullable | Key / Constraints | Default | Description |
| :--- | :--- | :---: | :--- | :--- | :--- |
| `id` | `BIGINT` | NO | `PK`, `AUTO_INCREMENT` | *None* | Unique purchase order ID. |
| `supplier_id` | `BIGINT` | NO | `FK -> suppliers(id)` | *None* | Vendor fulfilling the PO (`ON DELETE RESTRICT`). |
| `po_number` | `VARCHAR(100)` | NO | `UNIQUE` | *None* | Unique procurement reference number (e.g., `'PO-2026-089'`). |
| `status` | `ENUM(...)` | NO | `INDEX (idx_po_status)` | `'draft'` | Status: `'draft'`, `'submitted'`, `'received'`, `'cancelled'`. |
| `ordered_by` | `BIGINT` | YES | `FK -> users(id)` | `NULL` | Operator who placed the order (`ON DELETE SET NULL`). |
| `ordered_at` | `TIMESTAMP` | YES | | `NULL` | Timestamp of PO submission. |
| `received_at` | `TIMESTAMP` | YES | | `NULL` | Timestamp of warehouse goods receipt. |
| `notes` | `TEXT` | YES | | `NULL` | Commercial terms or batch notes. |

---

### Section 2: Core Hardware Assets & Specifications

#### Table: `inventory_items`
Central physical asset registry holding all serialised inventory and QR references.

| Column | Data Type | Nullable | Key / Constraints | Default | Description |
| :--- | :--- | :---: | :--- | :--- | :--- |
| `id` | `BIGINT` | NO | `PK`, `AUTO_INCREMENT` | *None* | Central asset identifier. |
| `item_type` | `ENUM(...)` | NO | `INDEX (idx_items_type)` | *None* | Hardware type: `'cabinet'`, `'module'`, `'power_supply'`, `'receiving_card'`, `'hub'`, `'video_processor'`. |
| `qr_code` | `VARCHAR(100)` | NO | `UNIQUE`, `INDEX` | *None* | Encoded barcode/QR identifier affixed to hardware. |
| `serial_number` | `VARCHAR(100)` | YES | `INDEX (idx_items_serial)` | `NULL` | OEM physical serial number stamped on unit. |
| `model_id` | `BIGINT` | YES | `FK -> item_models(id)` | `NULL` | Model reference (`ON DELETE SET NULL`). |
| `batch_id` | `VARCHAR(100)` | YES | `INDEX (idx_items_batch)` | `NULL` | Production batch lot ID (critical for LED wavelength/binning matching). |
| `status` | `ENUM(...)` | NO | `INDEX (idx_items_status)` | `'available'` | Status: `'available'`, `'installed'`, `'deployed'`, `'defective'`, `'under_repair'`, `'retired'`, `'sold'`. Sold items are allocated to accepted customer orders. |
| `current_location_id` | `BIGINT` | YES | `FK -> locations(id)` | `NULL` | Current physical storage location (`ON DELETE SET NULL`). |
| `purchase_order_id` | `BIGINT` | YES | `FK -> purchase_orders(id)` | `NULL` | Procurement source PO (`ON DELETE SET NULL`). |
| `metadata` | `JSON` | YES | | `NULL` | Dynamic key-value pairs matching `custom_fields`. |
| `is_active` | `BOOLEAN` | NO | | `TRUE` | Active tracking flag (soft filter). |
| `created_by` | `BIGINT` | YES | `FK -> users(id)` | `NULL` | User who registered the item (`ON DELETE SET NULL`). |
| `updated_by` | `BIGINT` | YES | `FK -> users(id)` | `NULL` | User who last modified the record (`ON DELETE SET NULL`). |
| `created_at` | `TIMESTAMP` | NO | `INDEX (idx_items_created_at)` | `CURRENT_TIMESTAMP` | Initial intake timestamp. |
| `updated_at` | `TIMESTAMP` | NO | | `CURRENT_TIMESTAMP ON UPDATE` | Last modification timestamp. |
| `deleted_at` | `TIMESTAMP` | YES | | `NULL` | Soft delete timestamp preserving audit trails. |

---

#### Table: `spec_modules`
1:1 Hardware specification extension table for LED display modules.

| Column | Data Type | Nullable | Key / Constraints | Default | Description |
| :--- | :--- | :---: | :--- | :--- | :--- |
| `item_id` | `BIGINT` | NO | `PK`, `FK -> inventory_items(id)` | *None* | 1:1 foreign key to parent asset (`ON DELETE CASCADE`). |
| `pixel_pitch` | `DECIMAL(5,2)` | NO | `CHECK (pixel_pitch > 0)` | *None* | Center-to-center diode spacing in millimeters (e.g. `2.60`, `3.91`). |
| `resolution_width` | `SMALLINT UNSIGNED` | YES | | `NULL` | Pixel count horizontally across module. |
| `resolution_height` | `SMALLINT UNSIGNED` | YES | | `NULL` | Pixel count vertically down module. |
| `width_mm` | `SMALLINT UNSIGNED` | YES | | `NULL` | Physical module width in millimeters. |
| `height_mm` | `SMALLINT UNSIGNED` | YES | | `NULL` | Physical module height in millimeters. |
| `scan_rate` | `VARCHAR(20)` | YES | | `NULL` | Driver IC multiplexing scan rate (e.g., `'1/16'`, `'1/32'`). |
| `refresh_rate` | `SMALLINT UNSIGNED` | YES | | `NULL` | Maximum refresh frequency in Hertz (e.g., `3840`, `7680`). |

---

#### Table: `spec_power_supplies`
1:1 Hardware specification extension table for switch-mode power supplies.

| Column | Data Type | Nullable | Key / Constraints | Default | Description |
| :--- | :--- | :---: | :--- | :--- | :--- |
| `item_id` | `BIGINT` | NO | `PK`, `FK -> inventory_items(id)` | *None* | 1:1 foreign key to parent asset (`ON DELETE CASCADE`). |
| `voltage_in` | `VARCHAR(50)` | YES | | `NULL` | Input AC voltage rating (e.g., `'100-240V AC 50/60Hz'`). |
| `voltage_out` | `VARCHAR(50)` | YES | | `NULL` | Regulated DC output voltage (e.g., `'4.2V'`, `'5.0V'`). |
| `current_a` | `DECIMAL(6,2)` | YES | `CHECK (current_a > 0)` | `NULL` | Maximum rated DC current capacity in Amperes. |
| `wattage` | `SMALLINT UNSIGNED` | YES | | `NULL` | Maximum total rated output wattage in Watts (e.g., `200`, `300`). |

---

#### Table: `spec_cabinets`
1:1 Hardware specification extension table for structural cabinet frames/chassis.

| Column | Data Type | Nullable | Key / Constraints | Default | Description |
| :--- | :--- | :---: | :--- | :--- | :--- |
| `item_id` | `BIGINT` | NO | `PK`, `FK -> inventory_items(id)` | *None* | 1:1 foreign key to parent asset (`ON DELETE CASCADE`). |
| `size` | `VARCHAR(50)` | YES | | `NULL` | Industry size code (e.g., `'500x500'`, `'500x1000'`). |
| `template_id` | `BIGINT` | YES | `FK -> cabinet_templates(id)` | `NULL` | Standard assembly BOM template (`ON DELETE SET NULL`). |
| `width_mm` | `SMALLINT UNSIGNED` | YES | | `NULL` | Frame width in millimeters. |
| `height_mm` | `SMALLINT UNSIGNED` | YES | | `NULL` | Frame height in millimeters. |
| `depth_mm` | `SMALLINT UNSIGNED` | YES | | `NULL` | Frame depth in millimeters. |
| `weight_kg` | `DECIMAL(6,2)` | YES | | `NULL` | Tare weight of bare chassis in kilograms. |

---

#### Table: `spec_video_processors`
1:1 Hardware specification extension table for video controllers, scalers, and sending units.

| Column | Data Type | Nullable | Key / Constraints | Default | Description |
| :--- | :--- | :---: | :--- | :--- | :--- |
| `item_id` | `BIGINT` | NO | `PK`, `FK -> inventory_items(id)` | *None* | 1:1 foreign key to parent asset (`ON DELETE CASCADE`). |
| `firmware_version` | `VARCHAR(50)` | YES | | `NULL` | Currently flashed firmware release. |
| `input_ports` | `TINYINT UNSIGNED` | YES | | `NULL` | Quantity of video input interfaces (HDMI, DP, SDI). |
| `output_ports` | `TINYINT UNSIGNED` | YES | | `NULL` | Quantity of Gigabit Ethernet sending output ports. |
| `last_serviced_at` | `DATE` | YES | | `NULL` | Date of last factory or bench calibration. |

---

#### Table: `warranties`
Manufacturer and third-party warranty coverage terms.

| Column | Data Type | Nullable | Key / Constraints | Default | Description |
| :--- | :--- | :---: | :--- | :--- | :--- |
| `id` | `BIGINT` | NO | `PK`, `AUTO_INCREMENT` | *None* | Unique warranty record ID. |
| `item_id` | `BIGINT` | NO | `FK -> inventory_items(id)` | *None* | Covered hardware item (`ON DELETE CASCADE`). |
| `supplier_id` | `BIGINT` | YES | `FK -> suppliers(id)` | `NULL` | Warranty guarantor / vendor (`ON DELETE SET NULL`). |
| `purchase_date` | `DATE` | YES | | `NULL` | In-service or purchase start date. |
| `warranty_expiry` | `DATE` | NO | `INDEX (idx_warranties_expiry)` | *None* | Exact date when warranty coverage expires. |
| `notes` | `TEXT` | YES | | `NULL` | RMA terms, warranty contract number. |

---

### Section 3: Physical Assembly Hierarchy

#### Table: `asset_hierarchy`
Tracks subcomponents installed inside master assemblies (e.g., modules inside a cabinet).

| Column | Data Type | Nullable | Key / Constraints | Default | Description |
| :--- | :--- | :---: | :--- | :--- | :--- |
| `id` | `BIGINT` | NO | `PK`, `AUTO_INCREMENT` | *None* | Unique hierarchy link ID. |
| `parent_id` | `BIGINT` | NO | `FK -> inventory_items(id)` | *None* | Parent assembly asset (`ON DELETE CASCADE`). |
| `child_id` | `BIGINT` | NO | `FK -> inventory_items(id)` | *None* | Child component installed (`ON DELETE CASCADE`). |
| `slot_info` | `VARCHAR(50)` | YES | | `NULL` | Chassis position slot (e.g., `'Bay 1'`, `'Top-Left'`). |
| `installed_at` | `TIMESTAMP` | NO | | `CURRENT_TIMESTAMP` | Timestamp of component installation. |
| `removed_at` | `TIMESTAMP` | YES | | `NULL` | Timestamp of removal/unseating. |
| `status` | `ENUM(...)` | NO | | `'installed'` | Link status: `'installed'`, `'removed'`. |
| `active_child_id` | `BIGINT` | YES | `UNIQUE (uq_active_child)` | *Generated* | Equals `child_id` for installed links and `NULL` for removed links, allowing full removal history while preventing simultaneous installs. |

> [!IMPORTANT]
> `uq_active_child` is a unique index on the generated `active_child_id`. It prevents a physical component from being simultaneously installed in multiple parent assemblies without blocking future reinstallation after removal.

---

### Section 4: Movement Logistics, Auditing & QR Telemetry

#### Table: `stock_movements`
Spatial transfer audit trail recording logistics between warehouses and field sites.

| Column | Data Type | Nullable | Key / Constraints | Default | Description |
| :--- | :--- | :---: | :--- | :--- | :--- |
| `id` | `BIGINT` | NO | `PK`, `AUTO_INCREMENT` | *None* | Unique movement log ID. |
| `item_id` | `BIGINT` | NO | `FK -> inventory_items(id)` | *None* | Item being moved (`ON DELETE CASCADE`). |
| `movement_type` | `ENUM(...)` | NO | `INDEX (idx_movements_type)` | *None* | Type: `'receive'`, `'transfer'`, `'deploy'`, `'return'`, `'repair_send'`, `'repair_return'`, `'retire'`. |
| `from_location_id` | `BIGINT` | YES | `FK -> locations(id)` | `NULL` | Dispatch source location (`ON DELETE SET NULL`). |
| `to_location_id` | `BIGINT` | YES | `FK -> locations(id)` | `NULL` | Destination location (`ON DELETE SET NULL`). |
| `reference_type` | `VARCHAR(50)` | YES | | `NULL` | Context entity type (e.g., `'deployment'`, `'repair'`). |
| `reference_id` | `BIGINT` | YES | | `NULL` | Context entity ID. |
| `notes` | `TEXT` | YES | | `NULL` | Bill of lading, truck number, or transfer notes. |
| `created_by` | `BIGINT` | YES | `FK -> users(id)` | `NULL` | User authorizing the movement (`ON DELETE SET NULL`). |
| `created_at` | `TIMESTAMP` | NO | `INDEX (idx_movements_created)` | `CURRENT_TIMESTAMP` | Movement timestamp. |

---

#### Table: `inventory_logs`
Historical change log recording status transitions and lifecycle actions.

| Column | Data Type | Nullable | Key / Constraints | Default | Description |
| :--- | :--- | :---: | :--- | :--- | :--- |
| `id` | `BIGINT` | NO | `PK`, `AUTO_INCREMENT` | *None* | Unique log entry ID. |
| `item_id` | `BIGINT` | NO | `FK -> inventory_items(id)` | *None* | Target inventory item (`ON DELETE CASCADE`). |
| `action` | `ENUM(...)` | NO | `INDEX (idx_logs_action)` | *None* | Action: `'create'`, `'status_change'`, `'repair'`, `'transfer'`, `'deploy'`, `'return'`, `'retire'`. |
| `from_status` | `VARCHAR(50)` | YES | | `NULL` | Status before action. |
| `to_status` | `VARCHAR(50)` | YES | | `NULL` | Status after action. |
| `notes` | `TEXT` | YES | | `NULL` | Operator remarks explaining status modification. |
| `performed_by` | `BIGINT` | YES | `FK -> users(id)` | `NULL` | User performing the action (`ON DELETE SET NULL`). |
| `action_at` | `TIMESTAMP` | NO | `INDEX (idx_logs_action_at)` | `CURRENT_TIMESTAMP` | Action event timestamp. |

---

#### Table: `qr_scan_logs`
High-frequency telemetry logging every physical scan interaction with hardware.

| Column | Data Type | Nullable | Key / Constraints | Default | Description |
| :--- | :--- | :---: | :--- | :--- | :--- |
| `id` | `BIGINT` | NO | `PK`, `AUTO_INCREMENT` | *None* | Unique scan record ID. |
| `item_id` | `BIGINT` | NO | `FK -> inventory_items(id)` | *None* | Item scanned (`ON DELETE CASCADE`). |
| `scanned_by` | `BIGINT` | YES | `FK -> users(id)` | `NULL` | Mobile scanner user (`ON DELETE SET NULL`). |
| `location_id` | `BIGINT` | YES | `FK -> locations(id)` | `NULL` | Geo facility where scan occurred (`ON DELETE SET NULL`). |
| `action` | `VARCHAR(50)` | YES | | `NULL` | Scan trigger context (e.g. `'manifest_verify'`, `'pre_flight_check'`). |
| `scanned_at` | `TIMESTAMP` | NO | `INDEX (idx_scans_scanned_at)` | `CURRENT_TIMESTAMP` | Exact scan timestamp. |

---

### Section 5: Event Deployments & Screen Coordinates

#### Table: `deployments`
Production event projects, concert tours, and temporary rental deployments.

| Column | Data Type | Nullable | Key / Constraints | Default | Description |
| :--- | :--- | :---: | :--- | :--- | :--- |
| `id` | `BIGINT` | NO | `PK`, `AUTO_INCREMENT` | *None* | Unique deployment identifier. |
| `name` | `VARCHAR(100)` | NO | | *None* | Event/show title (e.g., `'Summer Music Festival 2026'`). |
| `location_id` | `BIGINT` | YES | `FK -> locations(id)` | `NULL` | Event venue or site (`ON DELETE SET NULL`). |
| `start_date` | `DATE` | YES | | `NULL` | Planned load-in start date. |
| `end_date` | `DATE` | YES | | `NULL` | Planned strike / load-out date. |
| `status` | `ENUM(...)` | NO | `INDEX (idx_deployments_status)` | `'planned'` | Status: `'planned'`, `'active'`, `'completed'`, `'cancelled'`. |
| `created_by` | `BIGINT` | YES | `FK -> users(id)` | `NULL` | Project manager who created deployment (`ON DELETE SET NULL`). |
| `approved_by` | `BIGINT` | YES | `FK -> users(id)` | `NULL` | Senior engineer who approved rig safety (`ON DELETE SET NULL`). |
| `created_at` | `TIMESTAMP` | NO | | `CURRENT_TIMESTAMP` | Project record creation timestamp. |

---

#### Table: `deployment_items`
Individual items assigned to a deployment with video wall grid coordinate mapping.

| Column | Data Type | Nullable | Key / Constraints | Default | Description |
| :--- | :--- | :---: | :--- | :--- | :--- |
| `id` | `BIGINT` | NO | `PK`, `AUTO_INCREMENT` | *None* | Unique mapping ID. |
| `deployment_id` | `BIGINT` | NO | `FK -> deployments(id)` | *None* | Parent deployment project (`ON DELETE CASCADE`). |
| `item_id` | `BIGINT` | NO | `FK -> inventory_items(id)` | *None* | Hardware item deployed (`ON DELETE CASCADE`). |
| `screen_name` | `VARCHAR(100)` | YES | | `NULL` | Screen wall identifier (e.g., `'Main Center Wall'`, `'Stage Left I-Mag'`). |
| `pos_x` | `SMALLINT` | YES | | `NULL` | Horizontal column coordinate on display raster. |
| `pos_y` | `SMALLINT` | YES | | `NULL` | Vertical row coordinate on display raster. |
| `assigned_at` | `TIMESTAMP` | NO | | `CURRENT_TIMESTAMP` | Time item was committed to rig. |
| `returned_at` | `TIMESTAMP` | YES | | `NULL` | Time item was decommissioned back to warehouse. |

---

### Section 6: Repair Management & Remediation Actions

#### Table: `repairs`
Incident repair tickets tracking defective hardware through bench diagnostics.

| Column | Data Type | Nullable | Key / Constraints | Default | Description |
| :--- | :--- | :---: | :--- | :--- | :--- |
| `id` | `BIGINT` | NO | `PK`, `AUTO_INCREMENT` | *None* | Unique ticket identifier. |
| `ticket` | `VARCHAR(150)` | NO | | `'Repair request'` | Ticket number generated by the application for newly submitted tickets. |
| `reason` | `TEXT` | YES | | `NULL` | Reason the equipment needs repair. |
| `item_id` | `BIGINT` | NO | `FK -> inventory_items(id)` | *None* | Faulty item under diagnosis (`ON DELETE CASCADE`). |
| `issue_description` | `TEXT` | YES | | `NULL` | Symptoms and visual defect diagnosis (e.g. dead pixels, PSU ripple). |
| `priority` | `ENUM(...)` | NO | `INDEX (idx_repairs_priority)` | `'medium'` | Priority: `'low'`, `'medium'`, `'high'`, `'critical'`. |
| `status` | `ENUM(...)` | NO | `INDEX (idx_repairs_status)` | `'pending'` | Review statuses: `'pending'`, `'accepted'`, `'declined'`; repair lifecycle: `'in_progress'`, `'resolved'`, `'cancelled'`; `'open'` is retained for existing tickets. |
| `technician_id` | `BIGINT` | YES | `FK -> users(id)` | `NULL` | Lead repair technician assigned (`ON DELETE SET NULL`). |
| `repair_location_id` | `BIGINT` | YES | `FK -> locations(id)` | `NULL` | Repair lab or workshop bench (`ON DELETE SET NULL`). |
| `cost` | `DECIMAL(10,2)` | YES | | `NULL` | Financial repair cost (parts + labor). |
| `downtime_hours` | `DECIMAL(10,2)` | YES | | `NULL` | Total elapsed hours out of active service. |
| `before_status` | `VARCHAR(50)` | YES | | `NULL` | Lifecycle status prior to opening ticket. |
| `after_status` | `VARCHAR(50)` | YES | | `NULL` | Post-repair status (e.g., `'available'`). |
| `created_by` | `BIGINT` | YES | `FK -> users(id)` | `NULL` | User who logged ticket (`ON DELETE SET NULL`). |
| `reviewed_by` | `BIGINT` | YES | `FK -> users(id)` | `NULL` | Staff/admin who accepted or declined the ticket. |
| `created_at` | `TIMESTAMP` | NO | | `CURRENT_TIMESTAMP` | Ticket opening timestamp. |
| `resolved_at` | `TIMESTAMP` | YES | | `NULL` | Ticket closure timestamp. |
| `reviewed_at` | `TIMESTAMP` | YES | | `NULL` | Time the ticket was accepted or declined. |

---

#### Table: `repair_actions`
Granular remediation steps, soldering work, and component replacements performed.

| Column | Data Type | Nullable | Key / Constraints | Default | Description |
| :--- | :--- | :---: | :--- | :--- | :--- |
| `id` | `BIGINT` | NO | `PK`, `AUTO_INCREMENT` | *None* | Unique action step ID. |
| `repair_id` | `BIGINT` | NO | `FK -> repairs(id)` | *None* | Parent repair ticket (`ON DELETE CASCADE`). |
| `action` | `VARCHAR(100)` | YES | | *None* | Action description (e.g., `'Replaced Shift Register IC'`, `'Re-soldered ribbon'`). |
| `component_id` | `BIGINT` | YES | `FK -> inventory_items(id)` | `NULL` | Replacement part cannibalized or pulled from inventory (`ON DELETE SET NULL`). |
| `notes` | `TEXT` | YES | | `NULL` | Scope notes or oscilloscope readings. |
| `performed_by` | `BIGINT` | YES | `FK -> users(id)` | `NULL` | Technician who executed action (`ON DELETE SET NULL`). |
| `created_at` | `TIMESTAMP` | NO | | `CURRENT_TIMESTAMP` | Work execution timestamp. |

---

### Section 7: Preventive Maintenance Scheduling

#### Table: `maintenance_schedules`
Preventive inspection calendars (e.g., thermal paste refresh, PSU capacitor testing).

| Column | Data Type | Nullable | Key / Constraints | Default | Description |
| :--- | :--- | :---: | :--- | :--- | :--- |
| `id` | `BIGINT` | NO | `PK`, `AUTO_INCREMENT` | *None* | Unique schedule ID. |
| `item_id` | `BIGINT` | NO | `FK -> inventory_items(id)` | *None* | Hardware asset scheduled (`ON DELETE CASCADE`). |
| `scheduled_at` | `DATE` | NO | `INDEX (idx_maint_scheduled)` | *None* | Target due date for maintenance. |
| `completed_at` | `DATE` | YES | | `NULL` | Date maintenance was performed. |
| `assigned_to` | `BIGINT` | YES | `FK -> users(id)` | `NULL` | Assigned service technician (`ON DELETE SET NULL`). |
| `notes` | `TEXT` | YES | | `NULL` | Standard maintenance checklist procedures. |
| `status` | `ENUM(...)` | NO | | `'scheduled'` | Status: `'scheduled'`, `'completed'`, `'skipped'`. |

---

## 3. Lifecycle State Machine & Transition Rules

The status of every item in `inventory_items` is strictly governed by the `status_transitions` table. Application controllers must validate against this matrix before executing any status updates.

```
       [ Intake / Purchase ]
                 |
                 v
          +------------+
   +----->| available  |------+
   |      +------------+      |
   |        |        |        |
   |        v        v        |
   |  +----------+ +--------+ |
   |  |installed | |deployed| |
   |  +----------+ +--------+ |
   |        |        |        |
   |        +----+---+        |
   |             |            |
   |             v            |
   |      +------------+      |
   |      | defective  |      |
   |      +------------+      |
   |             |            |
   |             v            v
   |      +--------------+  +---------+
   +------| under_repair |->| retired |
          +--------------+  +---------+
```

### Valid Transition Matrix

| From Status | Permitted Destination Status | Business Trigger / Action |
| :--- | :--- | :--- |
| `available` | `installed` | Component is assembled into a parent cabinet via `asset_hierarchy`. |
| `available` | `deployed` | Asset is dispatched to a live stage event via `deployments`. |
| `available` | `under_repair` | Discovered damaged or dead-on-arrival during pre-flight warehouse QA. |
| `available` | `retired` | Decommissioned due to obsolescence or end-of-life. |
| `available` | `sold` | Unit allocated when an administrator accepts a customer order request. |
| `installed` | `available` | Component is disassembled/unseated from cabinet back to stock. |
| `installed` | `defective` | Component developed pixel or power fault while assembled. |
| `installed` | `under_repair` | Cabinet chassis sent to bench for corrective maintenance. |
| `deployed` | `available` | De-rigged from show and returned safely to warehouse inventory. |
| `deployed` | `defective` | Faulted live during rehearsal/show; tagged for repair. |
| `deployed` | `under_repair` | Dispatched directly from show site to repair facility. |
| `defective` | `under_repair` | Bench technician accepts ticket and begins diagnostics. |
| `defective` | `retired` | Declared Beyond Economical Repair (BER); parts scrapped. |
| `under_repair` | `available` | Passed QC and burn-in testing; returned to inventory. |
| `under_repair` | `defective` | Repair attempt failed or secondary fault discovered. |
| `under_repair` | `retired` | Irreparable board delamination or IC unavailability. |

---

## 4. Sample JSON Payloads

### 1. `item_models.specifications`
Stores model-level nominal engineering specifications:

```json
{
  "chassis_material": "Die-cast Aluminum Magnesium Alloy",
  "ingress_protection": "IP65 Front / IP54 Rear",
  "max_power_consumption_w": 650,
  "average_power_consumption_w": 220,
  "color_temperature_kelvin": 6500,
  "brightness_nits": 4500,
  "viewing_angle_horizontal_deg": 140,
  "viewing_angle_vertical_deg": 120,
  "supported_receiving_cards": ["MRV328", "MRV336", "A8s"],
  "rigging_limit_cabinets": 16
}
```

### 2. `inventory_items.metadata`
Stores instance-level dynamic attributes configured via `custom_fields`:

```json
{
  "led_bin_code": "BIN-A2-W58-B60",
  "calibration_profile": "Standard_V2_Rec709.cal",
  "driver_ic_batch": "MBI5153-2025Q4",
  "flight_case_id": "CASE-LED-042",
  "connector_type": "Seetronic PowerCON TRUE1",
  "rigging_bar_included": true
}
```

---

## 5. Physical Assembly & BOM Validation Rules

When a cabinet is assembled from components, application business logic validates against `cabinet_templates`:

$$\text{Assembly Complete} \iff \begin{cases}
N_{\text{module}} = \text{required\_module\_count} \\
N_{\text{psu}} = \text{required\_psu\_count} \\
N_{\text{hub}} = \text{required\_hub\_count} \\
N_{\text{card}} = \text{required\_card\_count}
\end{cases}$$

1. **Model Compatibility:** `model_compatibility` is queried to ensure the child model is certified for the parent cabinet chassis.
2. **Active Exclusivity:** The child component must currently have status `'available'` and have no active record in `asset_hierarchy` where `status = 'installed'`.
3. **State Transition:** Upon successful link creation in `asset_hierarchy`, the child component's status is atomically shifted from `'available'` to `'installed'`.

---

## 6. Indexing & Query Optimization Strategy

| Index Name | Table | Columns | Query Optimization Rationale |
| :--- | :--- | :--- | :--- |
| `idx_items_qr` | `inventory_items` | `(qr_code)` | Ultra-low-latency ($O(1)$) lookups during handheld mobile scanning in warehouses and event stages. |
| `idx_items_status` | `inventory_items` | `(status)` | Real-time warehouse availability filtering and dashboard health metrics. |
| `idx_items_batch` | `inventory_items` | `(batch_id)` | LED lot grouping to ensure all cabinets selected for a deployment share identical diode binning. |
| `idx_movements_item`| `stock_movements` | `(item_id, created_at)` | Fast pagination of chronological chain-of-custody movement logs for a given hardware asset. |
| `idx_depitems_coord`| `deployment_items`| `(deployment_id, screen_name, pos_x, pos_y)` | Immediate retrieval of visual screen grids and defect localization on video walls. |
| `idx_warranties_exp`| `warranties` | `(warranty_expiry)` | Scheduled background cron alerts for upcoming warranty expirations within 30/60/90 days. |

---

## 7. Customer Order Requests

- `customer_order_requests` stores each customer's pending, accepted, or declined purchase request, with the reviewing administrator and quote/update notes.
- `customer_order_items` stores the requested model and quantity for each order.
- `customer_order_allocations` maps accepted order lines to specific inventory units and prevents one unit from being allocated to multiple orders.
- The shop catalog includes only active inventory units whose status is `available`. Submitting a request does not reserve stock or collect payment.
- Accepting a request locks and allocates every requested unit in one transaction, changes its status to `sold`, clears its warehouse location, and records the sale in `stock_movements` and `inventory_logs`. If stock is insufficient, the whole request remains pending without partial allocation.

