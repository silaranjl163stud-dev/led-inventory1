<?php
class RepairController {
    private $db;
    private $user;

    public function __construct($user) {
        require_once dirname(__DIR__) . '/config/database.php';
        $database = new Database();
        $this->db = $database->getConnection();
        $this->user = $user;
    }

    private function canReview() {
        return in_array($this->user['role'] ?? '', ['admin', 'staff'], true);
    }

    public function processRequest($method, $uriParts) {
        $id = $uriParts[1] ?? null;
        $subResource = $uriParts[2] ?? null;
        if ($id !== null) {
            $validatedId = filter_var($id, FILTER_VALIDATE_INT);
            if ($validatedId === false || $validatedId < 1) {
                http_response_code(400);
                echo json_encode(["message" => "Invalid repair ticket ID"]);
                return;
            }
            $id = $validatedId;
        }

        if ($method === 'GET' && !$id) {
            $this->getRepairs();
            return;
        }

        if ($method === 'GET' && $id && !$subResource) {
            $this->getRepair($id);
            return;
        }

        if ($method === 'POST' && !$id) {
            $this->createRepair();
            return;
        }

        if ($method === 'PUT' && $id && $subResource === 'status') {
            $this->updateStatus($id);
            return;
        }

        if ($method === 'PUT' && $id && $subResource === 'decision') {
            $this->reviewTicket($id);
            return;
        }

        if ($method === 'POST' && $id && $subResource === 'actions') {
            $this->addAction($id);
            return;
        }

        http_response_code(405);
        echo json_encode(["message" => "Method not allowed"]);
    }

