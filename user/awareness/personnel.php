<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
require_once __DIR__ . '/../config/db.php';

try {
    $stmt = $conn->query("
        SELECT id, full_name, rank_title, position
        FROM bfp_personnel
        WHERE show_on_awareness = 1
        ORDER BY id ASC
    ");
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $data = array_map(function ($r) {
        return [
            'id' => (string) $r['id'],
            'name' => $r['full_name'],
            'rank' => $r['rank_title'] ?? '',
            'role' => $r['position'] ?? '',
        ];
    }, $rows);

    echo json_encode(['success' => true, 'data' => $data]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Failed to load personnel.']);
}