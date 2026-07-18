<?php
// profile/change_password.php
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

require_once __DIR__ . '/../../config/db.php';

$input = json_decode(file_get_contents('php://input'), true);
$user_id = isset($input['user_id']) ? (int) $input['user_id'] : 0;
$current_password = $input['current_password'] ?? '';
$new_password = $input['new_password'] ?? '';

if ($user_id <= 0 || $current_password === '' || $new_password === '') {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'All fields are required']);
    exit();
}

if (strlen($new_password) < 8) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'New password must be at least 8 characters']);
    exit();
}

try {
    $stmt = $conn->prepare("SELECT password FROM users WHERE id = :user_id");
    $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
    $stmt->execute();
    $row = $stmt->fetch();

    if (!$row) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'User not found']);
        exit();
    }

    if (!password_verify($current_password, $row['password'])) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Current password is incorrect']);
        exit();
    }

    $newHash = password_hash($new_password, PASSWORD_DEFAULT);
    $updateStmt = $conn->prepare("UPDATE users SET password = :password WHERE id = :user_id");
    $updateStmt->execute([':password' => $newHash, ':user_id' => $user_id]);

    echo json_encode(['success' => true]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}