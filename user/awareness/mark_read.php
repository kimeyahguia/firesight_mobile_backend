<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
require_once __DIR__ . '/../config/db.php';

$input = json_decode(file_get_contents('php://input'), true);
$user_id = isset($input['user_id']) ? (int) $input['user_id'] : 0;
$topic_id = isset($input['topic_id']) ? (int) $input['topic_id'] : 0;

if ($user_id <= 0 || $topic_id <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Missing user_id or topic_id']);
    exit();
}

try {
    $checkStmt = $conn->prepare("SELECT done FROM user_topic_progress WHERE user_id = :user_id AND topic_id = :topic_id");
    $checkStmt->execute([':user_id' => $user_id, ':topic_id' => $topic_id]);
    $existing = $checkStmt->fetch();

    if ($existing) {
        $newDone = $existing['done'] == 1 ? 0 : 1;
        $updateStmt = $conn->prepare("UPDATE user_topic_progress SET done = :done WHERE user_id = :user_id AND topic_id = :topic_id");
        $updateStmt->execute([':done' => $newDone, ':user_id' => $user_id, ':topic_id' => $topic_id]);
    } else {
        $newDone = 1;
        $insertStmt = $conn->prepare("INSERT INTO user_topic_progress (user_id, topic_id, done) VALUES (:user_id, :topic_id, :done)");
        $insertStmt->execute([':user_id' => $user_id, ':topic_id' => $topic_id, ':done' => $newDone]);
    }

    echo json_encode(['success' => true, 'done' => $newDone]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}