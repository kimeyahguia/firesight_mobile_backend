<?php
// profile/update_phone.php
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
require_once __DIR__ . '/../../config/db.php';

$input = json_decode(file_get_contents('php://input'), true);
$user_id = isset($input['user_id']) ? (int) $input['user_id'] : 0;
$new_phone = trim($input['phone'] ?? '');
$current_password = $input['current_password'] ?? '';

if ($user_id <= 0 || $new_phone === '' || $current_password === '') {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'All fields are required']);
    exit();
}

try {
    // Verify password muna bago palitan ang phone (security, dahil login credential din 'to)
    $verifyStmt = $conn->prepare("SELECT password FROM users WHERE id = :user_id");
    $verifyStmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
    $verifyStmt->execute();
    $row = $verifyStmt->fetch();

    if (!$row) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'User not found']);
        exit();
    }

    if (!password_verify($current_password, $row['password'])) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Password is incorrect']);
        exit();
    }

    // Check kung ginagamit na ng ibang user itong phone number
    $checkStmt = $conn->prepare("SELECT id FROM users WHERE phone = :phone AND id != :user_id");
    $checkStmt->execute([':phone' => $new_phone, ':user_id' => $user_id]);
    if ($checkStmt->fetch()) {
        http_response_code(409);
        echo json_encode(['success' => false, 'message' => 'This phone number is already in use']);
        exit();
    }

    $updateStmt = $conn->prepare("UPDATE users SET phone = :phone WHERE id = :user_id");
    $updateStmt->execute([':phone' => $new_phone, ':user_id' => $user_id]);

    echo json_encode(['success' => true, 'phone' => $new_phone]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}