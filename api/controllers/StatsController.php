<?php
// api/controllers/StatsController.php

class StatsController {
    private $db;

    public function __construct() {
        require_once dirname(__DIR__) . '/config/database.php';
        $database = new Database();
        $this->db = $database->getConnection();
    }

    public function processRequest($method, $uriParts) {
        // Only allow GET requests for stats
        if ($method !== 'GET') {
            http_response_code(405);
            echo json_encode(["message" => "Method not allowed"]);
            return;
        }

        try {
            // Count total cabinets
            $stmtCabinets = $this->db->query("SELECT COUNT(*) as count FROM inventory_items WHERE item_type = 'cabinet'");
            $cabinetsCount = $stmtCabinets->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;

            // Count total modules
            $stmtModules = $this->db->query("SELECT COUNT(*) as count FROM inventory_items WHERE item_type = 'module'");
            $modulesCount = $stmtModules->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;

            // Count tickets awaiting review or currently being repaired
            $stmtRepairs = $this->db->query("SELECT COUNT(*) as count FROM repairs WHERE status IN ('pending', 'accepted', 'open', 'in_progress')");
            $repairsCount = $stmtRepairs->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;

            echo json_encode([
                "cabinets" => (int)$cabinetsCount,
                "modules" => (int)$modulesCount,
                "repairs_pending" => (int)$repairsCount
            ]);
        } catch (PDOException $e) {
            http_response_code(500);
            echo json_encode(["message" => "Error fetching statistics: " . $e->getMessage()]);
        }
    }
}
?>
