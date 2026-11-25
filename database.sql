-- =============================================================================
-- LED INVENTORY MANAGEMENT SYSTEM -- UNIFIED SCHEMA
-- Synthesized from Gemini, ChatGPT, and Copilot recommendations
-- =============================================================================

CREATE DATABASE IF NOT EXISTS led_inventory;
USE led_inventory;

-- Drop old schema tables
DROP TABLE IF EXISTS repair_actions;
DROP TABLE IF EXISTS repairs;
DROP TABLE IF EXISTS deployment_items;
DROP TABLE IF EXISTS deployments;
DROP TABLE IF EXISTS inventory_logs;
DROP TABLE IF EXISTS cabinet_components;
DROP TABLE IF EXISTS cabinets;
DROP TABLE IF EXISTS modules;
DROP TABLE IF EXISTS power_supplies;
DROP TABLE IF EXISTS receiving_cards;
DROP TABLE IF EXISTS hubs;
DROP TABLE IF EXISTS video_processors;
DROP TABLE IF EXISTS users;

-- Drop new schema tables (if they exist, in reverse dependency order)
DROP TABLE IF EXISTS maintenance_schedules;
DROP TABLE IF EXISTS qr_scan_logs;
DROP TABLE IF EXISTS stock_movements;
DROP TABLE IF EXISTS asset_hierarchy;
DROP TABLE IF EXISTS warranties;
DROP TABLE IF EXISTS spec_video_processors;
DROP TABLE IF EXISTS spec_cabinets;
DROP TABLE IF EXISTS spec_power_supplies;
DROP TABLE IF EXISTS spec_modules;
DROP TABLE IF EXISTS inventory_items;
DROP TABLE IF EXISTS purchase_orders;
DROP TABLE IF EXISTS suppliers;
DROP TABLE IF EXISTS status_transitions;
DROP TABLE IF EXISTS cabinet_templates;
DROP TABLE IF EXISTS model_compatibility;
DROP TABLE IF EXISTS item_models;
DROP TABLE IF EXISTS manufacturers;
DROP TABLE IF EXISTS locations;

-- =============================================================================
-- SECTION 1: FOUNDATION TABLES
-- =============================================================================

CREATE TABLE users (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    full_name VARCHAR(100) NOT NULL,
    email VARCHAR(150) UNIQUE,
    role ENUM('admin','manager','warehouse','technician','staff','customer') NOT NULL DEFAULT 'customer',
    auth_token VARCHAR(255) NULL,
    is_active BOOLEAN NOT NULL DEFAULT TRUE,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP NULL, -- soft delete
    INDEX idx_role (role)
);

-- Insert a default admin account (password is 'admin123')
INSERT INTO users (username, password_hash, full_name, email, role) 
VALUES ('admin', '$2y$12$zmVDLm98Qo0wObvqeQkOGe9XWLpfdhPyzU3YlMouTll3zvGW/SgVe', 'System Admin', 'admin@example.com', 'admin')
ON DUPLICATE KEY UPDATE id=id;

-- Custom Fields (for dynamic per-type attributes shown in the Add Stock form)
CREATE TABLE custom_fields (
    id INT AUTO_INCREMENT PRIMARY KEY,
    item_type VARCHAR(50) NOT NULL,
    field_name VARCHAR(100) NOT NULL,
    field_type ENUM('text','number','boolean','date') DEFAULT 'text',
    is_required BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY idx_type_field (item_type, field_name)
);

CREATE TABLE locations (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    type ENUM('warehouse','event_site','repair_center','truck','office') NOT NULL,
    address TEXT,
    parent_location_id BIGINT NULL,
    is_active BOOLEAN NOT NULL DEFAULT TRUE,
    FOREIGN KEY (parent_location_id) REFERENCES locations(id)
);

CREATE TABLE manufacturers (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) UNIQUE NOT NULL,
    country VARCHAR(100),
    support_contact VARCHAR(150),
    website VARCHAR(200)
);

CREATE TABLE item_models (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    item_type ENUM('cabinet','module','power_supply','receiving_card','hub','video_processor') NOT NULL,
    manufacturer_id BIGINT NOT NULL,
    model_name VARCHAR(100) NOT NULL,
    specifications JSON,
    FOREIGN KEY (manufacturer_id) REFERENCES manufacturers(id),
    UNIQUE KEY uq_model (item_type, manufacturer_id, model_name)
);

