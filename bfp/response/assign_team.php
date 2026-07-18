<?php
error_reporting(E_ERROR | E_PARSE);
ini_set('display_errors', '0');
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
require_once __DIR__ . '/../../config/db.php';

if (!isset($conn) || !($conn instanceof PDO)) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database connection not established']);
    exit();
}

$input = json_decode(file_get_contents('php://input'), true);
$incidentId = $input['incident_id'] ?? null;
$responderId = $input['responder_id'] ?? null;
$truckId = $input['truck_id'] ?? null;

if (!$incidentId || (!$responderId && !$truckId)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'incident_id and at least one of responder_id/truck_id required']);
    exit();
}

try {
    $ensure = $conn->prepare("
        INSERT INTO incident_response (incident_id, stage, reported_at)
        VALUES (:id, 'Reported', NOW())
        ON DUPLICATE KEY UPDATE incident_id = incident_id
    ");
    $ensure->execute([':id' => $incidentId]);

    $fields = [];
    $params = [':id' => $incidentId];
    if ($responderId) { $fields[] = 'assigned_responder_id = :responderId'; $params[':responderId'] = $responderId; }
    if ($truckId) { $fields[] = 'assigned_truck_id = :truckId'; $params[':truckId'] = $truckId; }

    $stmt = $conn->prepare("UPDATE incident_response SET " . implode(', ', $fields) . " WHERE incident_id = :id");
    $stmt->execute($params);

    echo json_encode(['success' => true, 'message' => 'Team assigned']);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}