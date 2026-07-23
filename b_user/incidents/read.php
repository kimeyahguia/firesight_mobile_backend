<?php
require_once __DIR__ . '/../../config/db.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

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
}