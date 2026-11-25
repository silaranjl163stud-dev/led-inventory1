<?php
class ComponentController {
    private $db;

    public function __construct() {
        require_once dirname(__DIR__) . '/config/database.php';
        $database = new Database();
        $this->db = $database->getConnection();
    }

    public function processRequest($method, $uriParts) {
        $type = $uriParts[1] ?? null; 
        $id = $uriParts[2] ?? null; 

        if (!$type || !in_array($type, ['modules', 'power_supplies', 'receiving_cards', 'hubs', 'video_processors'])) {
            http_response_code(400);
            echo json_encode(["message" => "Invalid component type or unsupported"]);
            return;
        }

        if ($method === 'PUT' && $id) {
            $this->updateComponentStatus($type, $id);
            return;
        }

        if ($method === 'GET') {
            if ($id) {
                $this->getComponent($type, $id);
            } else {
                $this->getAllComponents($type);
            }
        } elseif ($method === 'POST') {
            $this->createComponent($type);
        } else {
            http_response_code(405);
            echo json_encode(["message" => "Method not allowed"]);
        }
    }
    
    private function getInternalType($type) {
        if (substr($type, -1) == 's') {
            $type = rtrim($type, 's');
            if ($type == 'power_supplie') $type = 'power_supply';
        }
        return $type;
    }

    private function getAllComponents($type) {
        $internalType = $this->getInternalType($type);
        
        $sql = "SELECT i.id, i.qr_code, i.serial_number, i.status, i.created_at, i.updated_at,
                       m.model_name AS model, mf.name AS manufacturer, i.metadata AS custom_properties";
                       
        if ($internalType === 'module') {
            $sql .= ", s.pixel_pitch, JSON_UNQUOTE(JSON_EXTRACT(i.metadata, '$.size')) AS size FROM inventory_items i LEFT JOIN spec_modules s ON i.id = s.item_id ";
        } elseif ($internalType === 'power_supply') {
            $sql .= ", s.voltage_in AS voltage, s.current_a AS current FROM inventory_items i LEFT JOIN spec_power_supplies s ON i.id = s.item_id ";
        } elseif ($internalType === 'video_processor') {
            $sql .= " FROM inventory_items i LEFT JOIN spec_video_processors s ON i.id = s.item_id ";
        } else {
            $sql .= " FROM inventory_items i ";
        }
        
        $sql .= " LEFT JOIN item_models m ON i.model_id = m.id LEFT JOIN manufacturers mf ON m.manufacturer_id = mf.id WHERE i.item_type = ? ORDER BY i.updated_at DESC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([$internalType]);
        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode($result);
    }
    
