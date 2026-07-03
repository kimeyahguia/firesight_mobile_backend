<?php
// profile/readiness_toggle.php
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

include_once '../config/db.php';

if (!isset($conn)) {
    if (isset($pdo)) {
        $conn = $pdo;
    } elseif (isset($db)) {
        $conn = $db;
    }
}

if (!isset($conn)) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database connection not available']);
    exit();
}

$input = json_decode(file_get_contents('php://input'), true);
$user_id = isset($input['user_id']) ? (int) $input['user_id'] : 0;
$readiness_item_id = isset($input['readiness_item_id']) ? (int) $input['readiness_item_id'] : 0;

if ($user_id <= 0 || $readiness_item_id <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Missing user_id or readiness_item_id']);
    exit();
}

try {
    $checkStmt = $conn->prepare("
        SELECT done FROM user_readiness WHERE user_id = :user_id AND readiness_item_id = :item_id
    ");
    $checkStmt->execute([':user_id' => $user_id, ':item_id' => $readiness_item_id]);
    $existing = $checkStmt->fetch();

    if ($existing) {
        $newDone = $existing['done'] == 1 ? 0 : 1;
        $updateStmt = $conn->prepare("
            UPDATE user_readiness SET done = :done WHERE user_id = :user_id AND readiness_item_id = :item_id
        ");
        $updateStmt->execute([
            ':done' => $newDone,
            ':user_id' => $user_id,
            ':item_id' => $readiness_item_id,
        ]);
    } else {
        $newDone = 1;
        $insertStmt = $conn->prepare("
            INSERT INTO user_readiness (user_id, readiness_item_id, done) VALUES (:user_id, :item_id, :done)
        ");
        $insertStmt->execute([
            ':user_id' => $user_id,
            ':item_id' => $readiness_item_id,
            ':done' => $newDone,
        ]);
    }

    echo json_encode(['success' => true, 'done' => $newDone]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}