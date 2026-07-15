<?php
// ── FOR BFP SIDE ── Nearby Barangay Risk Summary
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
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
            'distance' => '—', // wala pang stored coordinates ng center ng barangay para makakuha ng exact distance
            'incidents' => (int) $r['incident_count'],
        ];
    }, $rows);

    echo json_encode(['success' => true, 'data' => $data]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Failed to load nearby risk summary.']);
}