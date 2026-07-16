<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
require_once __DIR__ . '/../config/db.php';

$user_id = isset($_GET['user_id']) ? (int) $_GET['user_id'] : 0;

try {
    $stmt = $conn->query("SELECT id, slug, title, description, icon, content FROM safety_topics ORDER BY sort_order ASC");
    $topics = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $doneIds = [];
    if ($user_id > 0) {
        $progressStmt = $conn->prepare("SELECT topic_id FROM user_topic_progress WHERE user_id = :user_id AND done = 1");
        $progressStmt->execute([':user_id' => $user_id]);
        $doneIds = array_column($progressStmt->fetchAll(PDO::FETCH_ASSOC), 'topic_id');
    }

    $data = array_map(function ($t) use ($doneIds) {
        return [
            'id' => (string) $t['id'],
            'title' => $t['title'],
            'description' => $t['description'],
            'icon' => $t['icon'],
            'content' => $t['content'],
            'done' => in_array((int) $t['id'], $doneIds) ? 1 : 0,
        ];
    }, $topics);

    echo json_encode(['success' => true, 'data' => $data]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Failed to load safety topics.']);
}