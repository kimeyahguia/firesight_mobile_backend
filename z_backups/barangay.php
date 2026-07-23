<?php
/**
 * ⚠️ DEPRECATED — replaced by shared/risk_map.php
 * Kept temporarily for rollback safety. Confirmed unused as of 2026-07-23
 * (map.tsx, home screen, BFP dashboard, and alerts.tsx/RiskMapTab all
 * migrated to services/riskMap.ts -> shared/risk_map.php).
 * TODO: safe to delete after a few days of stable testing.
 */
//barangay.php
error_reporting(E_ERROR | E_PARSE);
ini_set('display_errors', '0');

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

require_once __DIR__ . '/../../config/db.php';

function timeAgo(?string $datetime): string {
    if (empty($datetime)) return 'No updates yet';
    $diff = time() - strtotime($datetime);
    if ($diff < 60) return 'just now';
    if ($diff < 3600) return floor($diff / 60) . ' minutes ago';
    if ($diff < 86400) return floor($diff / 3600) . ' hour' . (floor($diff / 3600) > 1 ? 's' : '') . ' ago';
    return floor($diff / 86400) . ' day' . (floor($diff / 86400) > 1 ? 's' : '') . ' ago';
}

try {
    $stmt = $conn->query("
        SELECT b.id, b.name, b.risk_level AS risk, b.note, b.lat, b.lng, b.boundary_coords, b.updated_at,
               COUNT(i.id) AS incidents
        FROM barangays b
        LEFT JOIN incidents i ON i.barangay = b.name AND i.created_at >= NOW() - INTERVAL 7 DAY
        GROUP BY b.id
        ORDER BY b.name ASC
    ");
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $data = array_map(function ($r) {
        $boundary = [];
        if (!empty($r['boundary_coords'])) {
            $decoded = json_decode($r['boundary_coords'], true);
            if (is_array($decoded)) {
                $boundary = $decoded;
            }
        }

        return [
            'id' => (string) $r['id'],
            'name' => $r['name'],
            'risk' => $r['risk'] ?? 'Low',
            'incidents' => (int) $r['incidents'],
            'note' => $r['note'] ?? '',
            'lastUpdate' => timeAgo($r['updated_at']),
            'lat' => $r['lat'] !== null ? (float) $r['lat'] : 0,
            'lng' => $r['lng'] !== null ? (float) $r['lng'] : 0,
            'boundary' => $boundary,
        ];
    }, $rows);

    echo json_encode(['success' => true, 'data' => $data]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Failed to fetch barangays.']);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error while fetching barangays.']);
}