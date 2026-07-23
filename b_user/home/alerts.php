<?php
// home/alerts.php
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

try {
    $stmt = $conn->prepare("
        SELECT id, title, description, type, barangay, created_at
        FROM alerts
        WHERE is_active = 1
        ORDER BY created_at DESC
    ");
    $stmt->execute();
    $rows = $stmt->fetchAll();

    $alerts = array_map(function ($row) {
        return [
            'id'          => (string) $row['id'],
            'title'       => $row['title'],
            'description' => $row['description'],
            'type'        => $row['type'],
            'timestamp'   => date('M j, Y g:i A', strtotime($row['created_at'])),
        ];
    }, $rows);

    echo json_encode([
        'success' => true,
        'alerts'  => $alerts,
    ]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}