    private function getRepairs() {
        $sql = "
            SELECT r.*, i.item_type, i.qr_code,
                   requester.username AS requester,
                   reviewer.username AS reviewer
            FROM repairs r
            JOIN inventory_items i ON r.item_id = i.id
            LEFT JOIN users requester ON requester.id = r.created_by
            LEFT JOIN users reviewer ON reviewer.id = r.reviewed_by
        ";

        if (!$this->canReview()) {
            $sql .= " WHERE r.created_by = ?";
            $stmt = $this->db->prepare($sql . "
                ORDER BY FIELD(r.status, 'pending', 'accepted', 'in_progress', 'open', 'declined', 'resolved', 'cancelled'), r.created_at DESC
            ");
            $stmt->execute([$this->user['id']]);
        } else {
            $stmt = $this->db->query($sql . "
                ORDER BY FIELD(r.status, 'pending', 'accepted', 'in_progress', 'open', 'declined', 'resolved', 'cancelled'), r.created_at DESC
            ");
        }

        echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    private function getRepair($id) {
        $sql = "
            SELECT r.*, i.item_type, i.qr_code,
                   requester.username AS requester,
                   reviewer.username AS reviewer
            FROM repairs r
            JOIN inventory_items i ON r.item_id = i.id
            LEFT JOIN users requester ON requester.id = r.created_by
            LEFT JOIN users reviewer ON reviewer.id = r.reviewed_by
            WHERE r.id = ?
        ";
        if (!$this->canReview()) {
            $sql .= " AND r.created_by = ?";
        }

        $stmt = $this->db->prepare($sql);
        $params = [$id];
        if (!$this->canReview()) {
            $params[] = $this->user['id'];
        }
        $stmt->execute($params);
        $repair = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($repair) {
            $stmtActions = $this->db->prepare("SELECT * FROM repair_actions WHERE repair_id = ? ORDER BY created_at ASC");
            $stmtActions->execute([$id]);
            $repair['actions'] = $stmtActions->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode($repair);
        } else {
            http_response_code(404);
            echo json_encode(["message" => "Repair not found"]);
        }
    }

    private function createRepair() {
        $data = json_decode(file_get_contents("php://input"), true);
        if (
            !is_array($data) ||
            !isset($data['reason'], $data['description']) ||
            !is_string($data['reason']) ||
            !is_string($data['description'])
        ) {
            http_response_code(400);
            echo json_encode(["message" => "Reason and description are required"]);
            return;
        }

        $reason = trim($data['reason']);
        $description = trim($data['description']);
        $itemQr = isset($data['item_qr']) && is_string($data['item_qr']) ? trim($data['item_qr']) : '';
        $itemId = filter_var($data['item_id'] ?? null, FILTER_VALIDATE_INT);

        if (
            $reason === '' || strlen($reason) > 2000 ||
            $description === '' || strlen($description) > 10000 ||
            ($itemQr === '' && (!$itemId || $itemId < 1))
        ) {
            http_response_code(400);
            echo json_encode(["message" => "Provide a reason, description, and valid item QR code"]);
            return;
        }

        try {
            $this->db->beginTransaction();

            if ($itemQr !== '') {
                $stmtItem = $this->db->prepare("
                    SELECT id, status FROM inventory_items
                    WHERE qr_code = ? AND is_active = 1 AND deleted_at IS NULL
                    FOR UPDATE
                ");
                $stmtItem->execute([$itemQr]);
            } else {
                $stmtItem = $this->db->prepare("
                    SELECT id, status FROM inventory_items
                    WHERE id = ? AND is_active = 1 AND deleted_at IS NULL
                    FOR UPDATE
                ");
                $stmtItem->execute([$itemId]);
            }
            $item = $stmtItem->fetch(PDO::FETCH_ASSOC);

            if (!$item) {
                $this->db->rollBack();
                http_response_code(404);
                echo json_encode(["message" => "Inventory item not found"]);
                return;
            }
            if (in_array($item['status'], ['retired', 'under_repair'], true)) {
                $this->db->rollBack();
                http_response_code(409);
                echo json_encode(["message" => "This item cannot receive another repair ticket"]);
                return;
            }

            $stmt = $this->db->prepare("
                INSERT INTO repairs (ticket, reason, item_id, issue_description, status, created_by)
                VALUES ('Pending', ?, ?, ?, 'pending', ?)
            ");
            $stmt->execute([$reason, $item['id'], $description, $this->user['id']]);
            $repairId = $this->db->lastInsertId();
            $ticketNumber = 'RPR-' . str_pad($repairId, 8, '0', STR_PAD_LEFT);
            $stmtTicket = $this->db->prepare("UPDATE repairs SET ticket = ? WHERE id = ?");
            $stmtTicket->execute([$ticketNumber, $repairId]);

            $this->logAction(
                $item['id'],
                'repair',
                "Repair ticket #{$repairId} submitted",
                null,
                null,
                $this->user['id']
            );

            $this->db->commit();
            http_response_code(201);
            echo json_encode(["message" => "Repair ticket submitted for review", "id" => $repairId, "ticket" => $ticketNumber]);
        } catch (PDOException $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            error_log($e->getMessage());
            http_response_code(500);
            echo json_encode(["message" => "Unable to submit repair ticket"]);
        }
    }

    private function reviewTicket($id) {
        if (!$this->canReview()) {
            http_response_code(403);
            echo json_encode(["message" => "Staff or admin access required"]);
            return;
        }

        $data = json_decode(file_get_contents("php://input"), true);
        $decision = is_array($data) ? ($data['decision'] ?? null) : null;
        if (!in_array($decision, ['accepted', 'declined'], true)) {
            http_response_code(400);
            echo json_encode(["message" => "Decision must be accepted or declined"]);
            return;
        }

        try {
            $this->db->beginTransaction();
            $stmt = $this->db->prepare("
                SELECT r.id, r.item_id, r.status, i.status AS item_status, i.is_active
                FROM repairs r
                JOIN inventory_items i ON i.id = r.item_id
                WHERE r.id = ?
                FOR UPDATE
            ");
            $stmt->execute([$id]);
            $repair = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$repair) {
                $this->db->rollBack();
                http_response_code(404);
                echo json_encode(["message" => "Repair ticket not found"]);
                return;
            }
            if ($repair['status'] !== 'pending') {
                $this->db->rollBack();
                http_response_code(409);
                echo json_encode(["message" => "Only pending tickets can be reviewed"]);
                return;
            }
            if ($decision === 'accepted' && (
                !$repair['is_active'] ||
                in_array($repair['item_status'], ['retired', 'under_repair'], true)
            )) {
                $this->db->rollBack();
                http_response_code(409);
                echo json_encode(["message" => "This item is no longer available for repair review"]);
                return;
            }

            $stmt = $this->db->prepare("
                UPDATE repairs
                SET status = ?, reviewed_by = ?, reviewed_at = CURRENT_TIMESTAMP,
                    before_status = CASE WHEN ? = 'accepted' THEN ? ELSE before_status END
                WHERE id = ?
            ");
            $stmt->execute([
                $decision,
                $this->user['id'],
                $decision,
                $repair['item_status'],
                $id
            ]);

            if ($decision === 'accepted') {
                $stmt = $this->db->prepare("
                    UPDATE inventory_items SET status = 'under_repair', updated_by = ? WHERE id = ?
                ");
                $stmt->execute([$this->user['id'], $repair['item_id']]);
                $this->logAction(
                    $repair['item_id'],
                    'status_change',
                    "Repair ticket #{$id} accepted",
                    $repair['item_status'],
                    'under_repair',
                    $this->user['id']
                );
            } else {
                $this->logAction(
                    $repair['item_id'],
                    'repair',
                    "Repair ticket #{$id} declined",
                    null,
                    null,
                    $this->user['id']
                );
            }

            $this->db->commit();
            echo json_encode(["message" => "Repair ticket {$decision}"]);
        } catch (PDOException $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            error_log($e->getMessage());
            http_response_code(500);
            echo json_encode(["message" => "Unable to review repair ticket"]);
        }
    }

    private function updateStatus($id) {
        if (!$this->canReview()) {
            http_response_code(403);
            echo json_encode(["message" => "Staff or admin access required"]);
            return;
        }

        $data = json_decode(file_get_contents("php://input"), true);
        $status = $data['status'] ?? null;
        if (!is_string($status)) {
            http_response_code(400);
            echo json_encode(["message" => "Status required"]);
            return;
        }

        try {
            $this->db->beginTransaction();

            $stmt = $this->db->prepare("SELECT item_id, status, before_status FROM repairs WHERE id = ? FOR UPDATE");
            $stmt->execute([$id]);
            $repair = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$repair) {
                $this->db->rollBack();
                http_response_code(404);
                echo json_encode(["message" => "Repair ticket not found"]);
                return;
            }

            $allowedTransitions = [
                'accepted' => ['in_progress', 'resolved', 'cancelled'],
                'open' => ['in_progress', 'resolved', 'cancelled'],
                'in_progress' => ['resolved', 'cancelled']
            ];
            if (!in_array($status, $allowedTransitions[$repair['status']] ?? [], true)) {
                $this->db->rollBack();
                http_response_code(409);
                echo json_encode(["message" => "Invalid repair status transition"]);
                return;
            }

            if ($status === 'resolved') {
                $stmtUpdate = $this->db->prepare("
                    UPDATE repairs SET status = ?, resolved_at = CURRENT_TIMESTAMP, after_status = 'available'
                    WHERE id = ?
                ");
                $stmtUpdate->execute([$status, $id]);
                $stmtStatus = $this->db->prepare("
                    UPDATE inventory_items SET status = 'available', updated_by = ? WHERE id = ?
                ");
                $stmtStatus->execute([$this->user['id'], $repair['item_id']]);
                $this->logAction(
                    $repair['item_id'],
                    'status_change',
                    "Repair ticket #{$id} resolved",
                    'under_repair',
                    'available',
                    $this->user['id']
                );
            } elseif ($status === 'cancelled') {
                $restoreStatus = $repair['before_status'] ?: 'available';
                $stmtUpdate = $this->db->prepare("
                    UPDATE repairs SET status = ?, after_status = ? WHERE id = ?
                ");
                $stmtUpdate->execute([$status, $restoreStatus, $id]);
                $stmtStatus = $this->db->prepare("
                    UPDATE inventory_items SET status = ?, updated_by = ? WHERE id = ?
                ");
                $stmtStatus->execute([$restoreStatus, $this->user['id'], $repair['item_id']]);
                $this->logAction(
                    $repair['item_id'],
                    'status_change',
                    "Repair ticket #{$id} cancelled",
                    'under_repair',
                    $restoreStatus,
                    $this->user['id']
                );
            } else {
                $stmtUpdate = $this->db->prepare("UPDATE repairs SET status = ? WHERE id = ?");
                $stmtUpdate->execute([$status, $id]);
            }

            $this->db->commit();
            echo json_encode(["message" => "Status updated"]);
        } catch (PDOException $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            error_log($e->getMessage());
            http_response_code(500);
            echo json_encode(["message" => "Unable to update repair status"]);
        }
    }

    private function addAction($id) {
        if (!$this->canReview()) {
            http_response_code(403);
            echo json_encode(["message" => "Staff or admin access required"]);
            return;
        }

        $data = json_decode(file_get_contents("php://input"), true);
        if (
            !is_array($data) ||
            !isset($data['action']) ||
            !is_string($data['action']) ||
            trim($data['action']) === '' ||
            strlen(trim($data['action'])) > 100
        ) {
            http_response_code(400);
            echo json_encode(["message" => "Action must be between 1 and 100 characters"]);
            return;
        }

        $stmt = $this->db->prepare("SELECT status FROM repairs WHERE id = ?");
        $stmt->execute([$id]);
        $repair = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$repair) {
            http_response_code(404);
            echo json_encode(["message" => "Repair ticket not found"]);
            return;
        }
        if (!in_array($repair['status'], ['accepted', 'in_progress', 'open'], true)) {
            http_response_code(409);
            echo json_encode(["message" => "This ticket is not being repaired"]);
            return;
        }

        $notes = isset($data['notes']) && is_string($data['notes']) ? trim($data['notes']) : null;
        $stmt = $this->db->prepare("
            INSERT INTO repair_actions (repair_id, action, notes, performed_by)
            VALUES (?, ?, ?, ?)
        ");
        $stmt->execute([$id, trim($data['action']), $notes, $this->user['id']]);
        echo json_encode(["message" => "Action added"]);
    }

    private function logAction($itemId, $action, $notes, $fromStatus, $toStatus, $performedBy) {
        $stmt = $this->db->prepare("
            INSERT INTO inventory_logs (item_id, action, from_status, to_status, notes, performed_by)
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([$itemId, $action, $fromStatus, $toStatus, $notes, $performedBy]);
    }
}
?>
