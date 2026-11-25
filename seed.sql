-- =============================================================================
-- JMACX LED INVENTORY -- DEMO SEED DATA
-- -----------------------------------------------------------------------------
-- Load AFTER schema.sql, into a database that holds only what schema.sql seeds
-- (the admin user and status_transitions). Rows use explicit ids, so if any of
-- them already exist the script stops at the first duplicate and the open
-- transaction is rolled back: nothing is half-applied, nothing is overwritten.
--
-- All demo users below share the test password: password123
-- (admin keeps the password set by schema.sql)
--
-- Story the data tells (dates in 2026, "today" ~ 2026-11-22):
--   * PO-2026-0001 (Mar): 4x PL2.9 cabinets + modules, PSUs, cards, hubs.
--   * PO-2026-0002 (Jun): 2x PL3.9 cabinets + 3 video processors.
--   * Cebu Music Festival (Aug, completed): CAB-...-0003 and CAB-20260618-0001.
--   * Year-End Product Expo (Nov, active): CAB-...-0001/0002 + VX1000 on site.
--   * MOD-...-0013 failed in CAB-...-0002 on Oct 30, swapped for MOD-...-0006,
--     now flagged defective awaiting a ticket.
--   * Open repairs: a flickering module and a bent cabinet frame.
--   * Retired: a burnt PSU and a water-damaged module.
-- =============================================================================

USE led_inventory;

START TRANSACTION;

SET @admin := (SELECT id FROM users WHERE username = 'admin');

-- -----------------------------------------------------------------------------
-- Users (password123)
-- -----------------------------------------------------------------------------
INSERT INTO users (id, username, password_hash, full_name, email, role, is_active, created_at, deleted_at) VALUES
(2, 'maria.santos', '$2y$12$gUjsNPhYF3pyl6q6PNozn.q9D7oxsG3oYl08g8kc/lFX2JNol4z.6', 'Maria Santos', 'maria.santos@example.com', 'manager',    TRUE,  '2026-01-15 09:00:00', NULL),
(3, 'jose.reyes',   '$2y$12$gUjsNPhYF3pyl6q6PNozn.q9D7oxsG3oYl08g8kc/lFX2JNol4z.6', 'Jose Reyes',   'jose.reyes@example.com',   'warehouse',  TRUE,  '2026-01-15 09:05:00', NULL),
(4, 'ana.cruz',     '$2y$12$gUjsNPhYF3pyl6q6PNozn.q9D7oxsG3oYl08g8kc/lFX2JNol4z.6', 'Ana Cruz',     'ana.cruz@example.com',     'technician', TRUE,  '2026-01-20 10:00:00', NULL),
(5, 'mark.dizon',   '$2y$12$gUjsNPhYF3pyl6q6PNozn.q9D7oxsG3oYl08g8kc/lFX2JNol4z.6', 'Mark Dizon',   'mark.dizon@example.com',   'technician', TRUE,  '2026-02-03 10:00:00', NULL),
(6, 'lea.garcia',   '$2y$12$gUjsNPhYF3pyl6q6PNozn.q9D7oxsG3oYl08g8kc/lFX2JNol4z.6', 'Lea Garcia',   'lea.garcia@example.com',   'staff',      TRUE,  '2026-04-01 08:30:00', NULL),
(7, 'paolo.lim',    '$2y$12$gUjsNPhYF3pyl6q6PNozn.q9D7oxsG3oYl08g8kc/lFX2JNol4z.6', 'Paolo Lim',    'paolo.lim@example.com',    'technician', FALSE, '2026-01-20 10:00:00', '2026-07-31 17:00:00');

-- -----------------------------------------------------------------------------
-- Custom fields (values live in inventory_items.metadata, keyed by field_name)
-- -----------------------------------------------------------------------------
INSERT INTO custom_fields (id, item_type, field_name, field_type, is_required) VALUES
(1, 'cabinet',         'Case Number',       'text',   TRUE),
(2, 'cabinet',         'Last Calibrated',   'date',   FALSE),
(3, 'module',          'Brightness (nits)', 'number', FALSE),
(4, 'module',          'Color Bin',         'text',   FALSE),
(5, 'video_processor', 'Rack Unit',         'text',   FALSE);

-- -----------------------------------------------------------------------------
-- Locations
-- -----------------------------------------------------------------------------
INSERT INTO locations (id, name, type, address, parent_location_id, is_active) VALUES
(1, 'Main Warehouse',           'warehouse',     '12 Industrial Ave, Quezon City', NULL, TRUE),
(2, 'Rack A - Cabinets',        'warehouse',     NULL,                             1,    TRUE),
(3, 'Rack B - Components',      'warehouse',     NULL,                             1,    TRUE),
(4, 'Repair Bench',             'repair_center', NULL,                             1,    TRUE),
(5, 'Delivery Truck 1',         'truck',         'Plate NBC 1234',                 NULL, TRUE),
(6, 'Head Office',              'office',        '88 Ayala Ave, Makati City',      NULL, TRUE),
(7, 'Pasay Convention Hall 3',  'event_site',    'Seaside Blvd, Pasay City',       NULL, TRUE),
(8, 'Cebu Concert Grounds',     'event_site',    'South Road Properties, Cebu City', NULL, TRUE),
(9, 'Makati Hotel Ballroom',    'event_site',    'Paseo de Roxas, Makati City',    NULL, TRUE);

