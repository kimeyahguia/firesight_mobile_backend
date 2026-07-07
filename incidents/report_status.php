<?php
// incidents/report_status.php
// Usage: report_status.php?reference_id=FS-20260707-1234

header("Content-Type: application/json");
require_once __DIR__ . '/../config/db.php';

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
    echo json_encode(["success" => false, "message" => "Error fetching report: " . $e->getMessage()]);
}