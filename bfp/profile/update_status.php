<?php
// firesight_api/bfp/profile/update_status.php
// ── FOR BFP SIDE ── I-update ang duty status ng personnel (On Duty / Off Duty)

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once __DIR__ . '/../../config/db.php'; // dapat naglalabas ng PDO sa $conn

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit();
}

$input = json_decode(file_get_contents('php://input'), true) ?? [];

$personnelId = isset($input['personnel_id']) ? trim((string) $input['personnel_id']) : null;
$status = isset($input['status']) ? trim((string) $input['status']) : null;

$allowedStatuses = ['On Duty', 'Off Duty'];

if (!$personnelId || !$status || !in_array($status, $allowedStatuses, true)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Missing or invalid personnel_id/status.']);
    exit();
}

try {
    $stmt = $conn->prepare("UPDATE bfp_personnel SET status = :status WHERE id = :id");
    $stmt->execute([
        ':status' => $status,
        ':id' => $personnelId,
    ]);

    echo json_encode(['success' => true, 'message' => 'Status updated.']);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Failed to update status.']);
}