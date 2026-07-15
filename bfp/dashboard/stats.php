<?php
// ── FOR BFP SIDE ── Dashboard Statistics (stat cards)
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
require_once __DIR__ . '/../../config/db.php';

try {
    $stmt = $conn->query("SELECT status, COUNT(*) as count FROM incidents GROUP BY status");
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $counts = ['Active' => 0, 'Verified' => 0, 'Responding' => 0, 'Resolved' => 0];
    foreach ($rows as $r) {
        if (isset($counts[$r['status']])) {
            $counts[$r['status']] = (int) $r['count'];
        }
    }

    $data = [
        ['id' => '1', 'label' => 'Active', 'value' => $counts['Active']],
        ['id' => '2', 'label' => 'Verified', 'value' => $counts['Verified']],
        ['id' => '3', 'label' => 'Responding', 'value' => $counts['Responding']],
        ['id' => '4', 'label' => 'Resolved', 'value' => $counts['Resolved']],
    ];

    echo json_encode(['success' => true, 'data' => $data]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Failed to load dashboard stats.']);
}