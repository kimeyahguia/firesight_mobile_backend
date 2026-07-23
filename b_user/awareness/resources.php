<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
require_once __DIR__ . '/../../config/db.php';

try {
    $stmt = $conn->query("SELECT id, title, category, snippet, icon, read_time, body FROM resources ORDER BY id ASC");
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $data = array_map(function ($r) {
        $body = [];
        if (!empty($r['body'])) {
            $decoded = json_decode($r['body'], true);
            if (is_array($decoded)) $body = $decoded;
        }
        return [
            'id' => (string) $r['id'],
            'title' => $r['title'],
            'summary' => $r['snippet'],
            'category' => $r['category'],
            'icon' => $r['icon'] ?? 'document-text-outline',
            'readTime' => $r['read_time'] ?? '2 min',
            'body' => $body,
        ];
    }, $rows);

    echo json_encode(['success' => true, 'data' => $data]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Failed to load resources.']);
}