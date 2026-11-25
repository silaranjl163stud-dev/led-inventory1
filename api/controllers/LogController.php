<?php
class LogController {
    private $db;

    public function __construct() {
        require_once dirname(__DIR__) . '/config/database.php';
        $database = new Database();
        $this->db = $database->getConnection();
    }

    public function processRequest($method, $uriParts) {
        if ($method === 'GET') {
            $this->getLogs();
        } else {
            http_response_code(405);
            echo json_encode(["message" => "Method not allowed"]);
        }
    }

    private function getLogs() {
        $itemId = $_GET['item_id'] ?? null;

        if (!$itemId) {
            http_response_code(400);
            echo json_encode(["message" => "Missing item_id parameter"]);
            return;
        }

        $stmt = $this->db->prepare("
            SELECT action, notes, action_at AS created_at, from_status, to_status 
            FROM inventory_logs 
            WHERE item_id = ? 
            ORDER BY action_at DESC
        ");
        $stmt->execute([$itemId]);
        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode($result);
    }
}
?>