CREATE TABLE model_compatibility (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    parent_model_id BIGINT NOT NULL,
    child_model_id BIGINT NOT NULL,
    compatibility_type VARCHAR(50) NOT NULL,
    notes TEXT,
    FOREIGN KEY (parent_model_id) REFERENCES item_models(id),
    FOREIGN KEY (child_model_id) REFERENCES item_models(id)
);

CREATE TABLE cabinet_templates (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    model_id BIGINT,
    required_module_count TINYINT UNSIGNED NOT NULL DEFAULT 0,
    required_psu_count TINYINT UNSIGNED NOT NULL DEFAULT 0,
    required_hub_count TINYINT UNSIGNED NOT NULL DEFAULT 0,
    required_card_count TINYINT UNSIGNED NOT NULL DEFAULT 0,
    notes TEXT,
    FOREIGN KEY (model_id) REFERENCES item_models(id)
);

CREATE TABLE status_transitions (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    from_status VARCHAR(50) NOT NULL,
    to_status VARCHAR(50) NOT NULL,
    UNIQUE KEY uq_transition (from_status, to_status)
);

-- Seed valid transitions
INSERT INTO status_transitions (from_status, to_status) VALUES
('available', 'installed'),
('available', 'deployed'),
('available', 'under_repair'),
('available', 'retired'),
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
('under_repair', 'retired');

CREATE TABLE suppliers (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    contact_name VARCHAR(100),
    contact_email VARCHAR(150),
    contact_phone VARCHAR(50),
    address TEXT
);

CREATE TABLE purchase_orders (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    supplier_id BIGINT NOT NULL,
    po_number VARCHAR(100) UNIQUE,
    status ENUM('draft','submitted','received','cancelled') NOT NULL DEFAULT 'draft',
    ordered_by BIGINT,
    ordered_at TIMESTAMP NULL,
    received_at TIMESTAMP NULL,
    notes TEXT,
    FOREIGN KEY (supplier_id) REFERENCES suppliers(id),
    FOREIGN KEY (ordered_by) REFERENCES users(id)
);

-- =============================================================================
-- SECTION 2: UNIFIED ASSET TABLE
-- =============================================================================

CREATE TABLE inventory_items (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    item_type ENUM('cabinet','module','power_supply','receiving_card','hub','video_processor') NOT NULL,
    qr_code VARCHAR(100) UNIQUE NOT NULL,
    serial_number VARCHAR(100),
    model_id BIGINT,
    batch_id VARCHAR(100),
    status ENUM('available','installed','deployed','defective','under_repair','retired') NOT NULL DEFAULT 'available',
    current_location_id BIGINT,
    purchase_order_id BIGINT NULL,
    metadata JSON,
    is_active BOOLEAN NOT NULL DEFAULT TRUE,
    created_by BIGINT, -- Changed to allow NULL since old data might not have users mapping initially during dev
    updated_by BIGINT,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP NULL,
    FOREIGN KEY (model_id) REFERENCES item_models(id),
    FOREIGN KEY (current_location_id) REFERENCES locations(id),
    FOREIGN KEY (purchase_order_id) REFERENCES purchase_orders(id),
    FOREIGN KEY (created_by) REFERENCES users(id),
    FOREIGN KEY (updated_by) REFERENCES users(id),
    INDEX idx_status (status),
    INDEX idx_item_type (item_type),
    INDEX idx_qr_code (qr_code),
    INDEX idx_serial (serial_number),
    INDEX idx_batch (batch_id),
    INDEX idx_location (current_location_id),
    INDEX idx_created_at (created_at)
);

CREATE TABLE spec_modules (
    item_id BIGINT PRIMARY KEY,
    pixel_pitch DECIMAL(5,2) NOT NULL CHECK (pixel_pitch > 0),
    resolution_width SMALLINT UNSIGNED,
    resolution_height SMALLINT UNSIGNED,
    width_mm SMALLINT UNSIGNED,
    height_mm SMALLINT UNSIGNED,
    scan_rate VARCHAR(20),
    refresh_rate SMALLINT UNSIGNED,
    FOREIGN KEY (item_id) REFERENCES inventory_items(id) ON DELETE CASCADE
);