-- -----------------------------------------------------------------------------
-- Manufacturers, models, compatibility, BOM templates
-- -----------------------------------------------------------------------------
INSERT INTO manufacturers (id, name, country, support_contact, website) VALUES
(1, 'Absen',      'China',  'support@absen.example',      'https://www.absen.com'),
(2, 'NovaStar',   'China',  'support@novastar.example',   'https://www.novastar.tech'),
(3, 'Colorlight', 'China',  'support@colorlight.example', 'https://www.colorlightinside.com'),
(4, 'Mean Well',  'Taiwan', 'support@meanwell.example',   'https://www.meanwell.com');

INSERT INTO item_models (id, item_type, manufacturer_id, model_name, specifications) VALUES
(1,  'cabinet',         1, 'PL2.9 Pro Cabinet',    '{"size_mm": "500x500", "pixel_pitch_mm": 2.976, "ip_rating": "IP30", "max_power_w": 180}'),
(2,  'cabinet',         1, 'PL3.9 Pro Cabinet',    '{"size_mm": "500x1000", "pixel_pitch_mm": 3.91, "ip_rating": "IP65", "max_power_w": 350}'),
(3,  'module',          1, 'PL2.9 Module 250x250', '{"pixel_pitch_mm": 2.976, "resolution": "84x84", "led_type": "SMD 3-in-1"}'),
(4,  'module',          1, 'PL3.9 Module 250x250', '{"pixel_pitch_mm": 3.91, "resolution": "64x64", "led_type": "SMD 3-in-1"}'),
(5,  'power_supply',    4, 'LRS-200-4.2',          '{"input": "200-240VAC", "output": "4.2VDC", "current_a": 40, "wattage": 168}'),
(6,  'receiving_card',  2, 'A8s Plus',             '{"max_load_px": "512x384", "ports": 16}'),
(7,  'receiving_card',  3, '5A-75B',               '{"max_load_px": "256x512", "ports": 8}'),
(8,  'hub',             2, 'HUB75E',               '{"interface": "HUB75E", "ports": 12}'),
(9,  'video_processor', 2, 'VX1000',               '{"ethernet_outputs": 10, "max_load_px": 6500000}'),
(10, 'video_processor', 2, 'MCTRL4K',              '{"ethernet_outputs": 16, "max_load_px": 8800000}');

INSERT INTO model_compatibility (parent_model_id, child_model_id, compatibility_type, notes) VALUES
(1, 3, 'native',            'Four modules per cabinet'),
(1, 5, 'native',            NULL),
(1, 6, 'native',            NULL),
(1, 7, 'requires_firmware', 'Needs Colorlight mapping file for PL2.9'),
(1, 8, 'native',            NULL),
(2, 4, 'native',            'Eight modules per cabinet'),
(2, 5, 'native',            NULL),
(2, 6, 'native',            NULL),
(2, 3, 'mechanical_only',   'Fits the frame but pitch does not match PL3.9 modules');

INSERT INTO cabinet_templates (id, name, model_id, required_module_count, required_psu_count, required_hub_count, required_card_count, notes) VALUES
(1, 'PL2.9 Pro 500x500',  1, 4, 1, 1, 1, 'Standard indoor rental cabinet'),
(2, 'PL3.9 Pro 500x1000', 2, 8, 2, 1, 1, 'Outdoor rental cabinet');

-- -----------------------------------------------------------------------------
-- Suppliers & purchase orders
-- -----------------------------------------------------------------------------
INSERT INTO suppliers (id, name, contact_name, contact_email, contact_phone, address) VALUES
(1, 'Lumina Display Solutions Inc.', 'Carlo Mendoza', 'sales@lumina-display.example', '+63 2 8123 4567', 'Pasig City, Metro Manila'),
(2, 'Pixelcraft AV Trading',         'Grace Tan',     'orders@pixelcraft.example',    '+63 32 234 5678', 'Mandaue City, Cebu'),
(3, 'Volt Power Distribution',       'Ramon Uy',      'ramon@voltpower.example',      '+63 917 555 0101', 'Caloocan City, Metro Manila');

