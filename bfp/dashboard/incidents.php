<?php
// ── FOR BFP SIDE ── Recent Incidents preview
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once __DIR__ . '/../../config/db.php';

$limit = isset($_GET['limit']) ? (int) $_GET['limit'] : 4;
if ($limit <= 0) $limit = 4;

function time_ago($datetime) {
    $diff = time() - strtotime($datetime);
    if ($diff < 60) return 'Just now';
    if ($diff < 3600) return floor($diff / 60) . ' min ago';
    if ($diff < 86400) return floor($diff / 3600) . ' hr ago';
    return floor($diff / 86400) . ' day(s) ago';
}

// I-normalize ang kahit anong raw status value galing DB papunta sa
// canonical set na inaasahan ng UI: Active | Responding | Verified | Resolved
function normalize_status($raw) {
    $map = [
        'pending'    => 'Active',
        'active'     => 'Active',
        'responding' => 'Responding',
        'dispatched' => 'Responding',
        'verified'   => 'Verified',
        'resolved'   => 'Resolved',
        'closed'     => 'Resolved',
    ];
    $key = strtolower(trim($raw ?? ''));
    return $map[$key] ?? 'Active';
}

try {
    $stmt = $conn->prepare("
        SELECT id, reference_id, incident_type, barangay, severity, status, full_name, latitude, longitude, created_at
        FROM incidents
        ORDER BY created_at DESC
        LIMIT :limit
    ");
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->execute();
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $data = array_map(function ($r) {
        return [
            'id' => (string) $r['id'],
            'type' => $r['incident_type'],
            'barangay' => $r['barangay'] ?? '—',
            'timeAgo' => time_ago($r['created_at']),
            'severity' => $r['severity'],
            'status' => normalize_status($r['status']),
            'reportedBy' => $r['full_name'] ?? 'Anonymous',
            'lat' => $r['latitude'] !== null ? (float) $r['latitude'] : 0,
            'lng' => $r['longitude'] !== null ? (float) $r['longitude'] : 0,
            'referenceId' => $r['reference_id'],
        ];
    }, $rows);

    echo json_encode(['success' => true, 'data' => $data]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Failed to load recent incidents.']);
}