    private function getComponent($type, $id) {
        $internalType = $this->getInternalType($type);
        
        $sql = "SELECT i.id, i.qr_code, i.serial_number, i.status, i.created_at, i.updated_at,
                       m.model_name AS model, mf.name AS manufacturer, i.metadata AS custom_properties";
                       
        if ($internalType === 'module') {
            $sql .= ", s.pixel_pitch, JSON_UNQUOTE(JSON_EXTRACT(i.metadata, '$.size')) AS size FROM inventory_items i LEFT JOIN spec_modules s ON i.id = s.item_id ";
        } elseif ($internalType === 'power_supply') {
            $sql .= ", s.voltage_in AS voltage, s.current_a AS current FROM inventory_items i LEFT JOIN spec_power_supplies s ON i.id = s.item_id ";
        } elseif ($internalType === 'video_processor') {
            $sql .= " FROM inventory_items i LEFT JOIN spec_video_processors s ON i.id = s.item_id ";
        } else {
            $sql .= " FROM inventory_items i ";
        }
        
        $sql .= " LEFT JOIN item_models m ON i.model_id = m.id LEFT JOIN manufacturers mf ON m.manufacturer_id = mf.id WHERE i.item_type = ? AND (i.id = ? OR i.qr_code = ?)";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([$internalType, $id, $id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($result) {
            echo json_encode($result);
        } else {
            http_response_code(404);
            echo json_encode(["message" => "Component not found"]);
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

    private function createComponent($type) {
        try {
            $data = json_decode(file_get_contents("php://input"), true);
            $quantity = isset($data['quantity']) ? (int)$data['quantity'] : 1;
            if ($quantity < 1) $quantity = 1;
            
            $internalType = $this->getInternalType($type);
            
            $prefixStr = strtoupper(substr($type, 0, 3));
            if ($type === 'power_supplies') $prefixStr = 'PWR';
            if ($type === 'receiving_cards') $prefixStr = 'RCV';
            if ($type === 'video_processors') $prefixStr = 'VID';

            $datePrefix = date('Ymd');
            $prefix = $prefixStr . '-' . $datePrefix . '-';

            $stmt_seq = $this->db->prepare("SELECT serial_number FROM inventory_items WHERE item_type = ? AND serial_number LIKE ? ORDER BY id DESC LIMIT 1");
            $stmt_seq->execute([$internalType, $prefix . '%']);
            $lastRow = $stmt_seq->fetch(PDO::FETCH_ASSOC);
            
            $counter = 0;
            if ($lastRow && isset($lastRow['serial_number'])) {
                $parts = explode('-', $lastRow['serial_number']);
                $counter = (int)end($parts);
            }

            $this->db->beginTransaction();
            
            $manufacturerId = $this->getOrCreateManufacturer($data['manufacturer'] ?? null);
            $modelId = $this->getOrCreateModel($internalType, $manufacturerId, $data['model'] ?? null);

            $custom_properties = isset($data['custom_properties']) ? $data['custom_properties'] : [];
            
            // Extract type-specific fields
            $specSql = "";
            $specValues = [];
            
            if ($internalType === 'module') {
                $custom_properties['size'] = $data['size'] ?? null; // Store size in JSON
                $specSql = "INSERT INTO spec_modules (item_id, pixel_pitch) VALUES (?, ?)";
                $specValues = [ (float)($data['pixel_pitch'] ?? 1.0) ];
            } elseif ($internalType === 'power_supply') {
                $specSql = "INSERT INTO spec_power_supplies (item_id, voltage_in, current_a) VALUES (?, ?, ?)";
                $specValues = [ $data['voltage'] ?? null, (float)($data['current'] ?? 1.0) ];
            } elseif ($internalType === 'video_processor') {
                $specSql = "INSERT INTO spec_video_processors (item_id) VALUES (?)";
            }
            
            $metadata = empty($custom_properties) ? null : json_encode($custom_properties);

            $stmt = $this->db->prepare("INSERT INTO inventory_items (item_type, qr_code, serial_number, model_id, metadata) VALUES (?, ?, ?, ?, ?)");
            $stmt_spec = $specSql ? $this->db->prepare($specSql) : null;

            $firstId = null;
            for ($i = 1; $i <= $quantity; $i++) {
                $counter++;
                $serial_number = $prefix . str_pad($counter, 4, '0', STR_PAD_LEFT);
                $qr_code       = $serial_number;
                
                $stmt->execute([$internalType, $qr_code, $serial_number, $modelId, $metadata]);
                $itemId = $this->db->lastInsertId();
                if ($i === 1) $firstId = $itemId;
                
                if ($stmt_spec) {
                    $vals = array_merge([$itemId], $specValues);
                    $stmt_spec->execute($vals);
                }
                $this->logAction('create', $itemId, null, null, ucfirst($internalType) . ' created');
            }
            $this->db->commit();

            http_response_code(201);
            echo json_encode([
                "message" => "$quantity " . ucfirst(str_replace('_', ' ', $internalType)) . "(s) created",
                "id"      => $firstId
            ]);
        } catch(PDOException $e) {
            if ($this->db->inTransaction()) $this->db->rollBack();
            http_response_code(500);
            echo json_encode(["message" => "DB Error: " . $e->getMessage(), "code" => $e->getCode()]);
        } catch(Exception $e) {
            http_response_code(500);
            echo json_encode(["message" => "Server Error: " . $e->getMessage()]);
        }
    }

    private function updateComponentStatus($type, $id) {
        $data = json_decode(file_get_contents("php://input"), true);
        if (!isset($data['status'])) {
            http_response_code(400); echo json_encode(["message" => "Missing status"]); return;
        }
        $status = $data['status'];
        
        $stmtCheck = $this->db->prepare("SELECT status FROM inventory_items WHERE id = ?");
        $stmtCheck->execute([$id]);
        $oldStatus = $stmtCheck->fetchColumn();

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