INSERT INTO purchase_orders (id, supplier_id, po_number, status, ordered_by, ordered_at, received_at, notes) VALUES
(1, 1, 'PO-2026-0001', 'received',  @admin, '2026-02-20 10:00:00', '2026-03-10 09:00:00', 'Initial PL2.9 rental set'),
(2, 2, 'PO-2026-0002', 'received',  2,      '2026-05-28 14:00:00', '2026-06-18 10:00:00', 'Outdoor cabinets and processors'),
(3, 1, 'PO-2026-0003', 'submitted', 2,      '2026-11-12 11:00:00', NULL,                  'Restock: 8x PL2.9 spare modules'),
(4, 3, 'PO-2026-0004', 'draft',     3,      NULL,                  NULL,                  'Replacement PSUs, pending quote');

-- -----------------------------------------------------------------------------
-- Inventory items
-- Serials follow the app's own format: <PREFIX>-<YYYYMMDD>-<NNNN>, qr_code = serial
-- -----------------------------------------------------------------------------
INSERT INTO inventory_items
(id, item_type, qr_code, serial_number, model_id, batch_id, status, current_location_id, purchase_order_id, metadata, created_by, created_at) VALUES
-- Cabinets
(1, 'cabinet', 'CAB-20260310-0001', 'CAB-20260310-0001', 1, NULL, 'deployed',     7, 1, '{"Case Number": "CASE-01", "Last Calibrated": "2026-11-10"}', 3, '2026-03-10 09:00:00'),
(2, 'cabinet', 'CAB-20260310-0002', 'CAB-20260310-0002', 1, NULL, 'deployed',     7, 1, '{"Case Number": "CASE-02", "Last Calibrated": "2026-11-10"}', 3, '2026-03-10 09:00:00'),
(3, 'cabinet', 'CAB-20260310-0003', 'CAB-20260310-0003', 1, NULL, 'available',    2, 1, '{"Case Number": "CASE-03", "Last Calibrated": "2026-09-10"}', 3, '2026-03-10 09:00:00'),
(4, 'cabinet', 'CAB-20260310-0004', 'CAB-20260310-0004', 1, NULL, 'available',    2, 1, '{"Case Number": "CASE-04"}',                                  3, '2026-03-10 09:00:00'),
(5, 'cabinet', 'CAB-20260618-0001', 'CAB-20260618-0001', 2, NULL, 'available',    2, 2, '{"Case Number": "CASE-05"}',                                  3, '2026-06-18 10:00:00'),
(6, 'cabinet', 'CAB-20260618-0002', 'CAB-20260618-0002', 2, NULL, 'under_repair', 4, 2, '{"Case Number": "CASE-06"}',                                  3, '2026-06-18 10:00:00'),
-- Modules (PL2.9)
(101, 'module', 'MOD-20260310-0001', 'MOD-20260310-0001', 3, 'LOT-PL29-2602A', 'installed',    7, 1, '{"size": "250x250", "Brightness (nits)": "1000", "Color Bin": "A2"}', 3, '2026-03-10 09:00:00'),
(102, 'module', 'MOD-20260310-0002', 'MOD-20260310-0002', 3, 'LOT-PL29-2602A', 'installed',    7, 1, '{"size": "250x250", "Brightness (nits)": "1000", "Color Bin": "A2"}', 3, '2026-03-10 09:00:00'),
(103, 'module', 'MOD-20260310-0003', 'MOD-20260310-0003', 3, 'LOT-PL29-2602A', 'installed',    7, 1, '{"size": "250x250", "Brightness (nits)": "1000", "Color Bin": "A2"}', 3, '2026-03-10 09:00:00'),
(104, 'module', 'MOD-20260310-0004', 'MOD-20260310-0004', 3, 'LOT-PL29-2602A', 'installed',    7, 1, '{"size": "250x250", "Brightness (nits)": "1000", "Color Bin": "A2"}', 3, '2026-03-10 09:00:00'),
(105, 'module', 'MOD-20260310-0005', 'MOD-20260310-0005', 3, 'LOT-PL29-2602A', 'installed',    7, 1, '{"size": "250x250", "Brightness (nits)": "1000", "Color Bin": "A2"}', 3, '2026-03-10 09:00:00'),
(106, 'module', 'MOD-20260310-0006', 'MOD-20260310-0006', 3, 'LOT-PL29-2602A', 'installed',    7, 1, '{"size": "250x250", "Brightness (nits)": "1000", "Color Bin": "A2"}', 3, '2026-03-10 09:00:00'),
(107, 'module', 'MOD-20260310-0007', 'MOD-20260310-0007', 3, 'LOT-PL29-2602A', 'installed',    7, 1, '{"size": "250x250", "Brightness (nits)": "1000", "Color Bin": "A2"}', 3, '2026-03-10 09:00:00'),
(108, 'module', 'MOD-20260310-0008', 'MOD-20260310-0008', 3, 'LOT-PL29-2602A', 'installed',    7, 1, '{"size": "250x250", "Brightness (nits)": "1000", "Color Bin": "A2"}', 3, '2026-03-10 09:00:00'),
(109, 'module', 'MOD-20260310-0009', 'MOD-20260310-0009', 3, 'LOT-PL29-2602A', 'installed',    2, 1, '{"size": "250x250", "Brightness (nits)": "1000", "Color Bin": "A2"}', 3, '2026-03-10 09:00:00'),
(110, 'module', 'MOD-20260310-0010', 'MOD-20260310-0010', 3, 'LOT-PL29-2602A', 'installed',    2, 1, '{"size": "250x250", "Brightness (nits)": "1000", "Color Bin": "A2"}', 3, '2026-03-10 09:00:00'),
(111, 'module', 'MOD-20260310-0011', 'MOD-20260310-0011', 3, 'LOT-PL29-2602A', 'installed',    2, 1, '{"size": "250x250", "Brightness (nits)": "1000", "Color Bin": "A2"}', 3, '2026-03-10 09:00:00'),
(112, 'module', 'MOD-20260310-0012', 'MOD-20260310-0012', 3, 'LOT-PL29-2602A', 'installed',    2, 1, '{"size": "250x250", "Brightness (nits)": "1000", "Color Bin": "A2"}', 3, '2026-03-10 09:00:00'),
(113, 'module', 'MOD-20260310-0013', 'MOD-20260310-0013', 3, 'LOT-PL29-2602B', 'defective',    3, 1, '{"size": "250x250", "Brightness (nits)": "1000", "Color Bin": "A3"}', 3, '2026-03-10 09:00:00'),
(114, 'module', 'MOD-20260310-0014', 'MOD-20260310-0014', 3, 'LOT-PL29-2602B', 'under_repair', 4, 1, '{"size": "250x250", "Brightness (nits)": "1000", "Color Bin": "A3"}', 3, '2026-03-10 09:00:00'),
(115, 'module', 'MOD-20260310-0015', 'MOD-20260310-0015', 3, 'LOT-PL29-2602B', 'available',    3, 1, '{"size": "250x250", "Brightness (nits)": "1000", "Color Bin": "A3"}', 3, '2026-03-10 09:00:00'),
(116, 'module', 'MOD-20260310-0016', 'MOD-20260310-0016', 3, 'LOT-PL29-2602B', 'available',    3, 1, '{"size": "250x250", "Brightness (nits)": "1000", "Color Bin": "A3"}', 3, '2026-03-10 09:00:00'),
(117, 'module', 'MOD-20260310-0017', 'MOD-20260310-0017', 3, 'LOT-PL29-2602B', 'retired',      3, 1, '{"size": "250x250", "Brightness (nits)": "1000", "Color Bin": "A3"}', 3, '2026-03-10 09:00:00'),
-- Power supplies
(201, 'power_supply', 'PWR-20260310-0001', 'PWR-20260310-0001', 5, NULL, 'installed', 7, 1, NULL, 3, '2026-03-10 09:00:00'),
(202, 'power_supply', 'PWR-20260310-0002', 'PWR-20260310-0002', 5, NULL, 'installed', 7, 1, NULL, 3, '2026-03-10 09:00:00'),
(203, 'power_supply', 'PWR-20260310-0003', 'PWR-20260310-0003', 5, NULL, 'installed', 2, 1, NULL, 3, '2026-03-10 09:00:00'),
(204, 'power_supply', 'PWR-20260310-0004', 'PWR-20260310-0004', 5, NULL, 'available', 3, 1, NULL, 3, '2026-03-10 09:00:00'),
(205, 'power_supply', 'PWR-20260310-0005', 'PWR-20260310-0005', 5, NULL, 'retired',   3, 1, NULL, 3, '2026-03-10 09:00:00'),
-- Receiving cards
(301, 'receiving_card', 'RCV-20260310-0001', 'RCV-20260310-0001', 6, NULL, 'installed', 7, 1, NULL, 3, '2026-03-10 09:00:00'),
(302, 'receiving_card', 'RCV-20260310-0002', 'RCV-20260310-0002', 6, NULL, 'installed', 7, 1, NULL, 3, '2026-03-10 09:00:00'),
(303, 'receiving_card', 'RCV-20260310-0003', 'RCV-20260310-0003', 6, NULL, 'installed', 2, 1, NULL, 3, '2026-03-10 09:00:00'),
(304, 'receiving_card', 'RCV-20260310-0004', 'RCV-20260310-0004', 7, NULL, 'available', 3, 1, NULL, 3, '2026-03-10 09:00:00'),
-- Hubs
(401, 'hub', 'HUB-20260310-0001', 'HUB-20260310-0001', 8, NULL, 'installed', 7, 1, NULL, 3, '2026-03-10 09:00:00'),
(402, 'hub', 'HUB-20260310-0002', 'HUB-20260310-0002', 8, NULL, 'installed', 7, 1, NULL, 3, '2026-03-10 09:00:00'),
(403, 'hub', 'HUB-20260310-0003', 'HUB-20260310-0003', 8, NULL, 'installed', 2, 1, NULL, 3, '2026-03-10 09:00:00'),
(404, 'hub', 'HUB-20260310-0004', 'HUB-20260310-0004', 8, NULL, 'available', 3, 1, NULL, 3, '2026-03-10 09:00:00'),
-- Video processors
(501, 'video_processor', 'VID-20260618-0001', 'VID-20260618-0001', 9,  NULL, 'deployed',  7, 2, '{"Rack Unit": "AV-RACK-01"}', 3, '2026-06-18 10:00:00'),
(502, 'video_processor', 'VID-20260618-0002', 'VID-20260618-0002', 10, NULL, 'available', 3, 2, '{"Rack Unit": "AV-RACK-02"}', 3, '2026-06-18 10:00:00'),
(503, 'video_processor', 'VID-20260618-0003', 'VID-20260618-0003', 9,  NULL, 'available', 3, 2, '{"Rack Unit": "AV-RACK-03"}', 3, '2026-06-18 10:00:00');