CREATE TABLE spec_power_supplies (
    item_id BIGINT PRIMARY KEY,
    voltage_in VARCHAR(50),
    voltage_out VARCHAR(50),
    current_a DECIMAL(6,2) CHECK (current_a > 0),
    wattage SMALLINT UNSIGNED,
    FOREIGN KEY (item_id) REFERENCES inventory_items(id) ON DELETE CASCADE
);

CREATE TABLE spec_cabinets (
    item_id BIGINT PRIMARY KEY,
    size VARCHAR(50),
    template_id BIGINT,
    width_mm SMALLINT UNSIGNED,
    height_mm SMALLINT UNSIGNED,
    depth_mm SMALLINT UNSIGNED,
    weight_kg DECIMAL(6,2),
    FOREIGN KEY (item_id) REFERENCES inventory_items(id) ON DELETE CASCADE,
    FOREIGN KEY (template_id) REFERENCES cabinet_templates(id)
);

CREATE TABLE spec_video_processors (
    item_id BIGINT PRIMARY KEY,
    firmware_version VARCHAR(50),
    input_ports TINYINT UNSIGNED,
    output_ports TINYINT UNSIGNED,
    last_serviced_at DATE,
    FOREIGN KEY (item_id) REFERENCES inventory_items(id) ON DELETE CASCADE
);

CREATE TABLE warranties (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    item_id BIGINT NOT NULL,
    supplier_id BIGINT,
    purchase_date DATE,
    warranty_expiry DATE NOT NULL,
    notes TEXT,
    FOREIGN KEY (item_id) REFERENCES inventory_items(id),
    FOREIGN KEY (supplier_id) REFERENCES suppliers(id),
    INDEX idx_expiry (warranty_expiry)
);

-- =============================================================================
-- SECTION 3: PHYSICAL HIERARCHY
-- =============================================================================

CREATE TABLE asset_hierarchy (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    parent_id BIGINT NOT NULL,
    child_id BIGINT NOT NULL,
    slot_info VARCHAR(50),
    installed_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    removed_at TIMESTAMP NULL,
    status ENUM('installed','removed') NOT NULL DEFAULT 'installed',
    active_child_id BIGINT GENERATED ALWAYS AS (CASE WHEN status = 'installed' THEN child_id ELSE NULL END) STORED,
    UNIQUE KEY uq_active_child (active_child_id),
    FOREIGN KEY (parent_id) REFERENCES inventory_items(id),
    FOREIGN KEY (child_id) REFERENCES inventory_items(id),
    INDEX idx_parent (parent_id),
    INDEX idx_child (child_id)
);

-- =============================================================================
-- SECTION 4: STOCK MOVEMENTS & AUDIT TRAIL
-- =============================================================================

CREATE TABLE stock_movements (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    item_id BIGINT NOT NULL,
    movement_type ENUM('receive','transfer','deploy','return','repair_send','repair_return','retire') NOT NULL,
    from_location_id BIGINT NULL,
    to_location_id BIGINT NULL,
    reference_type VARCHAR(50),
    reference_id BIGINT,
    notes TEXT,
    created_by BIGINT,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (item_id) REFERENCES inventory_items(id),
    FOREIGN KEY (from_location_id) REFERENCES locations(id),
    FOREIGN KEY (to_location_id) REFERENCES locations(id),
    FOREIGN KEY (created_by) REFERENCES users(id),
    INDEX idx_item (item_id),
    INDEX idx_type (movement_type),
    INDEX idx_created (created_at)
);

CREATE TABLE inventory_logs (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    item_id BIGINT NOT NULL,
    action ENUM('create','status_change','repair','transfer','deploy','return','retire') NOT NULL,
    from_status VARCHAR(50),
    to_status VARCHAR(50),
    notes TEXT,
    performed_by BIGINT,
    action_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (item_id) REFERENCES inventory_items(id),
    FOREIGN KEY (performed_by) REFERENCES users(id),
    INDEX idx_item (item_id),
    INDEX idx_action (action),
    INDEX idx_performed (performed_by),
    INDEX idx_action_at (action_at)
);

