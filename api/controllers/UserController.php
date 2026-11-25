<?php
// api/controllers/UserController.php
require_once dirname(__DIR__) . '/config/database.php';
require_once dirname(__DIR__) . '/config/auth.php';

class UserController {
    private $db;

    public function __construct() {
        // Require Admin for all actions
        authenticateRequest(true);
        $database = new Database();
        $this->db = $database->getConnection();
    }

    public function processRequest($method, $uriParts) {
        $id = $uriParts[1] ?? null;

        switch ($method) {
            case 'GET':
                $this->getAllUsers();
                break;
            case 'POST':
                $this->createUser();
                break;
            case 'PUT':
                if ($id) {
                    $this->updateUser($id);
                } else {
                    http_response_code(400);
                    echo json_encode(["message" => "User ID required"]);
                }
                break;
            case 'DELETE':
                if ($id) {
                    $this->deleteUser($id);
                } else {
                    http_response_code(400);
                    echo json_encode(["message" => "User ID required"]);
                }
                break;
            default:
                http_response_code(405);
                echo json_encode(["message" => "Method not allowed"]);
                break;
        }
    }

    private function getAllUsers() {
        $stmt = $this->db->query("
            SELECT id, username, full_name, email, role, is_active, deleted_at, created_at
            FROM users ORDER BY created_at DESC
        ");
        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode($result);
    }

    private function createUser() {
        $data = json_decode(file_get_contents("php://input"), true);
        if (
            !is_array($data) ||
            !isset($data['username'], $data['password']) ||
            !is_string($data['username']) ||
            !is_string($data['password']) ||
            trim($data['username']) === '' ||
            strlen($data['username']) > 50 ||
            strlen($data['password']) < 8
        ) {
            http_response_code(400);
            echo json_encode(["message" => "Valid username and password of at least 8 characters are required"]);
            return;
        }

        $role = $data['role'] ?? 'warehouse';
        if (!$this->validRole($role)) {
            http_response_code(400);
            echo json_encode(["message" => "Invalid user role"]);
            return;
        }

        $full_name = $data['full_name'] ?? trim($data['username']);
        $email = $data['email'] ?? null;
        if (!is_string($full_name) || trim($full_name) === '' || strlen($full_name) > 100) {
            http_response_code(400);
            echo json_encode(["message" => "Full name must be between 1 and 100 characters"]);
            return;
        }
        if ($email !== null && $email !== '' && (!is_string($email) || !filter_var($email, FILTER_VALIDATE_EMAIL))) {
            http_response_code(400);
            echo json_encode(["message" => "Invalid email address"]);
            return;
        }

        $hash = password_hash($data['password'], PASSWORD_DEFAULT);
        $email = $email === '' ? null : $email;

        try {
            $stmt = $this->db->prepare("INSERT INTO users (username, password_hash, full_name, email, role) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$data['username'], $hash, $full_name, $email, $role]);
            http_response_code(201);
            echo json_encode(["message" => "User created", "id" => $this->db->lastInsertId()]);
        } catch(PDOException $e) {
            if ($e->getCode() === '23000') {
                http_response_code(409);
                echo json_encode(["message" => "Username or email is already in use"]);
                return;
            }
            error_log($e->getMessage());
            http_response_code(500);
            echo json_encode(["message" => "Error creating user"]);
        }
    }

    private function updateUser($id) {
        $data = json_decode(file_get_contents("php://input"), true);
        if (!is_array($data)) {
            http_response_code(400);
            echo json_encode(["message" => "Invalid user data"]);
            return;
        }

        $role = $data['role'] ?? null;
        if ($role !== null && !$this->validRole($role)) {
            http_response_code(400);
            echo json_encode(["message" => "Invalid user role"]);
            return;
        }
        if (isset($data['full_name']) && (!is_string($data['full_name']) || trim($data['full_name']) === '' || strlen($data['full_name']) > 100)) {
            http_response_code(400);
            echo json_encode(["message" => "Full name must be between 1 and 100 characters"]);
            return;
        }
        if (isset($data['username']) && (!is_string($data['username']) || trim($data['username']) === '' || strlen($data['username']) > 50)) {
            http_response_code(400);
            echo json_encode(["message" => "Username must be between 1 and 50 characters"]);
            return;
        }
        if (
            isset($data['email']) &&
            $data['email'] !== '' &&
            (!is_string($data['email']) || !filter_var($data['email'], FILTER_VALIDATE_EMAIL))
        ) {
            http_response_code(400);
            echo json_encode(["message" => "Invalid email address"]);
            return;
        }
        if (array_key_exists('is_active', $data) && !is_bool($data['is_active'])) {
            http_response_code(400);
            echo json_encode(["message" => "Active status must be true or false"]);
            return;
        }
        if (isset($data['password']) && $data['password'] !== '' && strlen($data['password']) < 8) {
            http_response_code(400);
            echo json_encode(["message" => "Password must be at least 8 characters"]);
            return;
        }

        $fields = [];
        $values = [];
        foreach (['username', 'full_name', 'email', 'role'] as $field) {
            if (array_key_exists($field, $data)) {
                $fields[] = "{$field} = ?";
                $values[] = $data[$field] === '' && $field === 'email' ? null : (
                    $field === 'username' ? trim($data[$field]) : $data[$field]
                );
            }
        }
        if (array_key_exists('is_active', $data)) {
            $fields[] = 'is_active = ?';
            $values[] = $data['is_active'] ? 1 : 0;
            if ($data['is_active']) {
                $fields[] = 'deleted_at = NULL';
            }
        }
        if (!empty($data['password'])) {
            $fields[] = 'password_hash = ?';
            $values[] = password_hash($data['password'], PASSWORD_DEFAULT);
        }

        if (!$fields) {
            http_response_code(400);
            echo json_encode(["message" => "No user fields to update"]);
            return;
        }

        try {
            $values[] = $id;
            $stmt = $this->db->prepare("UPDATE users SET " . implode(', ', $fields) . " WHERE id = ?");
            $stmt->execute($values);
            if ($stmt->rowCount() === 0 && !$this->userExists($id)) {
                http_response_code(404);
                echo json_encode(["message" => "User not found"]);
                return;
            }
            echo json_encode(["message" => "User updated"]);
        } catch (PDOException $e) {
            if ($e->getCode() === '23000') {
                http_response_code(409);
                echo json_encode(["message" => "Username or email is already in use"]);
                return;
            }
            error_log($e->getMessage());
            http_response_code(500);
            echo json_encode(["message" => "Error updating user"]);
        }
    }

    private function validRole($role) {
        return is_string($role) && in_array($role, [
            'admin', 'manager', 'warehouse', 'technician', 'staff', 'customer'
        ], true);
    }

    private function userExists($id) {
        $stmt = $this->db->prepare("SELECT 1 FROM users WHERE id = ?");
        $stmt->execute([$id]);
        return (bool) $stmt->fetchColumn();
    }

    private function deleteUser($id) {
        try {
            // Soft delete — preserve audit trail per new schema
            $stmt = $this->db->prepare("UPDATE users SET deleted_at = CURRENT_TIMESTAMP, is_active = FALSE WHERE id = ?");
            $stmt->execute([$id]);
            echo json_encode(["message" => "User deleted"]);
        } catch(PDOException $e) {
            http_response_code(500);
            echo json_encode(["message" => "Error deleting user: " . $e->getMessage()]);
        }
    }
}
?>
