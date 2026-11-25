<?php
// api/controllers/AuthController.php
require_once dirname(__DIR__) . '/config/database.php';

class AuthController {
    private $db;

    public function __construct() {
        $database = new Database();
        $this->db = $database->getConnection();
    }

    public function processRequest($method, $uriParts) {
        $action = $uriParts[1] ?? '';

        if ($method === 'POST' && $action === 'login') {
            $this->login();
        } else if ($method === 'POST' && $action === 'register') {
            $this->register();
        } else if ($method === 'POST' && $action === 'logout') {
            $this->logout();
        } else if ($method === 'GET' && $action === 'me') {
            $this->me();
        } else {
            http_response_code(404);
            echo json_encode(["message" => "Auth endpoint not found"]);
        }
    }

    private function login() {
        $data = json_decode(file_get_contents("php://input"), true);
        
        if (!isset($data['username']) || !isset($data['password'])) {
            http_response_code(400);
            echo json_encode(["message" => "Username and password are required"]);
            return;
        }

        $stmt = $this->db->prepare("
            SELECT id, username, password_hash, role
            FROM users
            WHERE username = ? AND is_active = TRUE AND deleted_at IS NULL
        ");
        $stmt->execute([$data['username']]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user && password_verify($data['password'], $user['password_hash'])) {
            // Generate token (simple random string for demonstration, could be JWT)
            $token = bin2hex(random_bytes(32));
            
            // Save token to db
            $updateStmt = $this->db->prepare("UPDATE users SET auth_token = ? WHERE id = ?");
            $updateStmt->execute([$token, $user['id']]);

            echo json_encode([
                "message" => "Login successful",
                "token" => $token,
                "user" => [
                    "id" => $user['id'],
                    "username" => $user['username'],
                    "role" => $user['role']
                ]
            ]);
        } else {
            http_response_code(401);
            echo json_encode(["message" => "Invalid credentials"]);
        }
    }

    private function register() {
        $data = json_decode(file_get_contents("php://input"), true);
        if (
            !is_array($data) ||
            !isset($data['username'], $data['full_name'], $data['password']) ||
            !is_string($data['username']) ||
            !is_string($data['full_name']) ||
            !is_string($data['password'])
        ) {
            http_response_code(400);
            echo json_encode(["message" => "Username, full name, and password are required"]);
            return;
        }

        $username = trim($data['username']);
        $fullName = trim($data['full_name']);
        $password = $data['password'];
        $email = $data['email'] ?? null;

        if ($email !== null && !is_string($email)) {
            http_response_code(400);
            echo json_encode(["message" => "Email must be a string"]);
            return;
        }

        $email = $email === null ? null : trim($email);
        if ($email === '') {
            $email = null;
        }

        if (
            $username === '' || strlen($username) > 50 ||
            $fullName === '' || strlen($fullName) > 100 ||
            strlen($password) < 8 ||
            ($email !== null && (strlen($email) > 150 || !filter_var($email, FILTER_VALIDATE_EMAIL)))
        ) {
            http_response_code(400);
            echo json_encode(["message" => "Please provide valid registration details and a password of at least 8 characters"]);
            return;
        }

        $hash = password_hash($password, PASSWORD_DEFAULT);
        try {
            $stmt = $this->db->prepare(
                "INSERT INTO users (username, password_hash, full_name, email, role) VALUES (?, ?, ?, ?, 'customer')"
            );
            $stmt->execute([$username, $hash, $fullName, $email]);
            http_response_code(201);
            echo json_encode(["message" => "Registration successful"]);
        } catch (PDOException $e) {
            if ($e->getCode() === '23000') {
                http_response_code(409);
                echo json_encode(["message" => "Username or email is already in use"]);
                return;
            }

            error_log($e->getMessage());
            http_response_code(500);
            echo json_encode(["message" => "Registration failed"]);
        }
    }

    private function logout() {
        require_once dirname(__DIR__) . '/config/auth.php';
        $user = authenticateRequest(false, true);
        
        // Invalidate token
        $stmt = $this->db->prepare("UPDATE users SET auth_token = NULL WHERE id = ?");
        $stmt->execute([$user['id']]);
        
        echo json_encode(["message" => "Logged out successfully"]);
    }

    private function me() {
        require_once dirname(__DIR__) . '/config/auth.php';
        $user = authenticateRequest(false, true);
        echo json_encode(["user" => $user]);
    }
}
?>