CREATE TABLE qr_scan_logs (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    item_id BIGINT NOT NULL,
    scanned_by BIGINT,
    location_id BIGINT,
    action VARCHAR(50),
    scanned_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (item_id) REFERENCES inventory_items(id),
    FOREIGN KEY (scanned_by) REFERENCES users(id),
    FOREIGN KEY (location_id) REFERENCES locations(id),
    INDEX idx_item (item_id),
    INDEX idx_scanned (scanned_at)
);

-- =============================================================================
-- SECTION 5: DEPLOYMENTS
-- =============================================================================

CREATE TABLE deployments (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    location_id BIGINT,
    start_date DATE,
    end_date DATE,
    status ENUM('planned','active','completed','cancelled') NOT NULL DEFAULT 'planned',
    created_by BIGINT,
    approved_by BIGINT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (location_id) REFERENCES locations(id),
    FOREIGN KEY (created_by) REFERENCES users(id),
    FOREIGN KEY (approved_by) REFERENCES users(id)
);

CREATE TABLE deployment_items (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    deployment_id BIGINT NOT NULL,
    item_id BIGINT NOT NULL,
    screen_name VARCHAR(100),
    pos_x SMALLINT,
    pos_y SMALLINT,
    assigned_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    returned_at TIMESTAMP NULL,
    FOREIGN KEY (deployment_id) REFERENCES deployments(id) ON DELETE CASCADE,
    FOREIGN KEY (item_id) REFERENCES inventory_items(id),
    INDEX idx_deployment (deployment_id),
    INDEX idx_item (item_id)
);

-- =============================================================================
-- SECTION 6: REPAIRS
-- =============================================================================

CREATE TABLE repairs (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    ticket VARCHAR(150) NOT NULL DEFAULT 'Repair request',
    reason TEXT,
    item_id BIGINT NOT NULL,
    issue_description TEXT,
    priority ENUM('low','medium','high','critical') NOT NULL DEFAULT 'medium',
    status ENUM('pending','accepted','declined','open','in_progress','resolved','cancelled') NOT NULL DEFAULT 'pending',
    technician_id BIGINT,
    repair_location_id BIGINT,
    cost DECIMAL(10,2),
    downtime_hours DECIMAL(10,2),
    before_status VARCHAR(50),
    after_status VARCHAR(50),
    created_by BIGINT,
    reviewed_by BIGINT,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    resolved_at TIMESTAMP NULL,
    reviewed_at TIMESTAMP NULL,
    FOREIGN KEY (item_id) REFERENCES inventory_items(id),
    FOREIGN KEY (technician_id) REFERENCES users(id),
    FOREIGN KEY (repair_location_id) REFERENCES locations(id),
    FOREIGN KEY (created_by) REFERENCES users(id),
    FOREIGN KEY (reviewed_by) REFERENCES users(id),
    INDEX idx_item (item_id),
    INDEX idx_status (status),
    INDEX idx_priority (priority)
);

CREATE TABLE repair_actions (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    repair_id BIGINT NOT NULL,
    action VARCHAR(100),
    component_id BIGINT NULL,
    notes TEXT,
    performed_by BIGINT,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (repair_id) REFERENCES repairs(id) ON DELETE CASCADE,
    FOREIGN KEY (component_id) REFERENCES inventory_items(id),
    FOREIGN KEY (performed_by) REFERENCES users(id)
);

-- =============================================================================
-- SECTION 7: MAINTENANCE SCHEDULING
-- =============================================================================

CREATE TABLE maintenance_schedules (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    item_id BIGINT NOT NULL,
    scheduled_at DATE NOT NULL,
    completed_at DATE NULL,
    assigned_to BIGINT,
    notes TEXT,
    status ENUM('scheduled','completed','skipped') NOT NULL DEFAULT 'scheduled',
    FOREIGN KEY (item_id) REFERENCES inventory_items(id),
    FOREIGN KEY (assigned_to) REFERENCES users(id),
    INDEX idx_scheduled (scheduled_at),
    INDEX idx_item (item_id)
);