<?php
class DeploymentController {
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

        if ($method === 'GET' && !$id) {
            $this->getDeployments();
            return;
        }

        if ($method === 'GET' && $id && !$subResource) {
            $this->getDeployment($id);
            return;
        }

        if ($method === 'POST' && !$id) {
            $this->createDeployment();
            return;
        }

        if ($method === 'PUT' && $id && $subResource === 'status') {
            $this->updateStatus($id);
            return;
        }

        if ($method === 'POST' && $id && $subResource === 'items') {
            $this->assignItem($id);
            return;
        }

        if ($method === 'DELETE' && $id && $subResource === 'items' && $subId) {
            $this->removeItem($id, $subId);
            return;
        }

        http_response_code(405);
        echo json_encode(["message" => "Method not allowed"]);
    }

    private function getDeployments() {
        $stmt = $this->db->query("
            SELECT d.*, l.name AS location 
            FROM deployments d 
            LEFT JOIN locations l ON d.location_id = l.id 
            ORDER BY d.created_at DESC
        ");
        echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    private function getDeployment($id) {
        $stmt = $this->db->prepare("
            SELECT d.*, l.name AS location 
            FROM deployments d 
            LEFT JOIN locations l ON d.location_id = l.id 
            WHERE d.id = ?
        ");
        $stmt->execute([$id]);
        $deployment = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($deployment) {
            $stmtItems = $this->db->prepare("
                SELECT di.item_id, i.item_type, i.qr_code, di.screen_name, di.pos_x, di.pos_y
                FROM deployment_items di
                JOIN inventory_items i ON di.item_id = i.id
                WHERE di.deployment_id = ?
            ");
            $stmtItems->execute([$id]);
            $deployment['items'] = $stmtItems->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode($deployment);
        } else {
            http_response_code(404);
            echo json_encode(["message" => "Deployment not found"]);
        }
    }

    private function getOrCreateLocation($name) {
        if (empty($name)) return null;
        $stmt = $this->db->prepare("SELECT id FROM locations WHERE name = ? AND type = 'event_site'");
        $stmt->execute([$name]);
        $id = $stmt->fetchColumn();
        if ($id) return $id;

        $stmt = $this->db->prepare("INSERT INTO locations (name, type) VALUES (?, 'event_site')");
        $stmt->execute([$name]);
        return $this->db->lastInsertId();
    }

    private function createDeployment() {
        $data = json_decode(file_get_contents("php://input"), true);
        if (empty($data['name']) || empty($data['location'])) {
            http_response_code(400); echo json_encode(["message" => "Name and location required"]); return;
        }

        $locId = $this->getOrCreateLocation($data['location']);

        $stmt = $this->db->prepare("INSERT INTO deployments (name, location_id, start_date, end_date) VALUES (?, ?, ?, ?)");
        $stmt->execute([$data['name'], $locId, $data['start_date'] ?? null, $data['end_date'] ?? null]);
        
        echo json_encode(["message" => "Deployment created", "id" => $this->db->lastInsertId()]);
    }

    private function updateStatus($id) {
        $data = json_decode(file_get_contents("php://input"), true);
        $status = $data['status'] ?? null;
        if (!$status) {
            http_response_code(400); echo json_encode(["message" => "Status required"]); return;
        }

        $stmt = $this->db->prepare("UPDATE deployments SET status = ? WHERE id = ?");
        $stmt->execute([$status, $id]);
        echo json_encode(["message" => "Status updated"]);
    }

    private function assignItem($id) {
        $data = json_decode(file_get_contents("php://input"), true);
        $itemId = $data['item_id'] ?? null;

        if (!$itemId) {
            http_response_code(400); echo json_encode(["message" => "Missing item ID"]); return;
        }

        try {
            $this->db->beginTransaction();

            $stmtCheck = $this->db->prepare("SELECT id FROM deployment_items WHERE deployment_id = ? AND item_id = ?");
            $stmtCheck->execute([$id, $itemId]);
            if ($stmtCheck->fetch()) {
                throw new Exception("Item already assigned to this deployment");
            }

            $stmt = $this->db->prepare("INSERT INTO deployment_items (deployment_id, item_id) VALUES (?, ?)");
            $stmt->execute([$id, $itemId]);

            $stmtStatus = $this->db->prepare("UPDATE inventory_items SET status = 'deployed' WHERE id = ?");
            $stmtStatus->execute([$itemId]);

            $this->logAction($itemId, 'deploy', "Assigned to deployment ID $id");

            $this->db->commit();
            echo json_encode(["message" => "Item assigned"]);
        } catch(Exception $e) {
            if ($this->db->inTransaction()) $this->db->rollBack();
            http_response_code(500); echo json_encode(["message" => $e->getMessage()]);
        }
    }

    private function removeItem($id, $itemId) {
        try {
            $this->db->beginTransaction();

            $stmt = $this->db->prepare("DELETE FROM deployment_items WHERE deployment_id = ? AND item_id = ?");
            $stmt->execute([$id, $itemId]);

            $stmtStatus = $this->db->prepare("UPDATE inventory_items SET status = 'available' WHERE id = ?");
            $stmtStatus->execute([$itemId]);

            $this->logAction($itemId, 'return', "Removed from deployment ID $id");

            $this->db->commit();
            echo json_encode(["message" => "Item removed"]);
        } catch(Exception $e) {
            if ($this->db->inTransaction()) $this->db->rollBack();
            http_response_code(500); echo json_encode(["message" => $e->getMessage()]);
        }
    }

    private function logAction($itemId, $action, $notes) {
        try {
            $stmt = $this->db->prepare("INSERT INTO inventory_logs (item_id, action, notes) VALUES (?, ?, ?)");
            $stmt->execute([$itemId, $action, $notes]);
        } catch(Exception $e) {}
    }
}
?>
