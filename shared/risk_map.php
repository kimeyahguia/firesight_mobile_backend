<?php
/**
 * Shared Risk Map Endpoint — single source of truth for barangay risk
 * levels + incident markers. Used by BOTH the User app (map.tsx) and
 * the BFP app (risk map / dashboard preview).
 *
 * Replaces: bfp/dashboard/barangays.php, bfp/alerts/nearby_risk.php,
 * bfp/dashboard/risk_map.php, user/map/barangay.php,
 * user/home/barangay_risk_summary.php
 * (those stay in place for now until frontend is migrated — see Step 3/4)
 *
 * Risk is computed dynamically from VERIFIED incidents only.
 * "Verified" = verified_by IS NOT NULL (true regardless of current
 * status, since a verified incident can later move to responding/resolved —
 * matching on status = 'verified' alone would undercount).
 */

error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once __DIR__ . '/../config/db.php';

if (!isset($conn) || !($conn instanceof PDO)) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database connection not established']);
    exit();
}

/**
 * Linear-interpolation percentile (Excel PERCENTILE.INC / numpy default).
 */
function percentile(array $arr, float $p): float
{
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
 * Risk is relative to how verified incidents are currently distributed
 * across barangays — not a fixed magic number. This is intentional:
 * a barangay with 3 verified incidents can be "Critical" in a quiet
 * period and "Low" in a bad one, reflecting where danger is
 * concentrated *right now* rather than an arbitrary static count.
 */
function computeRiskLevel(int $count, float $p50, float $p75, float $p90): string
{
    if ($count <= 0) return 'Low';
    if ($count >= $p90) return 'Critical';
    if ($count >= $p75) return 'High';
    if ($count >= $p50) return 'Moderate';
    return 'Low';
}

// Optional filter: restrict to a specific year. Omit for all-time totals
// (which is what "total number of verified reports per barangay" implies).
$year = isset($_GET['year']) && $_GET['year'] !== '' ? (int) $_GET['year'] : null;
$yearFilterSql = $year !== null ? ' AND YEAR(i.created_at) = :year' : '';

try {
    // 1. Verified incident count per barangay (name-matched, case/whitespace-insensitive)
    $summaryStmt = $conn->prepare("
        SELECT
            b.id,
            b.name,
            b.lat,
            b.lng,
            b.boundary_coords,
            b.note,
            b.updated_at,
            COUNT(i.id) AS verified_count,
            MAX(i.created_at) AS last_incident_at
        FROM barangays b
        LEFT JOIN incidents i
            ON LOWER(TRIM(i.barangay)) = LOWER(TRIM(b.name))
            AND i.verified_by IS NOT NULL
            {$yearFilterSql}
        GROUP BY b.id, b.name, b.lat, b.lng, b.boundary_coords, b.note, b.updated_at
        ORDER BY b.name ASC
    ");
    $summaryParams = $year !== null ? [':year' => $year] : [];
    $summaryStmt->execute($summaryParams);
    $rows = $summaryStmt->fetchAll(PDO::FETCH_ASSOC);

    // Percentile distribution across barangays that actually have >=1
    // verified incident. Zero-incident barangays are excluded from the
    // percentile math (they're always "Low" regardless) so they don't
    // drag the thresholds down artificially.
    $nonZeroCounts = [];
    foreach ($rows as $r) {
        $c = (int) $r['verified_count'];
        if ($c > 0) $nonZeroCounts[] = $c;
    }

    $p50 = percentile($nonZeroCounts, 50);
    $p75 = percentile($nonZeroCounts, 75);
    $p90 = percentile($nonZeroCounts, 90);

    $barangays = array_map(function ($r) use ($p50, $p75, $p90) {
        $boundary = [];
        if (!empty($r['boundary_coords'])) {
            $decoded = json_decode($r['boundary_coords'], true);
            if (is_array($decoded)) $boundary = $decoded; // already [lat, lng] pairs
        }
        $count = (int) $r['verified_count'];
        return [
            'id'               => (string) $r['id'],
            'name'             => $r['name'],
            'lat'              => (float) $r['lat'],
            'lng'              => (float) $r['lng'],
            'boundary'         => $boundary,
            'note'             => $r['note'] ?? '',
            'risk'             => computeRiskLevel($count, $p50, $p75, $p90),
            'verifiedIncidents' => $count,
            'lastIncidentAt'   => $r['last_incident_at'],
        ];
    }, $rows);

    // 2. Individual verified incident markers (exact locations for map dots)
    $markerStmt = $conn->prepare("
        SELECT
            i.id,
            i.reference_id,
            i.barangay,
            i.latitude,
            i.longitude,
            i.severity,
            i.incident_type,
            i.status,
            i.created_at
        FROM incidents i
        WHERE i.verified_by IS NOT NULL
            AND i.latitude IS NOT NULL
            AND i.longitude IS NOT NULL
            {$yearFilterSql}
        ORDER BY i.created_at DESC
    ");
    $markerStmt->execute($summaryParams);
    $markerRows = $markerStmt->fetchAll(PDO::FETCH_ASSOC);

    $markers = array_map(function ($m) {
        return [
            'id'           => (string) $m['id'],
            'referenceId'  => $m['reference_id'],
            'barangay'     => trim($m['barangay'] ?? ''),
            'lat'          => (float) $m['latitude'],
            'lng'          => (float) $m['longitude'],
            'severity'     => $m['severity'],
            'incidentType' => $m['incident_type'],
            'status'       => $m['status'],
            'date'         => $m['created_at'],
        ];
    }, $markerRows);

    echo json_encode([
        'success'   => true,
        'year'      => $year, // null = all-time
        'barangays' => $barangays,
        'markers'   => $markers,
        // exposed so the frontend can show a legend like
        // "Moderate: 2+ verified, High: 4+, Critical: 7+"
        'riskThresholds' => [
            'moderate' => round($p50, 2),
            'high'     => round($p75, 2),
            'critical' => round($p90, 2),
        ],
    ]);

} catch (PDOException $e) {
    error_log('[shared/risk_map.php] DB error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Unable to load risk map data. Please try again later.']);
}