-- -----------------------------------------------------------------------------
-- 1:1 spec tables
-- -----------------------------------------------------------------------------
INSERT INTO spec_cabinets (item_id, size, template_id, width_mm, height_mm, depth_mm, weight_kg) VALUES
(1, '500x500',  1, 500, 500,  78, 7.50),
(2, '500x500',  1, 500, 500,  78, 7.50),
(3, '500x500',  1, 500, 500,  78, 7.50),
(4, '500x500',  1, 500, 500,  78, 7.50),
(5, '500x1000', 2, 500, 1000, 90, 14.20),
(6, '500x1000', 2, 500, 1000, 90, 14.20);

INSERT INTO spec_modules (item_id, pixel_pitch, resolution_width, resolution_height, width_mm, height_mm, scan_rate, refresh_rate)
SELECT id, 2.98, 84, 84, 250, 250, '1/21', 3840 FROM inventory_items WHERE id BETWEEN 101 AND 117;

INSERT INTO spec_power_supplies (item_id, voltage_in, voltage_out, current_a, wattage)
SELECT id, '200-240VAC', '4.2VDC', 40.00, 168 FROM inventory_items WHERE id BETWEEN 201 AND 205;

INSERT INTO spec_video_processors (item_id, firmware_version, input_ports, output_ports, last_serviced_at) VALUES
(501, 'V1.4.2', 6, 10, '2026-11-14'),
(502, 'V2.1.0', 3, 16, '2026-07-01'),
(503, 'V1.4.2', 6, 10, NULL);

