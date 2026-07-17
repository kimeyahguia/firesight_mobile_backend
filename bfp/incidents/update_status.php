<?php
// ── FOR BFP SIDE ── Update Incident Status (Responding / Resolved)
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once __DIR__ . '/../../config/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit();
}

$input = json_decode(file_get_contents('php://input'), true);

$incident_id  = isset($input['incident_id']) ? (int) $input['incident_id'] : 0;
$newStatus    = isset($input['status']) ? strtolower(trim($input['status'])) : '';
$personnel_id = isset($input['personnel_id']) ? (int) $input['personnel_id'] : 0;

$allowedStatuses = ['responding', 'resolved'];

if ($incident_id <= 0 || !in_array($newStatus, $allowedStatuses, true)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Missing/invalid incident_id or status']);
    exit();
}

try {
    $check = $conn->prepare("SELECT status FROM incidents WHERE id = :id LIMIT 1");
    $check->execute([':id' => $incident_id]);
    $existing = $check->fetch(PDO::FETCH_ASSOC);

    if (!$existing) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Incident not found.']);
        exit();
    }

    $currentStatus = strtolower($existing['status']);

    if ($currentStatus === 'pending') {
        http_response_code(409);
        echo json_encode(['success' => false, 'message' => 'Kailangan i-verify muna ang report bago ma-update ang status.']);
        exit();
    }

    if ($currentStatus === 'resolved') {
        http_response_code(409);
        echo json_encode(['success' => false, 'message' => 'Resolved na ang incident na ito.']);
        exit();
    }

    if ($currentStatus === $newStatus) {
        http_response_code(409);
        echo json_encode(['success' => false, 'message' => "Nasa '{$newStatus}' na status na ang incident na ito."]);
        exit();
    }

    $update = $conn->prepare("UPDATE incidents SET status = :status WHERE id = :id");
    $update->execute([
        ':status' => $newStatus,
        ':id'     => $incident_id,
    ]);

    echo json_encode([
        'success' => true,
        'data' => [
            'status' => ucfirst($newStatus),
        ],
    ]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Failed to update incident status.']);
}