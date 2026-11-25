-- =============================================================================
-- LED INVENTORY MANAGEMENT SYSTEM -- PRODUCTION DATABASE SCHEMA
-- Engine: MySQL 8.0+ / MariaDB 10.5+
-- Charset: utf8mb4 / utf8mb4_unicode_ci
-- =============================================================================

CREATE DATABASE IF NOT EXISTS led_inventory
    DEFAULT CHARACTER SET utf8mb4
    DEFAULT COLLATE utf8mb4_unicode_ci;

USE led_inventory;

-- =============================================================================
-- SECTION 1: FOUNDATION & LOOKUP TABLES
-- =============================================================================

-- Users and Role-Based Access Control
CREATE TABLE IF NOT EXISTS users (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    full_name VARCHAR(100) NOT NULL,
    email VARCHAR(150) UNIQUE,
    role ENUM('admin','manager','warehouse','technician','staff','customer') NOT NULL DEFAULT 'customer',
    auth_token VARCHAR(255) NULL,
    is_active BOOLEAN NOT NULL DEFAULT TRUE,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP NULL COMMENT 'Soft delete timestamp',
    INDEX idx_users_role (role),
    INDEX idx_users_active (is_active)
) ENGINE=InnoDB;

-- Dynamic Custom Fields per Item Type
CREATE TABLE IF NOT EXISTS custom_fields (
    id INT AUTO_INCREMENT PRIMARY KEY,
    item_type VARCHAR(50) NOT NULL COMMENT 'cabinet, module, power_supply, receiving_card, hub, video_processor',
    field_name VARCHAR(100) NOT NULL,
    field_type ENUM('text','number','boolean','date') NOT NULL DEFAULT 'text',
    is_required BOOLEAN NOT NULL DEFAULT FALSE,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_custom_type_field (item_type, field_name)
) ENGINE=InnoDB;

-- Physical & Virtual Locations (Hierarchical)
CREATE TABLE IF NOT EXISTS locations (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    type ENUM('warehouse','event_site','repair_center','truck','office') NOT NULL,
    address TEXT NULL,
    parent_location_id BIGINT NULL,
    is_active BOOLEAN NOT NULL DEFAULT TRUE,
    FOREIGN KEY (parent_location_id) REFERENCES locations(id) ON DELETE SET NULL,
    INDEX idx_locations_type (type),
    INDEX idx_locations_parent (parent_location_id)
) ENGINE=InnoDB;

-- Equipment Manufacturers
CREATE TABLE IF NOT EXISTS manufacturers (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) UNIQUE NOT NULL,
    country VARCHAR(100) NULL,
    support_contact VARCHAR(150) NULL,
    website VARCHAR(200) NULL
) ENGINE=InnoDB;

-- Hardware Model Catalog
CREATE TABLE IF NOT EXISTS item_models (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    item_type ENUM('cabinet','module','power_supply','receiving_card','hub','video_processor') NOT NULL,
    manufacturer_id BIGINT NOT NULL,
    model_name VARCHAR(100) NOT NULL,
    specifications JSON NULL COMMENT 'Dynamic model properties and nominal specs',
    FOREIGN KEY (manufacturer_id) REFERENCES manufacturers(id) ON DELETE RESTRICT,
    UNIQUE KEY uq_item_model (item_type, manufacturer_id, model_name),
    INDEX idx_models_type (item_type)
) ENGINE=InnoDB;

-- Model Cross-Compatibility Rules
CREATE TABLE IF NOT EXISTS model_compatibility (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    parent_model_id BIGINT NOT NULL,
    child_model_id BIGINT NOT NULL,
    compatibility_type VARCHAR(50) NOT NULL COMMENT 'e.g. native, requires_firmware, mechanical_only',
    notes TEXT NULL,
    FOREIGN KEY (parent_model_id) REFERENCES item_models(id) ON DELETE CASCADE,
    FOREIGN KEY (child_model_id) REFERENCES item_models(id) ON DELETE CASCADE,
    INDEX idx_compat_parent (parent_model_id),
    INDEX idx_compat_child (child_model_id)
) ENGINE=InnoDB;

