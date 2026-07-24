<?php
//read.php
error_reporting(E_ERROR | E_PARSE);
ini_set('display_errors', '0');

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Connection: close');

register_shutdown_function(function () {
    $error = error_get_last();
    if ($error && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
        if (!headers_sent()) {
            http_response_code(500);
            header('Content-Type: application/json');
        }
        echo json_encode(['success' => false, 'message' => 'Fatal server error: ' . $error['message']]);
    }
});

require_once __DIR__ . '/../../config/db.php';

$userId = $_GET['user_id'] ?? null;
$limit = isset($_GET['limit']) ? (int) $_GET['limit'] : null;

try {
    $sql = "SELECT * FROM incidents";
    $params = [];

    if ($userId !== null) {
        $sql .= " WHERE user_id = :user_id";
        $params[':user_id'] = $userId;
    }

    $sql .= " ORDER BY created_at DESC";

    if ($limit !== null && $limit > 0) {
        $sql .= " LIMIT " . $limit;
    }

    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    $incidents = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(["success" => true, "data" => $incidents]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(["success" => false, "message" => "Error fetching incidents: " . $e->getMessage()]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(["success" => false, "message" => "Server error while fetching incidents."]);
}