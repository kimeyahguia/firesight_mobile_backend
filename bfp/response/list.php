<?php
error_reporting(E_ERROR | E_PARSE);
ini_set('display_errors', '0');
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
require_once __DIR__ . '/../../config/db.php';

if (!isset($conn) || !($conn instanceof PDO)) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database connection not established']);
    exit();
}

define('STATION_LAT', 14.0369);
define('STATION_LNG', 120.65257);
define('AVG_SPEED_KMH', 35);

function haversineKm($lat1, $lng1, $lat2, $lng2) {
    $earthRadius = 6371;
    $dLat = deg2rad($lat2 - $lat1);
    $dLng = deg2rad($lng2 - $lng1);
    $a = sin($dLat / 2) ** 2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;
    $c = 2 * atan2(sqrt($a), sqrt(1 - $a));
    return $earthRadius * $c;
}

function timeAgo($datetime) {
    if (!$datetime) return null;
    $diff = time() - strtotime($datetime);
    if ($diff < 60) return 'just now';
    if ($diff < 3600) return floor($diff / 60) . ' min ago';
    if ($diff < 86400) return floor($diff / 3600) . ' hr ago';
    return floor($diff / 86400) . ' day' . (floor($diff / 86400) > 1 ? 's' : '') . ' ago';
}

$stageOrder = ['Reported', 'Verified', 'Dispatched', 'On Scene', 'Resolved'];
$includeResolved = isset($_GET['includeResolved']) && $_GET['includeResolved'] == '1';

try {
    $where = $includeResolved ? '' : "WHERE (ir.stage IS NULL OR ir.stage != 'Resolved')";
    $stmt = $conn->query("
        SELECT
            i.id, i.reference_id, i.title, i.barangay, i.location, i.street_landmark,
            i.latitude, i.longitude, i.severity, i.incident_type,
            i.full_name AS reporter_name, i.created_at,
            ir.assigned_responder_id, ir.assigned_truck_id, ir.stage,
            ir.reported_at, ir.verified_at, ir.dispatched_at, ir.on_scene_at, ir.resolved_at,
            p.full_name AS responder_name, p.rank_title AS responder_rank, p.phone AS responder_phone,
            t.name AS truck_name, t.plate_no AS truck_plate, t.type AS truck_type,
            t.water_capacity AS truck_capacity, t.driver_name AS truck_driver
        FROM incidents i
        LEFT JOIN incident_response ir ON ir.incident_id = i.id
        LEFT JOIN bfp_personnel p ON p.id = ir.assigned_responder_id
        LEFT JOIN fire_trucks t ON t.id = ir.assigned_truck_id
        $where
        ORDER BY i.created_at DESC
    ");
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $data = array_map(function ($r) use ($stageOrder) {
        $stage = $r['stage'] ?: 'Reported';
        $stageIndex = array_search($stage, $stageOrder);
        if ($stageIndex === false) $stageIndex = 0;

        $distanceKm = null;
        $etaMinutes = null;
        if ($r['latitude'] && $r['longitude']) {
            $distanceKm = round(haversineKm(STATION_LAT, STATION_LNG, (float) $r['latitude'], (float) $r['longitude']), 1);
            $etaMinutes = max(1, (int) round(($distanceKm / AVG_SPEED_KMH) * 60));
        }

        $initials = '';
        if ($r['responder_name']) {
            $parts = preg_split('/\s+/', trim($r['responder_name']));
            $initials = strtoupper(substr($parts[0], 0, 1) . substr(end($parts), 0, 1));
        }

        return [
            'id' => (string) $r['id'],
            'referenceId' => $r['reference_id'],
            'title' => $r['title'],
            'barangay' => $r['barangay'],
            'address' => trim(($r['street_landmark'] ? $r['street_landmark'] . ', ' : '') . ($r['location'] ?: '')),
            'risk' => $r['severity'],
            'reportedAt' => $r['reported_at'] ?: $r['created_at'],
            'reportedAgo' => timeAgo($r['reported_at'] ?: $r['created_at']),
            'reporterName' => $r['reporter_name'] ?: 'Anonymous Citizen Report',
            'lat' => $r['latitude'] ? (float) $r['latitude'] : null,
            'lng' => $r['longitude'] ? (float) $r['longitude'] : null,
            'etaMinutes' => $etaMinutes,
            'distanceKm' => $distanceKm,
            'responder' => $r['assigned_responder_id'] ? [
                'id' => (string) $r['assigned_responder_id'],
                'name' => $r['responder_name'],
                'rank' => $r['responder_rank'],
                'contactNumber' => $r['responder_phone'],
                'photoInitials' => $initials,
            ] : null,
            'truck' => $r['assigned_truck_id'] ? [
                'id' => (string) $r['assigned_truck_id'],
                'unitCode' => $r['truck_name'],
                'plateNumber' => $r['truck_plate'],
                'type' => $r['truck_type'],
                'capacity' => $r['truck_capacity'],
                'driver' => $r['truck_driver'],
            ] : null,
            'stageIndex' => (int) $stageIndex,
            'stageLabel' => $stage,
            'stageTimestamps' => [
                $r['reported_at'] ?: $r['created_at'],
                $r['verified_at'], $r['dispatched_at'], $r['on_scene_at'], $r['resolved_at'],
            ],
        ];
    }, $rows);

    echo json_encode(['success' => true, 'data' => $data]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}