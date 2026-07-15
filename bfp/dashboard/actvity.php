<?php
// ── FOR BFP SIDE ── Responder Activity Feed
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
require_once __DIR__ . '/../../config/db.php';

function time_ago($datetime) {
    $diff = time() - strtotime($datetime);
    if ($diff < 60) return 'Just now';
    if ($diff < 3600) return floor($diff / 60) . ' min ago';
    if ($diff < 86400) return floor($diff / 3600) . ' hr ago';
    return floor($diff / 86400) . ' day(s) ago';
}

function get_initials($fullName) {
    $parts = explode(' ', trim($fullName));
    $initials = '';
    foreach ($parts as $p) {
        if (strlen($p) > 0) $initials .= strtoupper($p[0]);
    }
    return substr($initials, 0, 2);
}

try {
    $stmt = $conn->query("
        SELECT a.id, p.full_name, a.action, a.target_reference, a.icon, a.icon_bg, a.icon_color, a.created_at
        FROM bfp_activity_log a
        JOIN bfp_personnel p ON a.personnel_id = p.id
        ORDER BY a.created_at DESC
        LIMIT 10
    ");
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $data = array_map(function ($r) {
        return [
            'id' => (string) $r['id'],
            'responder' => $r['full_name'],
            'initials' => get_initials($r['full_name']),
            'action' => $r['action'],
            'target' => $r['target_reference'],
            'timeAgo' => time_ago($r['created_at']),
            'iconBg' => $r['icon_bg'],
            'iconColor' => $r['icon_color'],
            'icon' => $r['icon'],
        ];
    }, $rows);

    echo json_encode(['success' => true, 'data' => $data]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Failed to load activity feed.']);
}