-- -----------------------------------------------------------------------------
-- Warranties
-- -----------------------------------------------------------------------------
INSERT INTO warranties (item_id, supplier_id, purchase_date, warranty_expiry, notes)
SELECT id, 1, '2026-03-10', '2028-03-10', '2-year manufacturer warranty' FROM inventory_items WHERE id BETWEEN 1 AND 4;
INSERT INTO warranties (item_id, supplier_id, purchase_date, warranty_expiry, notes)
SELECT id, 2, '2026-06-18', '2028-06-18', '2-year manufacturer warranty' FROM inventory_items WHERE id IN (5, 6);
INSERT INTO warranties (item_id, supplier_id, purchase_date, warranty_expiry, notes)
SELECT id, 1, '2026-03-10', '2026-12-10', '9-month distributor warranty' FROM inventory_items WHERE id BETWEEN 201 AND 205;
INSERT INTO warranties (item_id, supplier_id, purchase_date, warranty_expiry, notes)
SELECT id, 2, '2026-06-18', '2029-06-18', '3-year warranty incl. firmware support' FROM inventory_items WHERE id BETWEEN 501 AND 503;

-- -----------------------------------------------------------------------------
-- Cabinet assemblies
-- MOD-...-0013 sat in CAB-...-0002 slot M2 until it failed on Oct 30.
-- -----------------------------------------------------------------------------
INSERT INTO asset_hierarchy (parent_id, child_id, slot_info, installed_at, removed_at, status) VALUES
(1, 101, 'M1',   '2026-03-12 10:00:00', NULL, 'installed'),
(1, 102, 'M2',   '2026-03-12 10:00:00', NULL, 'installed'),
(1, 103, 'M3',   '2026-03-12 10:00:00', NULL, 'installed'),
(1, 104, 'M4',   '2026-03-12 10:00:00', NULL, 'installed'),
(1, 201, 'PSU1', '2026-03-12 10:00:00', NULL, 'installed'),
(1, 301, 'CARD', '2026-03-12 10:00:00', NULL, 'installed'),
(1, 401, 'HUB',  '2026-03-12 10:00:00', NULL, 'installed'),
(2, 105, 'M1',   '2026-03-12 11:00:00', NULL, 'installed'),
(2, 113, 'M2',   '2026-03-12 11:00:00', '2026-10-30 15:00:00', 'removed'),
(2, 106, 'M2',   '2026-10-30 15:30:00', NULL, 'installed'),
(2, 107, 'M3',   '2026-03-12 11:00:00', NULL, 'installed'),
(2, 108, 'M4',   '2026-03-12 11:00:00', NULL, 'installed'),
(2, 202, 'PSU1', '2026-03-12 11:00:00', NULL, 'installed'),
(2, 302, 'CARD', '2026-03-12 11:00:00', NULL, 'installed'),
(2, 402, 'HUB',  '2026-03-12 11:00:00', NULL, 'installed'),
(3, 109, 'M1',   '2026-03-12 13:00:00', NULL, 'installed'),
(3, 110, 'M2',   '2026-03-12 13:00:00', NULL, 'installed'),
(3, 111, 'M3',   '2026-03-12 13:00:00', NULL, 'installed'),
(3, 112, 'M4',   '2026-03-12 13:00:00', NULL, 'installed'),
(3, 203, 'PSU1', '2026-03-12 13:00:00', NULL, 'installed'),
(3, 303, 'CARD', '2026-03-12 13:00:00', NULL, 'installed'),
(3, 403, 'HUB',  '2026-03-12 13:00:00', NULL, 'installed');

