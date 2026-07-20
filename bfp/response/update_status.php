<?php
// ── UNIFIED — Update Incident Status (Incidents tab AT Response tab) ──
// Iisang set na lang ng status: pending → verified → responding → resolved.
// Parehong tab, parehong values na tinatawag — walang na conversion pa.

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

if (!isset($conn) || !($conn instanceof PDO)) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database connection not established']);
    exit();
}

$input = json_decode(file_get_contents('php://input'), true);

$incident_id = isset($input['incident_id']) ? (int) $input['incident_id'] : 0;
$rawStatus   = isset($input['status']) ? strtolower(trim($input['status'])) : (isset($input['next_stage']) ? strtolower(trim($input['next_stage'])) : '');

$stageOrder = ['pending', 'verified', 'responding', 'resolved'];
$stageTimestampColumn = [
    'pending'    => 'pending_at',
    'verified'   => 'verified_at',
    'responding' => 'responding_at',
    'resolved'   => 'resolved_at',
];

if ($incident_id <= 0 || !in_array($rawStatus, $stageOrder, true)) {
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

    if (strtolower($existing['status']) === 'resolved') {
        http_response_code(409);
        echo json_encode(['success' => false, 'message' => 'Resolved na ang incident na ito.']);
        exit();
    }

    $conn->beginTransaction();

    $irCheck = $conn->prepare("SELECT id, stage FROM incident_response WHERE incident_id = :id ORDER BY id DESC LIMIT 1");
    $irCheck->execute([':id' => $incident_id]);
    $irRow = $irCheck->fetch(PDO::FETCH_ASSOC);

    if (!$irRow) {
        $ins = $conn->prepare("INSERT INTO incident_response (incident_id, stage, pending_at) VALUES (:id, 'Pending', NOW())");
        $ins->execute([':id' => $incident_id]);
        $rowId = $conn->lastInsertId();
        $currentIndex = 0;
    } else {
        $rowId = $irRow['id'];
        $currentIndex = array_search(strtolower($irRow['stage']), $stageOrder);
        if ($currentIndex === false) $currentIndex = 0;
    }

    $targetIndex = array_search($rawStatus, $stageOrder);

    if ($targetIndex <= $currentIndex) {
        $conn->rollBack();
        http_response_code(409);
        echo json_encode(['success' => false, 'message' => "Nasa '{$stageOrder[$currentIndex]}' na status na ang incident na ito."]);
        exit();
    }

    $targetStage = ucfirst($rawStatus);
    $setParts = ['stage = :stage'];
    $params = [':stage' => $targetStage, ':rowId' => $rowId];
    for ($i = $currentIndex + 1; $i <= $targetIndex; $i++) {
        $col = $stageTimestampColumn[$stageOrder[$i]];
        $setParts[] = "`$col` = COALESCE(`$col`, NOW())";
    }
    $sql = "UPDATE incident_response SET " . implode(', ', $setParts) . " WHERE id = :rowId";
    $conn->prepare($sql)->execute($params);

    // I-sync pabalik ang incidents.status — iisa na lang ngayon ang vocabulary, direkta na.
    $conn->prepare("UPDATE incidents SET status = :status WHERE id = :id")
         ->execute([':status' => $rawStatus, ':id' => $incident_id]);

    $tsRow = $conn->prepare("SELECT pending_at, verified_at, responding_at, resolved_at FROM incident_response WHERE id = :rowId");
    $tsRow->execute([':rowId' => $rowId]);
    $timestamps = $tsRow->fetch(PDO::FETCH_ASSOC);

    $conn->commit();

    echo json_encode([
        'success' => true,
        'data' => [
            'status'          => $targetStage,
            'stage'           => $targetStage,
            'stageIndex'      => $targetIndex,
            'stageTimestamps' => [
                $timestamps['pending_at'],
                $timestamps['verified_at'],
                $timestamps['responding_at'],
                $timestamps['resolved_at'],
            ],
        ],
    ]);
} catch (PDOException $e) {
    if ($conn->inTransaction()) $conn->rollBack();
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Failed to update incident status.']);
}