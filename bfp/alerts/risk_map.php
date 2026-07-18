<?php
// bfp/dashboard/risk_map.php
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

function computeRiskLevel($count) {
    if ($count >= 7) return 'Critical';
    if ($count >= 4) return 'High';
    if ($count >= 2) return 'Moderate';
    return 'Low';
}

$year = isset($_GET['year']) ? (int) $_GET['year'] : (int) date('Y');

try {
    $stmt = $conn->prepare("
        SELECT
            b.id, b.name, b.lat, b.lng, b.boundary_coords,
            COUNT(i.id) AS incident_count,
            MAX(i.created_at) AS last_incident_at
        FROM barangays b
        LEFT JOIN incidents i
            ON LOWER(TRIM(i.barangay)) = LOWER(TRIM(b.name))
            AND YEAR(i.created_at) = :year
        GROUP BY b.id, b.name, b.lat, b.lng, b.boundary_coords
        ORDER BY b.name ASC
    ");
    $stmt->execute([':year' => $year]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $barangays = array_map(function ($r) {
        $boundary = [];
        if (!empty($r['boundary_coords'])) {
            $decoded = json_decode($r['boundary_coords'], true);
            if (is_array($decoded)) $boundary = $decoded;
        }
        return [
            'id' => (string) $r['id'],
            'name' => $r['name'],
            'lat' => (float) $r['lat'],
            'lng' => (float) $r['lng'],
            'boundary' => $boundary,
            'risk' => computeRiskLevel((int) $r['incident_count']),
            'incidentsThisYear' => (int) $r['incident_count'],
            'lastIncidentDate' => $r['last_incident_at'],
        ];
    }, $rows);

    $markerStmt = $conn->prepare("
        SELECT id, barangay, latitude, longitude, severity, incident_type, created_at,
               YEAR(created_at) AS incident_year
        FROM incidents
        WHERE YEAR(created_at) = :year
          AND latitude IS NOT NULL
          AND longitude IS NOT NULL
        ORDER BY created_at DESC
    ");
    $markerStmt->execute([':year' => $year]);
    $markerRows = $markerStmt->fetchAll(PDO::FETCH_ASSOC);

    $markers = array_map(function ($m) {
        return [
            'id' => (string) $m['id'],
            'barangay' => trim($m['barangay']),
            'lat' => (float) $m['latitude'],
            'lng' => (float) $m['longitude'],
            'type' => $m['incident_type'],
            'date' => $m['created_at'],
            'year' => (int) $m['incident_year'],
            'risk' => $m['severity'],
        ];
    }, $markerRows);

    echo json_encode(['success' => true, 'year' => $year, 'barangays' => $barangays, 'markers' => $markers]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}