-- -----------------------------------------------------------------------------
-- Deployments
-- -----------------------------------------------------------------------------
INSERT INTO deployments (id, name, location_id, start_date, end_date, status, created_by, approved_by, created_at) VALUES
(1, 'Year-End Product Expo',     7, '2026-11-18', '2026-11-24', 'active',    2, @admin, '2026-11-02 09:00:00'),
(2, 'Cebu Music Festival',       8, '2026-08-14', '2026-08-17', 'completed', 2, @admin, '2026-07-25 09:00:00'),
(3, 'Corporate Launch Backdrop', 9, '2026-12-05', '2026-12-06', 'planned',   2, NULL,   '2026-11-19 14:00:00'),
(4, 'Cebu Mall Activation',      8, '2026-10-03', '2026-10-04', 'cancelled', 6, NULL,   '2026-09-15 10:00:00');

INSERT INTO deployment_items (deployment_id, item_id, screen_name, pos_x, pos_y, assigned_at, returned_at) VALUES
(1, 1,   'Main Stage', 0, 0, '2026-11-17 08:00:00', NULL),
(1, 2,   'Main Stage', 1, 0, '2026-11-17 08:00:00', NULL),
(1, 501, 'Main Stage', NULL, NULL, '2026-11-17 08:00:00', NULL),
(2, 3,   'Stage Left', 0, 0, '2026-08-12 07:00:00', '2026-08-19 16:00:00'),
(2, 5,   'Stage Left', 0, 1, '2026-08-12 07:00:00', '2026-08-19 16:00:00');

-- -----------------------------------------------------------------------------
-- Repairs
-- -----------------------------------------------------------------------------
INSERT INTO repairs (id, item_id, issue_description, priority, status, technician_id, repair_location_id, cost, downtime_hours, before_status, after_status, created_by, created_at, resolved_at) VALUES
(1, 114, 'Rows 32-48 flicker at low brightness; suspect driver IC.',                    'medium',   'in_progress', 4, 4, NULL,   NULL,  'available', NULL,        3, '2026-11-10 10:00:00', NULL),
(2, 6,   'Frame corner bent in transit; locking pin misaligned.',                       'high',     'open',        5, 4, NULL,   NULL,  'available', NULL,        3, '2026-11-15 16:00:00', NULL),
(3, 115, 'Module not detected by receiving card; ribbon cable damaged.',                'low',      'resolved',    4, 4, 350.00, 26.50, 'available', 'available', 3, '2026-09-02 09:00:00', '2026-09-03 11:30:00'),
(4, 205, 'Output dropped to 3.1V under load, burnt smell.',                             'critical', 'resolved',    5, 4, 0.00,   4.00,  'available', 'retired',   3, '2026-07-20 13:00:00', '2026-07-21 17:00:00'),
(5, 117, 'Water damage from a roof leak over Rack B; corrosion on PCB.',                'high',     'resolved',    4, 4, 0.00,   6.00,  'available', 'retired',   3, '2026-08-25 09:00:00', '2026-08-27 15:00:00');

