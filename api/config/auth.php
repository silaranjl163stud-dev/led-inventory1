<?php
// api/config/auth.php
require_once __DIR__ . '/database.php';

function authenticateRequest($requireAdmin = false, $allowCustomer = false) {
    if (!isset($_SERVER['HTTP_AUTHORIZATION'])) {
        http_response_code(401);
        echo json_encode(["message" => "Unauthorized. Missing Authorization header."]);
        exit();
    }

    $authHeader = $_SERVER['HTTP_AUTHORIZATION'];
    list($jwt) = sscanf($authHeader, 'Bearer %s');

    if (!$jwt) {
        http_response_code(401);
        echo json_encode(["message" => "Unauthorized. Invalid token format."]);
        exit();
    }

    $db = (new Database())->getConnection();
    $stmt = $db->prepare("
        SELECT id, username, role
        FROM users
        WHERE auth_token = ? AND is_active = TRUE AND deleted_at IS NULL
    ");
    $stmt->execute([$jwt]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        http_response_code(401);
        echo json_encode(["message" => "Unauthorized. Invalid or expired token."]);
        exit();
    }

    if ($requireAdmin && $user['role'] !== 'admin') {
        http_response_code(403);
        echo json_encode(["message" => "Forbidden. Admin access required."]);
        exit();
    }

    if ($user['role'] === 'customer' && !$allowCustomer) {
        http_response_code(403);
        echo json_encode(["message" => "Forbidden. Customers can only access repair tickets and customer orders."]);
        exit();
    }

    return $user;
}
?>
