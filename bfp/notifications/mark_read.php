<?php
// ── FOR BFP SIDE ── Mark a notification as read
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once __DIR__ . '/../../config/db.php';

$input = json_decode(file_get_contents('php://input'), true);
$notification_id = isset($input['notification_id']) ? (int) $input['notification_id'] : 0;
$personnel_id = isset($input['personnel_id']) ? (int) $input['personnel_id'] : 0;

if ($notification_id <= 0 || $personnel_id <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Missing notification_id or personnel_id']);
    exit();
}

try {
    // Naka-scope sa personnel_id para hindi mai-mark as read ng ibang tao
    // yung notification na hindi naman sakanya.
    $stmt = $conn->prepare("
        UPDATE bfp_notifications
        SET is_read = 1
        WHERE id = :id AND personnel_id = :personnel_id
    ");
    $stmt->execute([
        ':id' => $notification_id,
        ':personnel_id' => $personnel_id,
    ]);

    if ($stmt->rowCount() === 0) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Notification not found or not yours.']);
        exit();
    }

    echo json_encode(['success' => true]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Failed to mark notification as read.']);
}