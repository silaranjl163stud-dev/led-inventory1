<?php
class CabinetController {
    private $db;

    public function __construct() {
        require_once dirname(__DIR__) . '/config/database.php';
        $database = new Database();
        $this->db = $database->getConnection();
    }

    public function processRequest($method, $uriParts) {
        $id = $uriParts[1] ?? null;
        $subResource = $uriParts[2] ?? null;
        $subId = $uriParts[3] ?? null;

        if ($method === 'PUT' && $id && !$subResource) {
            $this->updateCabinetStatus($id);
            return;
        }

        if ($method === 'POST' && $id && $subResource === 'components') {
            $this->installComponent($id);
            return;
        }

        if ($method === 'DELETE' && $id && $subResource === 'components' && $subId) {
            $this->removeComponent($id, $subId);
            return;
        }

        switch ($method) {
            case 'GET':
                if ($id) {
                    $this->getCabinet($id);
                } else {
                    $this->getAllCabinets();
                }
                break;
            case 'POST':
                $this->createCabinet();
                break;
            default:
                http_response_code(405);
                echo json_encode(["message" => "Method not allowed"]);
                break;
        }
    }

    private function getAllCabinets() {
        $stmt = $this->db->query("
            SELECT i.id, i.qr_code, i.serial_number, i.status, i.created_at, i.updated_at,
                   m.model_name AS model, mf.name AS manufacturer, c.size, i.metadata AS custom_properties
            FROM inventory_items i
            LEFT JOIN item_models m ON i.model_id = m.id
            LEFT JOIN manufacturers mf ON m.manufacturer_id = mf.id
            LEFT JOIN spec_cabinets c ON i.id = c.item_id
            WHERE i.item_type = 'cabinet'
            ORDER BY i.updated_at DESC
        ");
        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode($result);
    }

    private function getCabinet($id) {
        $stmt = $this->db->prepare("
            SELECT i.id, i.qr_code, i.serial_number, i.status, i.created_at, i.updated_at,
                   m.model_name AS model, mf.name AS manufacturer, c.size, i.metadata AS custom_properties
            FROM inventory_items i
            LEFT JOIN item_models m ON i.model_id = m.id
            LEFT JOIN manufacturers mf ON m.manufacturer_id = mf.id
            LEFT JOIN spec_cabinets c ON i.id = c.item_id
            WHERE i.item_type = 'cabinet' AND (i.id = ? OR i.qr_code = ?)
        ");
        $stmt->execute([$id, $id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($result) {
            // Fetch internal components using asset_hierarchy
            $stmt_comp = $this->db->prepare("
                SELECT ah.child_id AS component_id, ah.slot_info AS slot,
                       child.item_type AS component_type, child.qr_code AS comp_qr_code
                FROM asset_hierarchy ah
                JOIN inventory_items child ON ah.child_id = child.id
                WHERE ah.parent_id = ? AND ah.status = 'installed'
            ");
            $stmt_comp->execute([$result['id']]);
            $result['components'] = $stmt_comp->fetchAll(PDO::FETCH_ASSOC);
            
            echo json_encode($result);
        } else {
            http_response_code(404);
            echo json_encode(["message" => "Cabinet not found"]);
        }
    }

    private function getOrCreateManufacturer($name) {
        if (empty($name)) $name = 'Unknown Manufacturer';
        $stmt = $this->db->prepare("SELECT id FROM manufacturers WHERE name = ?");
        $stmt->execute([$name]);
        $id = $stmt->fetchColumn();
        if ($id) return $id;
        
        $stmt = $this->db->prepare("INSERT INTO manufacturers (name) VALUES (?)");
        $stmt->execute([$name]);
        return $this->db->lastInsertId();
    }

    private function getOrCreateModel($itemType, $manufacturerId, $modelName) {
        if (empty($modelName)) $modelName = 'Unknown Model';
        $stmt = $this->db->prepare("SELECT id FROM item_models WHERE item_type = ? AND manufacturer_id = ? AND model_name = ?");
        $stmt->execute([$itemType, $manufacturerId, $modelName]);
        $id = $stmt->fetchColumn();
        if ($id) return $id;
        
        $stmt = $this->db->prepare("INSERT INTO item_models (item_type, manufacturer_id, model_name) VALUES (?, ?, ?)");
        $stmt->execute([$itemType, $manufacturerId, $modelName]);
        return $this->db->lastInsertId();
    }

    private function createCabinet() {
        try {
            $data = json_decode(file_get_contents("php://input"), true);
            $quantity = isset($data['quantity']) ? (int)$data['quantity'] : 1;
            if ($quantity < 1) $quantity = 1;

            $datePrefix = date('Ymd');
            $prefix = 'CAB-' . $datePrefix . '-';

            $stmt_seq = $this->db->prepare("SELECT serial_number FROM inventory_items WHERE item_type = 'cabinet' AND serial_number LIKE ? ORDER BY id DESC LIMIT 1");
            $stmt_seq->execute([$prefix . '%']);
            $lastRow = $stmt_seq->fetch(PDO::FETCH_ASSOC);
            
            $counter = 0;
            if ($lastRow && isset($lastRow['serial_number'])) {
                $parts = explode('-', $lastRow['serial_number']);
                $counter = (int)end($parts);
            }

            $custom_properties = isset($data['custom_properties']) ? json_encode($data['custom_properties']) : null;

            $this->db->beginTransaction();
            $firstId = null;
            
            $manufacturerId = $this->getOrCreateManufacturer($data['manufacturer'] ?? null);
            $modelId = $this->getOrCreateModel('cabinet', $manufacturerId, $data['model'] ?? null);

            $stmt = $this->db->prepare("INSERT INTO inventory_items (item_type, qr_code, serial_number, model_id, metadata) VALUES ('cabinet', ?, ?, ?, ?)");
            $stmt_spec = $this->db->prepare("INSERT INTO spec_cabinets (item_id, size) VALUES (?, ?)");

            for ($i = 1; $i <= $quantity; $i++) {
                $counter++;
                $serial_number = $prefix . str_pad($counter, 4, '0', STR_PAD_LEFT);
                $qr_code = $serial_number;

                $stmt->execute([$qr_code, $serial_number, $modelId, $custom_properties]);
                $itemId = $this->db->lastInsertId();
                if ($i === 1) $firstId = $itemId;
                
                $stmt_spec->execute([$itemId, $data['size'] ?? null]);
                $this->logAction('create', $itemId, null, 'Cabinet created');
            }
            $this->db->commit();
            
            http_response_code(201);
            echo json_encode(["message" => "$quantity Cabinet(s) created", "id" => $firstId]);
        } catch(PDOException $e) {
            if ($this->db->inTransaction()) $this->db->rollBack();
            http_response_code(500);
            echo json_encode(["message" => "DB Error: " . $e->getMessage(), "code" => $e->getCode()]);
        } catch(Exception $e) {
            http_response_code(500);
            echo json_encode(["message" => "Server Error: " . $e->getMessage()]);
        }
    }

    private function updateCabinetStatus($id) {
        $data = json_decode(file_get_contents("php://input"), true);
        if (!isset($data['status'])) {
            http_response_code(400); echo json_encode(["message" => "Missing status"]); return;
        }
        
        $stmtCheck = $this->db->prepare("SELECT status FROM inventory_items WHERE id = ?");
        $stmtCheck->execute([$id]);
        $oldStatus = $stmtCheck->fetchColumn();

        $status = $data['status'];
        if ($oldStatus === 'sold' || $status === 'sold') {
            http_response_code(409);
            echo json_encode(["message" => "Sold status can only be set by accepting a customer order"]);
            return;
        }
        $stmt = $this->db->prepare("UPDATE inventory_items SET status = ? WHERE id = ?");
        $stmt->execute([$status, $id]);
        
        $this->logAction('status_change', $id, $oldStatus, $status, "Status updated to $status");
        echo json_encode(["message" => "Status updated successfully"]);
    }

    private function installComponent($cabinetId) {
        $data = json_decode(file_get_contents("php://input"), true);
        if (!is_array($data)) {
            http_response_code(400);
            echo json_encode(["message" => "Invalid component installation request"]);
            return;
        }

        $type = $data['component_type'] ?? null;
        $compId = filter_var($data['component_id'] ?? null, FILTER_VALIDATE_INT);
        $slot = $data['slot'] ?? null;
        $allowedTypes = ['module', 'power_supply', 'receiving_card', 'hub'];

        if (!$compId || $compId < 1 || !is_string($type) || !in_array($type, $allowedTypes, true)) {
            http_response_code(400);
            echo json_encode(["message" => "Provide a valid cabinet component type and ID"]);
            return;
        }
        if ($slot !== null && (!is_string($slot) || strlen($slot) > 50)) {
            http_response_code(400);
            echo json_encode(["message" => "Slot must be 50 characters or fewer"]);
            return;
        }

        try {
            $this->db->beginTransaction();

            $stmtCabinet = $this->db->prepare("
                SELECT id FROM inventory_items
                WHERE id = ? AND item_type = 'cabinet' AND deleted_at IS NULL AND is_active = 1
                FOR UPDATE
            ");
            $stmtCabinet->execute([$cabinetId]);
            if (!$stmtCabinet->fetchColumn()) {
                $this->db->rollBack();
                http_response_code(404);
                echo json_encode(["message" => "Cabinet not found"]);
                return;
            }

            $stmtCheck = $this->db->prepare("
                SELECT status, item_type FROM inventory_items
                WHERE id = ? AND deleted_at IS NULL AND is_active = 1
                FOR UPDATE
            ");
            $stmtCheck->execute([$compId]);
            $comp = $stmtCheck->fetch(PDO::FETCH_ASSOC);

            if (!$comp) {
                $this->db->rollBack();
                http_response_code(404);
                echo json_encode(["message" => "Component not found"]);
                return;
            }
            if ($comp['item_type'] !== $type) {
                $this->db->rollBack();
                http_response_code(400);
                echo json_encode(["message" => "Component type does not match the scanned item"]);
                return;
            }
            if ($comp['status'] !== 'available') {
                $this->db->rollBack();
                http_response_code(409);
                echo json_encode(["message" => "Component cannot be installed because its status is not 'available'."]);
                return;
            }

            $stmt = $this->db->prepare("INSERT INTO asset_hierarchy (parent_id, child_id, slot_info, status) VALUES (?, ?, ?, 'installed')");
            $stmt->execute([$cabinetId, $compId, $slot]);

            $stmt2 = $this->db->prepare("UPDATE inventory_items SET status = 'installed' WHERE id = ?");
            $stmt2->execute([$compId]);

            $this->logAction('status_change', $compId, 'available', 'installed', "Installed into cabinet $cabinetId");
            $this->logAction('status_change', $cabinetId, null, null, "Installed component {$comp['item_type']} $compId");

            $this->db->commit();
            http_response_code(201);
            echo json_encode(["message" => "Component installed"]);
        } catch(PDOException $e) {
            if ($this->db->inTransaction()) $this->db->rollBack();
            error_log($e->getMessage());
            http_response_code(500); echo json_encode(["message" => "Unable to install component"]);
        }
    }

    private function removeComponent($cabinetId, $compId) {
        try {
            $this->db->beginTransaction();

            $stmt = $this->db->prepare("UPDATE asset_hierarchy SET status = 'removed', removed_at = CURRENT_TIMESTAMP WHERE parent_id = ? AND child_id = ? AND status = 'installed'");
            $stmt->execute([$cabinetId, $compId]);
            if ($stmt->rowCount() !== 1) {
                $this->db->rollBack();
                http_response_code(404);
                echo json_encode(["message" => "Installed component not found in this cabinet"]);
                return;
            }

            $stmt2 = $this->db->prepare("UPDATE inventory_items SET status = 'available' WHERE id = ?");
            $stmt2->execute([$compId]);

            $this->logAction('status_change', $compId, 'installed', 'available', "Removed from cabinet $cabinetId");
            $this->logAction('status_change', $cabinetId, null, null, "Removed component $compId");

            $this->db->commit();
            echo json_encode(["message" => "Component removed"]);
        } catch(PDOException $e) {
            if ($this->db->inTransaction()) $this->db->rollBack();
            error_log($e->getMessage());
            http_response_code(500); echo json_encode(["message" => "Unable to remove component"]);
        }
    }

    private function logAction($action, $itemId, $fromStatus = null, $toStatus = null, $notes = '') {
        try {
            $stmt = $this->db->prepare("INSERT INTO inventory_logs (item_id, action, from_status, to_status, notes) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$itemId, $action, $fromStatus, $toStatus, $notes]);
        } catch(Exception $e) {
            // Ignore log error
        }
    }
}
?>
