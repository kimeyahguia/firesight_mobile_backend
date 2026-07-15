<?php
// ── FOR BFP SIDE ── Recent Incidents preview
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
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

try {
    $stmt = $conn->prepare("
        SELECT id, reference_id, incident_type, barangay, severity, status, reported_by, latitude, longitude, created_at
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
            'barangay' => $r['barangay'],
            'timeAgo' => time_ago($r['created_at']),
            'severity' => $r['severity'],
            'status' => $r['status'],
            'reportedBy' => $r['reported_by'],
            'lat' => (float) $r['latitude'],
            'lng' => (float) $r['longitude'],
            'referenceId' => $r['reference_id'],
        ];
    }, $rows);

    echo json_encode(['success' => true, 'data' => $data]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Failed to load recent incidents.']);
}