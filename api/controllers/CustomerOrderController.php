<?php
class CustomerOrderController {
    private $db;
    private $user;

    public function __construct($user) {
        require_once dirname(__DIR__) . '/config/database.php';
        $database = new Database();
        $this->db = $database->getConnection();
        $this->user = $user;
    }

    public function processRequest($method, $uriParts) {
        $id = $uriParts[1] ?? null;
        $subResource = $uriParts[2] ?? null;

        if ($method === 'GET' && $id === 'catalog' && !$subResource) {
            $this->getCatalog();
            return;
        }
        if ($method === 'GET' && !$id) {
            $this->getOrders();
            return;
        }
        if ($method === 'POST' && !$id) {
            $this->createOrder();
            return;
        }
        if ($method === 'PUT' && $id && $subResource === 'decision') {
            $validatedId = filter_var($id, FILTER_VALIDATE_INT);
            if ($validatedId === false || $validatedId < 1) {
                $this->respond(400, ["message" => "Invalid order ID"]);
                return;
            }
            $this->decideOrder($validatedId);
            return;
        }

        $this->respond(405, ["message" => "Method not allowed"]);
    }

    private function getCatalog() {
        if (!in_array($this->user['role'] ?? '', ['customer', 'admin'], true)) {
            $this->respond(403, ["message" => "Customer or admin access required"]);
            return;
        }

        $stmt = $this->db->query("
            SELECT m.id AS model_id, m.item_type, m.model_name, mf.name AS manufacturer,
                   COUNT(i.id) AS available_count
            FROM item_models m
            JOIN manufacturers mf ON mf.id = m.manufacturer_id
            JOIN inventory_items i ON i.model_id = m.id
            WHERE i.status = 'available'
              AND i.is_active = 1
              AND i.deleted_at IS NULL
            GROUP BY m.id, m.item_type, m.model_name, mf.name
            HAVING COUNT(i.id) > 0
            ORDER BY m.item_type, mf.name, m.model_name
        ");
        echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    private function getOrders() {
        $role = $this->user['role'] ?? '';
        if (!in_array($role, ['customer', 'admin'], true)) {
            $this->respond(403, ["message" => "Customer or admin access required"]);
            return;
        }

        $sql = "
            SELECT o.*, customer.full_name AS customer_name, customer.email AS customer_email,
                   reviewer.full_name AS reviewer_name
            FROM customer_order_requests o
            JOIN users customer ON customer.id = o.customer_id
            LEFT JOIN users reviewer ON reviewer.id = o.reviewed_by
        ";
        $params = [];
        if ($role === 'customer') {
            $sql .= " WHERE o.customer_id = ?";
            $params[] = $this->user['id'];
        }
        $sql .= " ORDER BY o.created_at DESC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
        if (!$orders) {
            echo json_encode([]);
            return;
        }

        $ids = array_column($orders, 'id');
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $itemsStmt = $this->db->prepare("
            SELECT oi.id AS order_item_id, oi.order_id, oi.model_id, oi.quantity,
                   m.item_type, m.model_name, mf.name AS manufacturer
            FROM customer_order_items oi
            JOIN item_models m ON m.id = oi.model_id
            JOIN manufacturers mf ON mf.id = m.manufacturer_id
            WHERE oi.order_id IN ($placeholders)
            ORDER BY m.item_type, m.model_name
        ");
        $itemsStmt->execute($ids);

        $itemsByOrder = [];
        foreach ($itemsStmt->fetchAll(PDO::FETCH_ASSOC) as $item) {
            $itemsByOrder[$item['order_id']][] = $item;
        }
        foreach ($orders as &$order) {
            $order['items'] = $itemsByOrder[$order['id']] ?? [];
        }
        unset($order);

        echo json_encode($orders);
    }

    private function createOrder() {
        if (($this->user['role'] ?? '') !== 'customer') {
            $this->respond(403, ["message" => "Only customers can submit purchase requests"]);
            return;
        }

        $data = json_decode(file_get_contents("php://input"), true);
        if (!is_array($data) || !isset($data['items']) || !is_array($data['items']) || count($data['items']) < 1 || count($data['items']) > 25) {
            $this->respond(400, ["message" => "Choose between 1 and 25 product models"]);
            return;
        }

        $items = [];
        foreach ($data['items'] as $item) {
            if (!is_array($item)) {
                $this->respond(400, ["message" => "Each order line must include a model and quantity"]);
                return;
            }
            $modelId = filter_var($item['model_id'] ?? null, FILTER_VALIDATE_INT);
            $quantity = filter_var($item['quantity'] ?? null, FILTER_VALIDATE_INT);
            if ($modelId === false || $modelId < 1 || $quantity === false || $quantity < 1 || $quantity > 10000) {
                $this->respond(400, ["message" => "Each order line must include a valid model and quantity"]);
                return;
            }
            $items[$modelId] = ($items[$modelId] ?? 0) + $quantity;
            if ($items[$modelId] > 10000) {
                $this->respond(400, ["message" => "Requested quantity for a product cannot exceed 10000"]);
                return;
            }
        }

        try {
            $this->db->beginTransaction();
            foreach ($items as $modelId => $quantity) {
                $stock = $this->db->prepare("
                    SELECT COUNT(*)
                    FROM inventory_items
                    WHERE model_id = ? AND status = 'available'
                      AND is_active = 1 AND deleted_at IS NULL
                ");
                $stock->execute([$modelId]);
                if ((int) $stock->fetchColumn() < $quantity) {
                    $this->db->rollBack();
                    $this->respond(409, ["message" => "Requested quantity exceeds available stock for model {$modelId}"]);
                    return;
                }
            }

            $orderStmt = $this->db->prepare("
                INSERT INTO customer_order_requests (customer_id, status)
                VALUES (?, 'pending')
            ");
            $orderStmt->execute([$this->user['id']]);
            $orderId = (int) $this->db->lastInsertId();
            $requestNumber = 'ORD-' . str_pad((string) $orderId, 8, '0', STR_PAD_LEFT);
            $numberStmt = $this->db->prepare("UPDATE customer_order_requests SET request_number = ? WHERE id = ?");
            $numberStmt->execute([$requestNumber, $orderId]);

            $itemStmt = $this->db->prepare("
                INSERT INTO customer_order_items (order_id, model_id, quantity)
                VALUES (?, ?, ?)
            ");
            foreach ($items as $modelId => $quantity) {
                $itemStmt->execute([$orderId, $modelId, $quantity]);
            }

            $this->db->commit();
            $this->respond(201, [
                "message" => "Purchase request submitted for admin review",
                "id" => $orderId,
                "request_number" => $requestNumber
            ]);
        } catch (PDOException $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            error_log($e->getMessage());
            $this->respond(500, ["message" => "Unable to submit purchase request"]);
        }
    }

    private function decideOrder($id) {
        if (($this->user['role'] ?? '') !== 'admin') {
            $this->respond(403, ["message" => "Admin access required"]);
            return;
        }

        $data = json_decode(file_get_contents("php://input"), true);
        $decision = is_array($data) ? ($data['decision'] ?? '') : '';
        $adminNotes = is_array($data) && isset($data['admin_notes']) && is_string($data['admin_notes'])
            ? trim($data['admin_notes'])
            : '';
        if (!in_array($decision, ['accepted', 'declined'], true) || strlen($adminNotes) > 4000) {
            $this->respond(400, ["message" => "Provide a valid decision and notes no longer than 4000 characters"]);
            return;
        }

        try {
            $this->db->beginTransaction();
            $orderStmt = $this->db->prepare("
                SELECT id, request_number, status
                FROM customer_order_requests
                WHERE id = ?
                FOR UPDATE
            ");
            $orderStmt->execute([$id]);
            $order = $orderStmt->fetch(PDO::FETCH_ASSOC);
            if (!$order) {
                $this->db->rollBack();
                $this->respond(404, ["message" => "Purchase request not found"]);
                return;
            }
            if ($order['status'] !== 'pending') {
                $this->db->rollBack();
                $this->respond(409, ["message" => "This purchase request has already been reviewed"]);
                return;
            }

            if ($decision === 'accepted') {
                $linesStmt = $this->db->prepare("
                    SELECT oi.id, oi.model_id, oi.quantity, m.model_name
                    FROM customer_order_items oi
                    JOIN item_models m ON m.id = oi.model_id
                    WHERE oi.order_id = ?
                    ORDER BY oi.model_id
                ");
                $linesStmt->execute([$id]);
                $lines = $linesStmt->fetchAll(PDO::FETCH_ASSOC);
                $allocations = [];
                foreach ($lines as $line) {
                    $quantity = (int) $line['quantity'];
                    $stockStmt = $this->db->prepare("
                        SELECT id, current_location_id
                        FROM inventory_items
                        WHERE model_id = ? AND status = 'available'
                          AND is_active = 1 AND deleted_at IS NULL
                        ORDER BY id
                        LIMIT {$quantity}
                        FOR UPDATE
                    ");
                    $stockStmt->execute([$line['model_id']]);
                    $stock = $stockStmt->fetchAll(PDO::FETCH_ASSOC);
                    if (count($stock) < $quantity) {
                        $this->db->rollBack();
                        $this->respond(409, [
                            "message" => "Not enough available stock for {$line['model_name']}; the request remains pending"
                        ]);
                        return;
                    }
                    $allocations[] = ['line' => $line, 'stock' => $stock];
                }

                $updateItem = $this->db->prepare("
                    UPDATE inventory_items
                    SET status = 'sold', current_location_id = NULL, updated_by = ?
                    WHERE id = ? AND status = 'available'
                ");
                $allocationStmt = $this->db->prepare("
                    INSERT INTO customer_order_allocations (order_item_id, inventory_item_id)
                    VALUES (?, ?)
                ");
                $movementStmt = $this->db->prepare("
                    INSERT INTO stock_movements
                        (item_id, movement_type, from_location_id, reference_type, reference_id, notes, created_by)
                    VALUES (?, 'sale', ?, 'customer_order', ?, ?, ?)
                ");
                $logStmt = $this->db->prepare("
                    INSERT INTO inventory_logs
                        (item_id, action, from_status, to_status, notes, performed_by)
                    VALUES (?, 'sale', 'available', 'sold', ?, ?)
                ");

                foreach ($allocations as $allocation) {
                    foreach ($allocation['stock'] as $stockItem) {
                        $updateItem->execute([$this->user['id'], $stockItem['id']]);
                        if ($updateItem->rowCount() !== 1) {
                            throw new RuntimeException("Inventory item {$stockItem['id']} was no longer available");
                        }
                        $allocationStmt->execute([$allocation['line']['id'], $stockItem['id']]);
                        $notes = "Sold on {$order['request_number']}";
                        $movementStmt->execute([
                            $stockItem['id'],
                            $stockItem['current_location_id'],
                            $id,
                            $notes,
                            $this->user['id']
                        ]);
                        $logStmt->execute([$stockItem['id'], $notes, $this->user['id']]);
                    }
                }
            }

            $reviewStmt = $this->db->prepare("
                UPDATE customer_order_requests
                SET status = ?, admin_notes = ?, reviewed_by = ?, reviewed_at = CURRENT_TIMESTAMP
                WHERE id = ?
            ");
            $reviewStmt->execute([$decision, $adminNotes !== '' ? $adminNotes : null, $this->user['id'], $id]);

            $this->db->commit();
            $this->respond(200, ["message" => "Purchase request {$decision}", "id" => $id, "status" => $decision]);
        } catch (PDOException $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            error_log($e->getMessage());
            $this->respond(500, ["message" => "Unable to review purchase request"]);
        } catch (RuntimeException $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            error_log($e->getMessage());
            $this->respond(409, ["message" => "Inventory changed during review; the request remains pending"]);
        }
    }

    private function respond($status, $payload) {
        http_response_code($status);
        echo json_encode($payload);
    }
}