INSERT INTO repair_actions (repair_id, action, component_id, notes, performed_by, created_at) VALUES
(1, 'Diagnosis',               NULL, 'Confirmed flicker on rows 32-48; IC U7 runs hot.',      4, '2026-11-10 14:00:00'),
(1, 'Ordered replacement IC',  NULL, 'ETA 2026-11-25 from supplier.',                          4, '2026-11-11 09:30:00'),
(2, 'Inspection',              NULL, 'Bent corner bracket; modules and electronics unaffected.', 5, '2026-11-16 10:00:00'),
(3, 'Replaced ribbon cable',   NULL, 'Swapped 16-pin HUB75 ribbon.',                           4, '2026-09-02 15:00:00'),
(3, 'Burn-in test',            NULL, '24h burn-in passed, no dead pixels.',                    4, '2026-09-03 11:00:00'),
(4, 'Load test',               NULL, 'Failed at 60% load. Not economical to repair.',          5, '2026-07-21 10:00:00'),
(4, 'Retired',                 NULL, 'Moved to scrap shelf on Rack B.',                        5, '2026-07-21 17:00:00'),
(5, 'Inspection',              NULL, 'Corrosion across driver ICs; beyond repair.',            4, '2026-08-26 10:00:00'),
(5, 'Retired',                 NULL, 'Kept for spare connectors.',                             4, '2026-08-27 15:00:00');

-- -----------------------------------------------------------------------------
-- Maintenance
-- -----------------------------------------------------------------------------
INSERT INTO maintenance_schedules (item_id, scheduled_at, completed_at, assigned_to, notes, status) VALUES
(3,   '2026-09-10', '2026-09-10', 4, 'Post-festival cleaning and color calibration', 'completed'),
(1,   '2026-11-27', NULL,         4, 'Post-expo cleaning and calibration',           'scheduled'),
(2,   '2026-11-27', NULL,         4, 'Post-expo cleaning and calibration',           'scheduled'),
(501, '2026-12-01', NULL,         5, 'Dust filter cleaning, firmware check',         'scheduled'),
(502, '2026-10-15', NULL,         5, 'Firmware update to V2.2',                      'skipped');

-- -----------------------------------------------------------------------------
-- Stock movements
-- -----------------------------------------------------------------------------
-- Receiving: cabinets to Rack A, everything else to Rack B
INSERT INTO stock_movements (item_id, movement_type, from_location_id, to_location_id, reference_type, reference_id, notes, created_by, created_at)
SELECT id, 'receive', NULL, IF(item_type = 'cabinet', 2, 3), 'purchase_order', purchase_order_id, 'Received against PO', 3, created_at
FROM inventory_items WHERE id IN (1,2,3,4,5,6) OR id BETWEEN 101 AND 503;

-- Components fitted into cabinets on Rack A
INSERT INTO stock_movements (item_id, movement_type, from_location_id, to_location_id, reference_type, reference_id, notes, created_by, created_at)
SELECT child_id, 'transfer', 3, 2, NULL, NULL, CONCAT('Installed in cabinet, slot ', slot_info), 3, installed_at
FROM asset_hierarchy;

INSERT INTO stock_movements (item_id, movement_type, from_location_id, to_location_id, reference_type, reference_id, notes, created_by, created_at) VALUES
-- Cebu Music Festival
(3,   'deploy',        2, 8, 'deployment', 2, 'Shipped for Cebu Music Festival', 3, '2026-08-12 07:00:00'),
(5,   'deploy',        2, 8, 'deployment', 2, 'Shipped for Cebu Music Festival', 3, '2026-08-12 07:00:00'),
(3,   'return',        8, 2, 'deployment', 2, 'Returned from Cebu Music Festival', 3, '2026-08-19 16:00:00'),
(5,   'return',        8, 2, 'deployment', 2, 'Returned from Cebu Music Festival', 3, '2026-08-19 16:00:00'),
-- Retired after repair
(205, 'repair_send',   3, 4, 'repair', 4, NULL, 3, '2026-07-20 13:00:00'),
(205, 'retire',        4, 3, 'repair', 4, 'Scrap shelf', 5, '2026-07-21 17:00:00'),
(117, 'repair_send',   3, 4, 'repair', 5, NULL, 3, '2026-08-25 09:00:00'),
(117, 'retire',        4, 3, 'repair', 5, 'Kept for spare connectors', 4, '2026-08-27 15:00:00'),
-- Repaired and returned to stock
(115, 'repair_send',   3, 4, 'repair', 3, NULL, 3, '2026-09-02 09:00:00'),
(115, 'repair_return', 4, 3, 'repair', 3, NULL, 4, '2026-09-03 11:30:00'),
-- Module swap in CAB-20260310-0002
(113, 'transfer',      2, 3, NULL, NULL, 'Removed from CAB-20260310-0002 slot M2 (dead pixels)', 4, '2026-10-30 15:00:00'),
-- Open repairs
(114, 'repair_send',   3, 4, 'repair', 1, NULL, 3, '2026-11-10 10:00:00'),
(6,   'repair_send',   2, 4, 'repair', 2, NULL, 3, '2026-11-15 16:00:00'),
-- Year-End Product Expo
(1,   'deploy',        2, 7, 'deployment', 1, 'Shipped for Year-End Product Expo', 3, '2026-11-17 08:00:00'),
(2,   'deploy',        2, 7, 'deployment', 1, 'Shipped for Year-End Product Expo', 3, '2026-11-17 08:00:00'),
(501, 'deploy',        3, 7, 'deployment', 1, 'Shipped for Year-End Product Expo', 3, '2026-11-17 08:00:00');

