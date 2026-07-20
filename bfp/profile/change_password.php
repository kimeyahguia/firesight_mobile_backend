<?php
// ── FOR BFP SIDE ── Change Password ng Logged-in Personnel
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once __DIR__ . '/../../config/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit();
}

$input = json_decode(file_get_contents('php://input'), true);

$personnel_id     = isset($input['personnel_id']) ? (int) $input['personnel_id'] : 0;
$current_password = isset($input['current_password']) ? (string) $input['current_password'] : '';
$new_password      = isset($input['new_password']) ? (string) $input['new_password'] : '';

if ($personnel_id <= 0 || $current_password === '' || $new_password === '') {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Missing required fields']);
    exit();
}

if (strlen($new_password) < 8) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'Kailangan ng bagong password ng hindi bababa sa 8 characters.']);
    exit();
}

try {
    $stmt = $conn->prepare("SELECT password FROM bfp_personnel WHERE id = :id LIMIT 1");
    $stmt->execute([':id' => $personnel_id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$row) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Personnel not found.']);
        exit();
    }

    if (!password_verify($current_password, $row['password'])) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Mali ang kasalukuyang password.']);
        exit();
    }

    $newHash = password_hash($new_password, PASSWORD_DEFAULT);

    $update = $conn->prepare("UPDATE bfp_personnel SET password = :password WHERE id = :id");
    $update->execute([':password' => $newHash, ':id' => $personnel_id]);

    echo json_encode(['success' => true, 'message' => 'Na-update na ang password.']);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Failed to change password.']);
}