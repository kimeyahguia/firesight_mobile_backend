<?php
// ── FOR BFP SIDE ── Nearby Barangay Risk Summary
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once __DIR__ . '/../../config/db.php';

try {
    $stmt = $conn->query("
        SELECT b.id, b.name, b.risk_level,
        (SELECT COUNT(*) FROM incidents i WHERE i.barangay = b.name AND i.status != 'Resolved') AS incident_count
        FROM barangays b
        ORDER BY FIELD(b.risk_level, 'Critical','High','Moderate','Low'), incident_count DESC
        LIMIT 6
    ");
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $data = array_map(function ($r) {
        return [
            'id' => (string) $r['id'],
            'name' => $r['name'],
            'risk' => $r['risk_level'],
            'distance' => '—',
            'incidents' => (int) $r['incident_count'],
        ];
    }, $rows);

    echo json_encode(['success' => true, 'data' => $data]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Failed to load nearby risk summary.']);
}