-- Components travelling inside the expo cabinets
INSERT INTO stock_movements (item_id, movement_type, from_location_id, to_location_id, reference_type, reference_id, notes, created_by, created_at)
SELECT child_id, 'transfer', 2, 7, 'deployment', 1, 'Moved with parent cabinet', 3, '2026-11-17 08:00:00'
FROM asset_hierarchy WHERE parent_id IN (1, 2) AND status = 'installed';

-- -----------------------------------------------------------------------------
-- Audit log (inventory_logs)
-- -----------------------------------------------------------------------------
INSERT INTO inventory_logs (item_id, action, from_status, to_status, notes, performed_by, action_at)
SELECT id, 'create', NULL, 'available', CONCAT(REPLACE(item_type, '_', ' '), ' created'), 3, created_at
FROM inventory_items WHERE id IN (1,2,3,4,5,6) OR id BETWEEN 101 AND 503;

INSERT INTO inventory_logs (item_id, action, from_status, to_status, notes, performed_by, action_at)
SELECT child_id, 'status_change', 'available', 'installed', CONCAT('Installed in cabinet ', parent_id, ' slot ', slot_info), 3, installed_at
FROM asset_hierarchy;

INSERT INTO inventory_logs (item_id, action, from_status, to_status, notes, performed_by, action_at) VALUES
(205, 'repair',        'available',    'under_repair', 'Sent to repair. Issue: output voltage drop', 3, '2026-07-20 13:00:00'),
(205, 'retire',        'under_repair', 'retired',      'Failed load test, retired',                  5, '2026-07-21 17:00:00'),
(3,   'deploy',        'available',    'deployed',     'Assigned to deployment ID 2',                3, '2026-08-12 07:00:00'),
(5,   'deploy',        'available',    'deployed',     'Assigned to deployment ID 2',                3, '2026-08-12 07:00:00'),
(3,   'return',        'deployed',     'available',    'Returned from deployment ID 2',              3, '2026-08-19 16:00:00'),
(5,   'return',        'deployed',     'available',    'Returned from deployment ID 2',              3, '2026-08-19 16:00:00'),
(117, 'repair',        'available',    'under_repair', 'Sent to repair. Issue: water damage',        3, '2026-08-25 09:00:00'),
(117, 'retire',        'under_repair', 'retired',      'Corrosion beyond repair, retired',           4, '2026-08-27 15:00:00'),
(115, 'repair',        'available',    'under_repair', 'Sent to repair. Issue: not detected',        3, '2026-09-02 09:00:00'),
(115, 'status_change', 'under_repair', 'available',    'Repair resolved',                            4, '2026-09-03 11:30:00'),
(113, 'status_change', 'installed',    'defective',    'Dead pixel cluster; removed from CAB-20260310-0002', 4, '2026-10-30 15:00:00'),
(114, 'repair',        'available',    'under_repair', 'Sent to repair. Issue: row flicker',         3, '2026-11-10 10:00:00'),
(6,   'repair',        'available',    'under_repair', 'Sent to repair. Issue: bent frame',          3, '2026-11-15 16:00:00'),
(1,   'deploy',        'available',    'deployed',     'Assigned to deployment ID 1',                3, '2026-11-17 08:00:00'),
(2,   'deploy',        'available',    'deployed',     'Assigned to deployment ID 1',                3, '2026-11-17 08:00:00'),
(501, 'deploy',        'available',    'deployed',     'Assigned to deployment ID 1',                3, '2026-11-17 08:00:00');

-- -----------------------------------------------------------------------------
-- QR scans
-- -----------------------------------------------------------------------------
INSERT INTO qr_scan_logs (item_id, scanned_by, location_id, action, scanned_at) VALUES
(1,   3, 2, 'audit',  '2026-10-01 09:10:00'),
(2,   3, 2, 'audit',  '2026-10-01 09:11:00'),
(3,   3, 2, 'audit',  '2026-10-01 09:12:00'),
(4,   3, 2, 'audit',  '2026-10-01 09:13:00'),
(113, 4, 2, 'verify', '2026-10-30 14:45:00'),
(114, 4, 4, 'verify', '2026-11-10 10:05:00'),
(6,   5, 4, 'verify', '2026-11-15 16:10:00'),
(1,   3, 2, 'pack',   '2026-11-17 07:30:00'),
(2,   3, 2, 'pack',   '2026-11-17 07:31:00'),
(501, 3, 3, 'pack',   '2026-11-17 07:35:00'),
(1,   6, 7, 'verify', '2026-11-18 06:45:00'),
(2,   6, 7, 'verify', '2026-11-18 06:46:00'),
(501, 6, 7, 'verify', '2026-11-18 06:50:00'),
(116, 3, 3, 'audit',  '2026-11-20 10:00:00');

COMMIT;
