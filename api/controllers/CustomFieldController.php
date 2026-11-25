<?php
class CustomFieldController {
    private $db;

    public function __construct() {
        require_once dirname(__DIR__) . '/config/database.php';
        $database = new Database();
        $this->db = $database->getConnection();
    }

    public function processRequest($method, $uriParts) {
        $idOrType = $uriParts[1] ?? null;

        if ($method === 'GET') {
            if ($idOrType) {
                // Get fields for a specific item type
                $this->getFieldsByType($idOrType);
            } else {
                // Get all fields
                $this->getAllFields();
            }
        } elseif ($method === 'POST') {
            $this->createField();
        } elseif ($method === 'DELETE' && $idOrType) {
            $this->deleteField($idOrType); // here $idOrType is the ID of the custom field
        } else {
            http_response_code(405);
            echo json_encode(["message" => "Method Not Allowed"]);
        }
    }

    private function getFieldsByType($type) {
        $stmt = $this->db->prepare("SELECT * FROM custom_fields WHERE item_type = ? ORDER BY created_at ASC");
        $stmt->execute([$type]);
        $fields = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode($fields);
    }

    private function getAllFields() {
        $stmt = $this->db->query("SELECT * FROM custom_fields ORDER BY item_type, created_at ASC");
        $fields = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode($fields);
    }

    private function createField() {
        $data = json_decode(file_get_contents("php://input"), true);
        
        $item_type = $data['item_type'] ?? null;
        $field_name = $data['field_name'] ?? null;
        $field_type = $data['field_type'] ?? 'text';
        $is_required = isset($data['is_required']) ? (int)$data['is_required'] : 0;

        if (!$item_type || !$field_name) {
            http_response_code(400);
            echo json_encode(["message" => "Missing required parameters (item_type, field_name)"]);
            return;
        }

        try {
            $stmt = $this->db->prepare("INSERT INTO custom_fields (item_type, field_name, field_type, is_required) VALUES (?, ?, ?, ?)");
            $stmt->execute([$item_type, $field_name, $field_type, $is_required]);
            
            http_response_code(201);
            echo json_encode(["message" => "Custom field created", "id" => $this->db->lastInsertId()]);
        } catch(PDOException $e) {
            http_response_code(500);
            echo json_encode(["message" => "DB Error: " . $e->getMessage()]);
        }
    }

    private function deleteField($id) {
        try {
            $stmt = $this->db->prepare("DELETE FROM custom_fields WHERE id = ?");
            $stmt->execute([$id]);
            echo json_encode(["message" => "Custom field deleted"]);
        } catch(PDOException $e) {
            http_response_code(500);
            echo json_encode(["message" => "DB Error: " . $e->getMessage()]);
        }
    }
}
?>
