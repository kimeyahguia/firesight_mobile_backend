<?php
// incidents/report_status.php
// GET  usage: report_status.php?reference_id=FS-20260707-1234   → citizen-side status lookup
// POST usage: body { "incident_id": "12", "status": "Responding" } → BFP-side status update

header("Content-Type: application/json");
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}
require_once __DIR__ . '/../../config/db.php';

$method = $_SERVER['REQUEST_METHOD'];

// ── POST: BFP responder updates incident status ──
if ($method === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    $incidentId = isset($input['incident_id']) ? (int) $input['incident_id'] : 0;
    $newStatus = isset($input['status']) ? trim($input['status']) : '';

    $allowedStatuses = ['Active', 'Responding', 'Verified', 'Resolved'];

    if ($incidentId <= 0) {
        http_response_code(400);
        echo json_encode(["success" => false, "message" => "incident_id is required."]);
        exit;
    }

    if (!in_array($newStatus, $allowedStatuses, true)) {
        http_response_code(400);
        echo json_encode(["success" => false, "message" => "Invalid status value."]);
        exit;
    }

    try {
        $stmt = $conn->prepare("UPDATE incidents SET status = :status WHERE id = :id");
        $stmt->execute([':status' => $newStatus, ':id' => $incidentId]);

        if ($stmt->rowCount() === 0) {
            http_response_code(404);
            echo json_encode(["success" => false, "message" => "No incident found with that ID."]);
            exit;
        }

        echo json_encode(["success" => true, "message" => "Status updated successfully."]);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(["success" => false, "message" => "Error updating status."]);
    }
    exit;
}

// ── GET: citizen-side status lookup by reference_id ──
$referenceId = $_GET['reference_id'] ?? '';

if (empty($referenceId)) {
    http_response_code(400);
    echo json_encode(["success" => false, "message" => "reference_id is required."]);
    exit;
}

try {
    $stmt = $conn->prepare("
        SELECT reference_id, title, status, incident_type, severity, location, created_at
        FROM incidents
        WHERE reference_id = :reference_id
        LIMIT 1
    ");
    $stmt->execute([':reference_id' => $referenceId]);
    $report = $stmt->fetch();

    if (!$report) {
        http_response_code(404);
        echo json_encode(["success" => false, "message" => "No report found with that reference ID."]);
        exit;
    }

    echo json_encode(["success" => true, "report" => $report]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(["success" => false, "message" => "Error fetching report."]);
}