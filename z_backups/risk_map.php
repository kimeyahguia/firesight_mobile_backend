<?php
/**
 * ⚠️ DEPRECATED — replaced by shared/risk_map.php
 * Kept temporarily for rollback safety. Confirmed unused as of 2026-07-23
 * (map.tsx, home screen, BFP dashboard, and alerts.tsx/RiskMapTab all
 * migrated to services/riskMap.ts -> shared/risk_map.php).
 * TODO: safe to delete after a few days of stable testing.
 */
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
/**
 * Linear-interpolation percentile (same method as Excel's PERCENTILE.INC / numpy default).
 * $arr must be a plain numeric array (will be sorted internally).
 */
function percentile(array $arr, float $p): float {
    $n = count($arr);
    if ($n === 0) return 0.0;
    sort($arr, SORT_NUMERIC);
    if ($n === 1) return (float) $arr[0];

    $index = ($p / 100) * ($n - 1);
    $lower = (int) floor($index);
    $upper = (int) ceil($index);

    if ($lower === $upper) return (float) $arr[$lower];

    $fraction = $index - $lower;
    return $arr[$lower] + ($arr[$upper] - $arr[$lower]) * $fraction;
}

/**
 * Risk is relative to how this specific year's incidents are distributed
 * across barangays, not a fixed magic number. A barangay with 3 incidents
 * can be "Critical" in a quiet year and "Low" in a bad year — that's intentional,
 * it reflects where the danger is concentrated *right now*.
 *
 * Note: if incidents are heavily tied at the same low count (e.g. everyone has
 * exactly 1), percentiles will collapse and most/all barangays with any incident
 * may land in the same bucket. That's an expected edge case with sparse data,
 * not a bug.
 */
function computeRiskLevel(int $count, float $p50, float $p75, float $p90): string {
    if ($count <= 0) return 'Low';
    if ($count >= $p90) return 'Critical';
    if ($count >= $p75) return 'High';
    if ($count >= $p50) return 'Moderate';
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

    // Build the distribution of incident counts for THIS year, across barangays
    // that actually have at least 1 incident. Zero-incident barangays are excluded
    // from the percentile math (they're always "Low" regardless) so they don't
    // drag the percentiles down and make everything look worse than it is.
    $nonZeroCounts = [];
    foreach ($rows as $r) {
        $c = (int) $r['incident_count'];
        if ($c > 0) $nonZeroCounts[] = $c;
    }

    $p50 = percentile($nonZeroCounts, 50);
    $p75 = percentile($nonZeroCounts, 75);
    $p90 = percentile($nonZeroCounts, 90);

    $barangays = array_map(function ($r) use ($p50, $p75, $p90) {
        $boundary = [];
        if (!empty($r['boundary_coords'])) {
            $decoded = json_decode($r['boundary_coords'], true);
            if (is_array($decoded)) $boundary = $decoded;
        }
        $count = (int) $r['incident_count'];
        return [
            'id' => (string) $r['id'],
            'name' => $r['name'],
            'lat' => (float) $r['lat'],
            'lng' => (float) $r['lng'],
            'boundary' => $boundary,
            'risk' => computeRiskLevel($count, $p50, $p75, $p90),
            'incidentsThisYear' => $count,
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

    echo json_encode([
        'success' => true,
        'year' => $year,
        'barangays' => $barangays,
        'markers' => $markers,
        // exposed so the frontend can show a legend like
        // "Moderate: 3+ incidents, High: 6+, Critical: 9+" for this year
        'riskThresholds' => [
            'moderate' => round($p50, 2),
            'high' => round($p75, 2),
            'critical' => round($p90, 2),
        ],
    ]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}