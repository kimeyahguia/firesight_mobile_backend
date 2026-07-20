<?php
// firesight_api/bfp/incidents/actions.php
// Nagbabalik ng listahan ng responder actions/updates (kasama ang evidence photos) para sa isang incident.
// Ginagamit kapag kailangan i-refresh lang ang timeline nang hindi kinukuha ulit ang buong incident details.

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once __DIR__ . '/../../config/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit();
}

$incidentId = isset($_GET['id']) ? (int) $_GET['id'] : 0;

if ($incidentId <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Missing incident id']);
    exit();
}

try {
    $stmt = $conn->prepare("
        SELECT
            a.id,
            a.action_type,
            a.note,
            a.created_at,
            p.full_name AS personnel_name
        FROM incident_actions a
        LEFT JOIN bfp_personnel p ON a.personnel_id = p.id
        WHERE a.incident_id = :id
        ORDER BY a.created_at ASC
    ");
    $stmt->execute([':id' => $incidentId]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $photoStmt = $conn->prepare(
        "SELECT photo_url FROM incident_action_photos WHERE action_id = :action_id ORDER BY id ASC"
    );

    $actions = array_map(function ($row) use ($photoStmt) {
        $photoStmt->execute([':action_id' => $row['id']]);
        $photos = array_column($photoStmt->fetchAll(PDO::FETCH_ASSOC), 'photo_url');

        return [
            'id'         => (string) $row['id'],
            'actionType' => $row['action_type'],
            'note'       => $row['note'],
            'personnel'  => $row['personnel_name'] ?? 'Unknown',
            'photos'     => $photos,
            'createdAt'  => date('M j, Y · h:i A', strtotime($row['created_at'])),
        ];
    }, $rows);

    echo json_encode(['success' => true, 'data' => $actions]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}