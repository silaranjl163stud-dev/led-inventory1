<?php
$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

$apiPathPosition = strpos($uri, '/api');
if ($apiPathPosition !== false) {
    $uri = substr($uri, $apiPathPosition + 4);
}

// Clean up URI and split
$uri = trim($uri, '/');
$uriParts = explode('/', $uri);
$resource = $uriParts[0] ?? '';

switch ($resource) {
    case 'auth':
        require_once __DIR__ . '/controllers/AuthController.php';
        $controller = new AuthController();
        $controller->processRequest($_SERVER['REQUEST_METHOD'], $uriParts);
        break;
    case 'users':
        require_once __DIR__ . '/controllers/UserController.php';
        $controller = new UserController();
        $controller->processRequest($_SERVER['REQUEST_METHOD'], $uriParts);
        break;
    case 'cabinets':
        require_once __DIR__ . '/config/auth.php';
        authenticateRequest();
        require_once __DIR__ . '/controllers/CabinetController.php';
        $controller = new CabinetController();
        $controller->processRequest($_SERVER['REQUEST_METHOD'], $uriParts);
        break;
    case 'components':
        require_once __DIR__ . '/config/auth.php';
        authenticateRequest();
        require_once __DIR__ . '/controllers/ComponentController.php';
        $controller = new ComponentController();
        $controller->processRequest($_SERVER['REQUEST_METHOD'], $uriParts);
        break;
    case 'scan':
        require_once __DIR__ . '/config/auth.php';
        authenticateRequest();
        require_once __DIR__ . '/controllers/ScanController.php';
        $controller = new ScanController();
        $controller->processRequest($_SERVER['REQUEST_METHOD'], $uriParts);
        break;
    case 'migrate':
        // Schema is managed via database.sql — this endpoint now just verifies key tables exist
        require_once __DIR__ . '/config/database.php';
        $db = new Database();
        $conn = $db->getConnection();
        $tables = ['inventory_items', 'asset_hierarchy', 'stock_movements', 'qr_scan_logs', 'custom_fields', 'maintenance_schedules', 'customer_order_requests', 'customer_order_items', 'customer_order_allocations'];
        $results = [];
        foreach ($tables as $tbl) {
            try {
                $stmt = $conn->query("SHOW TABLES LIKE '$tbl'");
                $results[$tbl] = $stmt->rowCount() > 0 ? 'EXISTS' : 'MISSING';
            } catch (PDOException $e) {
                $results[$tbl] = 'ERROR: ' . $e->getMessage();
            }
        }
        $allOk = !in_array('MISSING', $results);
        if ($allOk) {
            echo json_encode(["message" => "All schema tables present.", "tables" => $results]);
        } else {
            http_response_code(500);
            echo json_encode(["message" => "Some tables are missing. Re-run database.sql.", "tables" => $results]);
        }
        break;
    case 'schema-check':
        require_once __DIR__ . '/config/database.php';
        $db = new Database();
        $conn = $db->getConnection();
        $results = [];
        $checkTables = ['users','inventory_items','item_models','manufacturers','locations','spec_modules','spec_cabinets','spec_power_supplies','spec_video_processors','asset_hierarchy','stock_movements','inventory_logs','qr_scan_logs','deployments','deployment_items','repairs','repair_actions','maintenance_schedules','custom_fields','warranties','suppliers','purchase_orders','status_transitions','cabinet_templates','model_compatibility','customer_order_requests','customer_order_items','customer_order_allocations'];
        foreach ($checkTables as $tbl) {
            try {
                $stmt = $conn->query("SHOW TABLES LIKE '$tbl'");
                $results[$tbl] = $stmt->rowCount() > 0 ? 'EXISTS' : 'MISSING';
            } catch (PDOException $e) {
                $results[$tbl] = 'ERROR: ' . $e->getMessage();
            }
        }
        echo json_encode($results, JSON_PRETTY_PRINT);
        break;
    case 'custom-fields':
        require_once __DIR__ . '/config/auth.php';
        // Write operations (POST, DELETE) are admin-only; GET is open to any authenticated user
        if (in_array($_SERVER['REQUEST_METHOD'], ['POST', 'DELETE'])) {
            authenticateRequest(true); // admin required
        } else {
            authenticateRequest();
        }
        require_once __DIR__ . '/controllers/CustomFieldController.php';
        $controller = new CustomFieldController();
        $controller->processRequest($_SERVER['REQUEST_METHOD'], $uriParts);
        break;
    case 'stats':
        require_once __DIR__ . '/config/auth.php';
        authenticateRequest();
        require_once __DIR__ . '/controllers/StatsController.php';
        $controller = new StatsController();
        $controller->processRequest($_SERVER['REQUEST_METHOD'], $uriParts);
        break;
    case 'logs':
        require_once __DIR__ . '/config/auth.php';
        authenticateRequest();
        require_once __DIR__ . '/controllers/LogController.php';
        $controller = new LogController();
        $controller->processRequest($_SERVER['REQUEST_METHOD'], $uriParts);
        break;
    case 'deployments':
        require_once __DIR__ . '/config/auth.php';
        authenticateRequest();
        require_once __DIR__ . '/controllers/DeploymentController.php';
        $controller = new DeploymentController();
        $controller->processRequest($_SERVER['REQUEST_METHOD'], $uriParts);
        break;
    case 'repairs':
        require_once __DIR__ . '/config/auth.php';
        $user = authenticateRequest(false, true);
        require_once __DIR__ . '/controllers/RepairController.php';
        $controller = new RepairController($user);
        $controller->processRequest($_SERVER['REQUEST_METHOD'], $uriParts);
        break;
    case 'orders':
        require_once __DIR__ . '/config/auth.php';
        $user = authenticateRequest(false, true);
        require_once __DIR__ . '/controllers/CustomerOrderController.php';
        $controller = new CustomerOrderController($user);
        $controller->processRequest($_SERVER['REQUEST_METHOD'], $uriParts);
        break;
    default:
        http_response_code(404);
        echo json_encode(["message" => "API endpoint not found.", "resource" => $resource, "uri" => $uri]);
        break;
}
?>
