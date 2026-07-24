<?php
/**
 * ⚠️ DEPRECATED — replaced by shared/risk_map.php
 * Kept temporarily for rollback safety. Confirmed unused as of 2026-07-23
 * (map.tsx, home screen, BFP dashboard, and alerts.tsx/RiskMapTab all
 * migrated to services/riskMap.ts -> shared/risk_map.php).
 * TODO: safe to delete after a few days of stable testing.
 */

// home/barangay_risk_summary.php
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

// ── Risk level thresholds based on incident count this month ──
function computeRiskLevel($count) {
    if ($count >= 7) return 'Critical';
    if ($count >= 4) return 'High';
    if ($count >= 2) return 'Moderate';
    return 'Low';
}

try {
    $stmt = $conn->prepare("
        SELECT
            barangay,
            COUNT(*) AS incident_count,
            MAX(created_at) AS last_incident_at
        FROM incidents
        WHERE barangay IS NOT NULL
          AND barangay != ''
          AND created_at >= DATE_FORMAT(NOW(), '%Y-%m-01')
        GROUP BY barangay
        ORDER BY incident_count DESC
    ");
    $stmt->execute();
    $rows = $stmt->fetchAll();

    $barangays = array_map(function ($row, $index) {
        return [
            'id'        => (string) ($index + 1),
            'name'      => $row['barangay'],
            'risk'      => computeRiskLevel((int) $row['incident_count']),
            'incidents' => (int) $row['incident_count'],
            'updatedAt' => $row['last_incident_at'],
        ];
    }, $rows, array_keys($rows));

    echo json_encode([
        'success'   => true,
        'barangays' => $barangays,
    ]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}