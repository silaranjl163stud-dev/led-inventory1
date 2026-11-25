<?php
class ScanController {
    private $db;

    public function __construct() {
        require_once dirname(__DIR__) . '/config/database.php';
        $database = new Database();
        $this->db = $database->getConnection();
    }

    public function processRequest($method, $uriParts) {
        if ($method !== 'POST') {
            http_response_code(405);
            echo json_encode(["message" => "Only POST allowed for scan"]);
            return;
        }

        $data = json_decode(file_get_contents("php://input"), true);
        $qr_code = $data['qr_code'] ?? null;

        if (!$qr_code) {
            http_response_code(400);
            echo json_encode(["message" => "qr_code is required"]);
            return;
        }

        $stmt = $this->db->prepare("
            SELECT i.id, i.item_type, i.qr_code, i.serial_number, i.status,
                   m.model_name AS model, mf.name AS manufacturer
            FROM inventory_items i
            LEFT JOIN item_models m ON i.model_id = m.id
            LEFT JOIN manufacturers mf ON m.manufacturer_id = mf.id
            WHERE i.qr_code = ?
        ");
        $stmt->execute([$qr_code]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($result) {
            $type = $result['item_type'];
            
            try {
                $logStmt = $this->db->prepare("INSERT INTO qr_scan_logs (item_id, action) VALUES (?, 'Item Scanned via App')");
                $logStmt->execute([$result['id']]);
            } catch (PDOException $e) {
                // Ignore log error
            }

            echo json_encode([
                "found" => true,
                "item_type" => $type,
                "item" => $result
            ]);
            return;
        }

        http_response_code(404);
        echo json_encode(["found" => false, "message" => "Item not found in any inventory database"]);
    }
}
?>
