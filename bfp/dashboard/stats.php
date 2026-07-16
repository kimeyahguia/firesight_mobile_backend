<?php
// ── FOR BFP SIDE ── Dashboard Statistics (stat cards)
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once __DIR__ . '/../../config/db.php';

function normalize_status($raw) {
    $map = [
        'pending'    => 'Active',
        'active'     => 'Active',
        'responding' => 'Responding',
        'dispatched' => 'Responding',
        'verified'   => 'Verified',
        'resolved'   => 'Resolved',
        'closed'     => 'Resolved',
    ];
    $key = strtolower(trim($raw ?? ''));
    return $map[$key] ?? 'Active';
}

try {
    $stmt = $conn->query("SELECT status FROM incidents");
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $counts = ['Active' => 0, 'Verified' => 0, 'Responding' => 0, 'Resolved' => 0];
    foreach ($rows as $r) {
        $normalized = normalize_status($r['status']);
        $counts[$normalized]++;
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