-- Cabinet Bill of Materials (BOM) Assembly Templates
CREATE TABLE IF NOT EXISTS cabinet_templates (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    model_id BIGINT NULL,
    required_module_count TINYINT UNSIGNED NOT NULL DEFAULT 0,
    required_psu_count TINYINT UNSIGNED NOT NULL DEFAULT 0,
    required_hub_count TINYINT UNSIGNED NOT NULL DEFAULT 0,
    required_card_count TINYINT UNSIGNED NOT NULL DEFAULT 0,
    notes TEXT NULL,
    FOREIGN KEY (model_id) REFERENCES item_models(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- Finite State Machine Lifecycle Transition Rules
CREATE TABLE IF NOT EXISTS status_transitions (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    from_status VARCHAR(50) NOT NULL,
    to_status VARCHAR(50) NOT NULL,
    UNIQUE KEY uq_status_transition (from_status, to_status)
) ENGINE=InnoDB;

-- Equipment Suppliers / Vendors
CREATE TABLE IF NOT EXISTS suppliers (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    contact_name VARCHAR(100) NULL,
    contact_email VARCHAR(150) NULL,
    contact_phone VARCHAR(50) NULL,
    address TEXT NULL
) ENGINE=InnoDB;

-- Procurement Purchase Orders
CREATE TABLE IF NOT EXISTS purchase_orders (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    supplier_id BIGINT NOT NULL,
    po_number VARCHAR(100) UNIQUE NOT NULL,
    status ENUM('draft','submitted','received','cancelled') NOT NULL DEFAULT 'draft',
    ordered_by BIGINT NULL,
    ordered_at TIMESTAMP NULL,
    received_at TIMESTAMP NULL,
    notes TEXT NULL,
    FOREIGN KEY (supplier_id) REFERENCES suppliers(id) ON DELETE RESTRICT,
    FOREIGN KEY (ordered_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_po_status (status)
) ENGINE=InnoDB;

-- =============================================================================
-- SECTION 2: UNIFIED ASSET CORE & SPEC SUBTABLES
-- =============================================================================

-- Central Hardware Asset Inventory
CREATE TABLE IF NOT EXISTS inventory_items (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    item_type ENUM('cabinet','module','power_supply','receiving_card','hub','video_processor') NOT NULL,
    qr_code VARCHAR(100) UNIQUE NOT NULL,
    serial_number VARCHAR(100) NULL,
    model_id BIGINT NULL,
    batch_id VARCHAR(100) NULL COMMENT 'LED lot batch identifier for binning/color uniformity',
    status ENUM('available','installed','deployed','defective','under_repair','retired','sold') NOT NULL DEFAULT 'available',
    current_location_id BIGINT NULL,
    purchase_order_id BIGINT NULL,
    metadata JSON NULL COMMENT 'Dynamic custom field key-values',
    is_active BOOLEAN NOT NULL DEFAULT TRUE,
    created_by BIGINT NULL,
    updated_by BIGINT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP NULL COMMENT 'Soft delete timestamp',
    FOREIGN KEY (model_id) REFERENCES item_models(id) ON DELETE SET NULL,
    FOREIGN KEY (current_location_id) REFERENCES locations(id) ON DELETE SET NULL,
    FOREIGN KEY (purchase_order_id) REFERENCES purchase_orders(id) ON DELETE SET NULL,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_items_status (status),
    INDEX idx_items_type (item_type),
    INDEX idx_items_qr (qr_code),
    INDEX idx_items_serial (serial_number),
    INDEX idx_items_batch (batch_id),
    INDEX idx_items_location (current_location_id),
    INDEX idx_items_created_at (created_at)
) ENGINE=InnoDB;

-- 1:1 Hardware Specs: LED Display Modules
CREATE TABLE IF NOT EXISTS spec_modules (
    item_id BIGINT PRIMARY KEY,
    pixel_pitch DECIMAL(5,2) NOT NULL CHECK (pixel_pitch > 0),
    resolution_width SMALLINT UNSIGNED NULL,
    resolution_height SMALLINT UNSIGNED NULL,
    width_mm SMALLINT UNSIGNED NULL,
    height_mm SMALLINT UNSIGNED NULL,
    scan_rate VARCHAR(20) NULL,
    refresh_rate SMALLINT UNSIGNED NULL,
    FOREIGN KEY (item_id) REFERENCES inventory_items(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- 1:1 Hardware Specs: Power Supplies
CREATE TABLE IF NOT EXISTS spec_power_supplies (
    item_id BIGINT PRIMARY KEY,
    voltage_in VARCHAR(50) NULL,
    voltage_out VARCHAR(50) NULL,
    current_a DECIMAL(6,2) NULL CHECK (current_a > 0),
    wattage SMALLINT UNSIGNED NULL,
    FOREIGN KEY (item_id) REFERENCES inventory_items(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- 1:1 Hardware Specs: Cabinet Frames
CREATE TABLE IF NOT EXISTS spec_cabinets (
    item_id BIGINT PRIMARY KEY,
    size VARCHAR(50) NULL,
    template_id BIGINT NULL,
    width_mm SMALLINT UNSIGNED NULL,
    height_mm SMALLINT UNSIGNED NULL,
    depth_mm SMALLINT UNSIGNED NULL,
    weight_kg DECIMAL(6,2) NULL,
    FOREIGN KEY (item_id) REFERENCES inventory_items(id) ON DELETE CASCADE,
    FOREIGN KEY (template_id) REFERENCES cabinet_templates(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- 1:1 Hardware Specs: Video Processors & Controllers
CREATE TABLE IF NOT EXISTS spec_video_processors (
    item_id BIGINT PRIMARY KEY,
    firmware_version VARCHAR(50) NULL,
    input_ports TINYINT UNSIGNED NULL,
    output_ports TINYINT UNSIGNED NULL,
    last_serviced_at DATE NULL,
    FOREIGN KEY (item_id) REFERENCES inventory_items(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Equipment Warranty Records
CREATE TABLE IF NOT EXISTS warranties (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    item_id BIGINT NOT NULL,
    supplier_id BIGINT NULL,
    purchase_date DATE NULL,
    warranty_expiry DATE NOT NULL,
    notes TEXT NULL,
    FOREIGN KEY (item_id) REFERENCES inventory_items(id) ON DELETE CASCADE,
    FOREIGN KEY (supplier_id) REFERENCES suppliers(id) ON DELETE SET NULL,
    INDEX idx_warranties_expiry (warranty_expiry),
    INDEX idx_warranties_item (item_id)
) ENGINE=InnoDB;

-- =============================================================================
-- SECTION 3: PHYSICAL HIERARCHY & RECURSIVE ASSEMBLY
-- =============================================================================

CREATE TABLE IF NOT EXISTS asset_hierarchy (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    parent_id BIGINT NOT NULL COMMENT 'Cabinet or master screen assembly',
    child_id BIGINT NOT NULL COMMENT 'Child component (module, PSU, hub, receiving card)',
    slot_info VARCHAR(50) NULL COMMENT 'Mounting bay / position index',
    installed_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    removed_at TIMESTAMP NULL,
    status ENUM('installed','removed') NOT NULL DEFAULT 'installed',
    active_child_id BIGINT GENERATED ALWAYS AS (CASE WHEN status = 'installed' THEN child_id ELSE NULL END) STORED,
    FOREIGN KEY (parent_id) REFERENCES inventory_items(id) ON DELETE CASCADE,
    FOREIGN KEY (child_id) REFERENCES inventory_items(id) ON DELETE CASCADE,
    INDEX idx_hierarchy_parent (parent_id),
    INDEX idx_hierarchy_child (child_id),
    UNIQUE KEY uq_active_child (active_child_id)
) ENGINE=InnoDB;

-- =============================================================================
-- SECTION 4: STOCK MOVEMENTS, AUDITING & SCANNING
-- =============================================================================

CREATE TABLE IF NOT EXISTS stock_movements (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    item_id BIGINT NOT NULL,
    movement_type ENUM('receive','transfer','deploy','return','repair_send','repair_return','retire','sale') NOT NULL,
    from_location_id BIGINT NULL,
    to_location_id BIGINT NULL,
    reference_type VARCHAR(50) NULL COMMENT 'e.g. deployment, repair, purchase_order',
    reference_id BIGINT NULL,
    notes TEXT NULL,
    created_by BIGINT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (item_id) REFERENCES inventory_items(id) ON DELETE CASCADE,
    FOREIGN KEY (from_location_id) REFERENCES locations(id) ON DELETE SET NULL,
    FOREIGN KEY (to_location_id) REFERENCES locations(id) ON DELETE SET NULL,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_movements_item (item_id),
    INDEX idx_movements_type (movement_type),
    INDEX idx_movements_created (created_at)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS inventory_logs (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    item_id BIGINT NOT NULL,
    action ENUM('create','status_change','repair','transfer','deploy','return','retire','sale') NOT NULL,
    from_status VARCHAR(50) NULL,
    to_status VARCHAR(50) NULL,
    notes TEXT NULL,
    performed_by BIGINT NULL,
    action_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (item_id) REFERENCES inventory_items(id) ON DELETE CASCADE,
    FOREIGN KEY (performed_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_logs_item (item_id),
    INDEX idx_logs_action (action),
    INDEX idx_logs_performed (performed_by),
    INDEX idx_logs_action_at (action_at)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS qr_scan_logs (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    item_id BIGINT NOT NULL,
    scanned_by BIGINT NULL,
    location_id BIGINT NULL,
    action VARCHAR(50) NULL COMMENT 'Scan context or intent (e.g. audit, verify, pack)',
    scanned_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (item_id) REFERENCES inventory_items(id) ON DELETE CASCADE,
    FOREIGN KEY (scanned_by) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (location_id) REFERENCES locations(id) ON DELETE SET NULL,
    INDEX idx_scans_item (item_id),
    INDEX idx_scans_scanned_at (scanned_at)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS customer_order_requests (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    request_number VARCHAR(32) NULL UNIQUE,
    customer_id BIGINT NOT NULL,
    status ENUM('pending','accepted','declined') NOT NULL DEFAULT 'pending',
    admin_notes TEXT NULL,
    reviewed_by BIGINT NULL,
    reviewed_at TIMESTAMP NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (customer_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (reviewed_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_customer_orders_customer (customer_id),
    INDEX idx_customer_orders_status (status),
    INDEX idx_customer_orders_created (created_at)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS customer_order_items (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    order_id BIGINT NOT NULL,
    model_id BIGINT NOT NULL,
    quantity INT UNSIGNED NOT NULL,
    FOREIGN KEY (order_id) REFERENCES customer_order_requests(id) ON DELETE CASCADE,
    FOREIGN KEY (model_id) REFERENCES item_models(id) ON DELETE RESTRICT,
    UNIQUE KEY uq_customer_order_model (order_id, model_id),
    INDEX idx_customer_order_items_model (model_id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS customer_order_allocations (
    order_item_id BIGINT NOT NULL,
    inventory_item_id BIGINT NOT NULL,
    PRIMARY KEY (order_item_id, inventory_item_id),
    UNIQUE KEY uq_customer_order_inventory_item (inventory_item_id),
    FOREIGN KEY (order_item_id) REFERENCES customer_order_items(id) ON DELETE CASCADE,
    FOREIGN KEY (inventory_item_id) REFERENCES inventory_items(id) ON DELETE RESTRICT
) ENGINE=InnoDB;

-- =============================================================================
-- SECTION 5: DEPLOYMENTS & SCREEN COORDINATE POSITIONING
-- =============================================================================

CREATE TABLE IF NOT EXISTS deployments (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    location_id BIGINT NULL,
    start_date DATE NULL,
    end_date DATE NULL,
    status ENUM('planned','active','completed','cancelled') NOT NULL DEFAULT 'planned',
    created_by BIGINT NULL,
    approved_by BIGINT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (location_id) REFERENCES locations(id) ON DELETE SET NULL,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (approved_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_deployments_status (status)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS deployment_items (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    deployment_id BIGINT NOT NULL,
    item_id BIGINT NOT NULL,
    screen_name VARCHAR(100) NULL,
    pos_x SMALLINT NULL COMMENT 'Horizontal column coordinate on display canvas',
    pos_y SMALLINT NULL COMMENT 'Vertical row coordinate on display canvas',
    assigned_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    returned_at TIMESTAMP NULL,
    FOREIGN KEY (deployment_id) REFERENCES deployments(id) ON DELETE CASCADE,
    FOREIGN KEY (item_id) REFERENCES inventory_items(id) ON DELETE CASCADE,
    INDEX idx_depitems_deployment (deployment_id),
    INDEX idx_depitems_item (item_id)
) ENGINE=InnoDB;

-- =============================================================================
-- SECTION 6: REPAIR MANAGEMENT & REMEDIATION ACTIONS
-- =============================================================================

CREATE TABLE IF NOT EXISTS repairs (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    ticket VARCHAR(150) NOT NULL DEFAULT 'Repair request',
    reason TEXT NULL,
    item_id BIGINT NOT NULL,
    issue_description TEXT NULL,
    priority ENUM('low','medium','high','critical') NOT NULL DEFAULT 'medium',
    status ENUM('pending','accepted','declined','open','in_progress','resolved','cancelled') NOT NULL DEFAULT 'pending',
    technician_id BIGINT NULL,
    repair_location_id BIGINT NULL,
    cost DECIMAL(10,2) NULL,
    downtime_hours DECIMAL(10,2) NULL,
    before_status VARCHAR(50) NULL,
    after_status VARCHAR(50) NULL,
    created_by BIGINT NULL,
    reviewed_by BIGINT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    resolved_at TIMESTAMP NULL,
    reviewed_at TIMESTAMP NULL,
    FOREIGN KEY (item_id) REFERENCES inventory_items(id) ON DELETE CASCADE,
    FOREIGN KEY (technician_id) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (repair_location_id) REFERENCES locations(id) ON DELETE SET NULL,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (reviewed_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_repairs_item (item_id),
    INDEX idx_repairs_status (status),
    INDEX idx_repairs_priority (priority)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS repair_actions (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    repair_id BIGINT NOT NULL,
    action VARCHAR(100) NULL,
    component_id BIGINT NULL COMMENT 'Replacement component from inventory if applicable',
    notes TEXT NULL,
    performed_by BIGINT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (repair_id) REFERENCES repairs(id) ON DELETE CASCADE,
    FOREIGN KEY (component_id) REFERENCES inventory_items(id) ON DELETE SET NULL,
    FOREIGN KEY (performed_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_actions_repair (repair_id)
) ENGINE=InnoDB;

-- =============================================================================
-- SECTION 7: PREVENTIVE MAINTENANCE SCHEDULING
-- =============================================================================

CREATE TABLE IF NOT EXISTS maintenance_schedules (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    item_id BIGINT NOT NULL,
    scheduled_at DATE NOT NULL,
    completed_at DATE NULL,
    assigned_to BIGINT NULL,
    notes TEXT NULL,
    status ENUM('scheduled','completed','skipped') NOT NULL DEFAULT 'scheduled',
    FOREIGN KEY (item_id) REFERENCES inventory_items(id) ON DELETE CASCADE,
    FOREIGN KEY (assigned_to) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_maint_scheduled (scheduled_at),
    INDEX idx_maint_item (item_id)
) ENGINE=InnoDB;

-- =============================================================================
-- SECTION 8: SEED DATA & LIFECYCLE STATE RULES
-- =============================================================================

-- Seed Default Admin User (Default password is 'admin123')
INSERT INTO users (username, password_hash, full_name, email, role) 
VALUES ('admin', '$2y$12$zmVDLm98Qo0wObvqeQkOGe9XWLpfdhPyzU3YlMouTll3zvGW/SgVe', 'System Admin', 'admin@example.com', 'admin')
ON DUPLICATE KEY UPDATE id=id;

-- Seed Finite State Machine Valid Transitions
INSERT INTO status_transitions (from_status, to_status) VALUES
('available', 'installed'),
('available', 'deployed'),
('available', 'under_repair'),
('available', 'retired'),
('available', 'sold'),
('installed', 'available'),
('installed', 'defective'),
('installed', 'under_repair'),
('deployed', 'available'),
('deployed', 'defective'),
('deployed', 'under_repair'),
('defective', 'under_repair'),
('defective', 'retired'),
('under_repair', 'available'),
('under_repair', 'defective'),
('under_repair', 'retired')
ON DUPLICATE KEY UPDATE from_status=from_status;

