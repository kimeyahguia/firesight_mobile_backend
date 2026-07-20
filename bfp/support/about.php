<?php
// ── FOR BFP SIDE ── About FireSight (backend-managed content)
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once __DIR__ . '/../../config/db.php';

try {
    $stmt = $conn->prepare("
        SELECT app_name, description, system_overview, developer_info, version_label, version_description, additional_info
        FROM app_about
        ORDER BY id DESC
        LIMIT 1
    ");
    $stmt->execute();
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    $data = [
        'appName'            => $row['app_name'] ?? null,
        'description'        => $row['description'] ?? null,
        'systemOverview'     => $row['system_overview'] ?? null,
        'developerInfo'      => $row['developer_info'] ?? null,
        'versionLabel'       => $row['version_label'] ?? null,
        'versionDescription' => $row['version_description'] ?? null,
        'additionalInfo'     => $row['additional_info'] ?? null,
    ];

    echo json_encode(['success' => true, 'data' => $data]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Failed to load About FireSight info.']);
}