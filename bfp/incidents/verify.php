<?php
// ── FOR BFP SIDE ── Verify Incident Report
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
$personnel_id = isset($input['personnel_id']) ? (int) $input['personnel_id'] : 0;

if ($incident_id <= 0 || $personnel_id <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Missing incident_id or personnel_id']);
    exit();
}

try {
    // Kumpirmahin munang existing ang incident at "pending" pa
    $check = $conn->prepare("SELECT status FROM incidents WHERE id = :id LIMIT 1");
    $check->execute([':id' => $incident_id]);
    $existing = $check->fetch(PDO::FETCH_ASSOC);

    if (!$existing) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Incident not found.']);
        exit();
    }

    if (strtolower($existing['status']) !== 'pending') {
        http_response_code(409);
        echo json_encode(['success' => false, 'message' => 'Hindi na "Pending" ang incident na ito, hindi na ito pwedeng i-verify ulit.']);
        exit();
    }

    // Kumpirmahin na existing ang personnel (para hindi maka-insert ng invalid FK)
    $personnelCheck = $conn->prepare("SELECT full_name FROM bfp_personnel WHERE id = :id LIMIT 1");
    $personnelCheck->execute([':id' => $personnel_id]);
    $personnelRow = $personnelCheck->fetch(PDO::FETCH_ASSOC);

    if (!$personnelRow) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Personnel not found.']);
        exit();
    }

    $update = $conn->prepare("
        UPDATE incidents
        SET status = 'verified', verified_by = :personnel_id, verified_at = NOW()
        WHERE id = :id
    ");
    $update->execute([
        ':personnel_id' => $personnel_id,
        ':id'           => $incident_id,
    ]);

    // Kunin yung updated verified_at para eksaktong maipakita sa UI
    $refetch = $conn->prepare("SELECT verified_at FROM incidents WHERE id = :id LIMIT 1");
    $refetch->execute([':id' => $incident_id]);
    $updatedRow = $refetch->fetch(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'data' => [
            'status'         => 'Verified',
            'verifiedByName' => $personnelRow['full_name'],
            'verifiedAt'     => date('M j, Y · h:i A', strtotime($updatedRow['verified_at'])),
        ],
    ]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Failed to verify incident.']);
}