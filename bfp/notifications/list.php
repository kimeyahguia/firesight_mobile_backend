<?php
// ── FOR BFP SIDE ── Notifications list (per logged-in personnel)
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once __DIR__ . '/../../config/db.php';

$personnel_id = isset($_GET['personnel_id']) ? (int) $_GET['personnel_id'] : 0;

if ($personnel_id <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Missing personnel_id']);
    exit();
}

try {
    $stmt = $conn->prepare("
        SELECT id, category, alert_type, title, description, status, is_read, created_at
        FROM bfp_notifications
        WHERE personnel_id = :personnel_id
        ORDER BY created_at DESC
    ");
    $stmt->execute([':personnel_id' => $personnel_id]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    function time_ago($datetime) {
        $diff = time() - strtotime($datetime);
        if ($diff < 60) return 'Just now';
        if ($diff < 3600) return floor($diff / 60) . ' min ago';
        if ($diff < 86400) return floor($diff / 3600) . ' hr ago';
        return floor($diff / 86400) . ' day(s) ago';
    }

    $data = array_map(function ($r) {
        return [
            'id' => (string) $r['id'],
            'category' => $r['category'],
            'alertType' => $r['alert_type'],
            'title' => $r['title'],
            'description' => $r['description'],
            'status' => $r['status'],
            'unread' => (bool) ($r['is_read'] == 0),
            'timestamp' => time_ago($r['created_at']),
        ];
    }, $rows);

    echo json_encode(['success' => true, 'data' => $data]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Failed to load notifications.']);
}