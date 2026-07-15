<?php
// ── FOR BFP SIDE ── Mark a notification as read
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
require_once __DIR__ . '/../../config/db.php';

$input = json_decode(file_get_contents('php://input'), true);
$notification_id = isset($input['notification_id']) ? (int) $input['notification_id'] : 0;

if ($notification_id <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Missing notification_id']);
    exit();
}

try {
    $stmt = $conn->prepare("UPDATE bfp_notifications SET is_read = 1 WHERE id = :id");
    $stmt->execute([':id' => $notification_id]);

    echo json_encode(['success' => true]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Failed to mark notification as read.']);
}