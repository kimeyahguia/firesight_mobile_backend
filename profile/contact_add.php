<?php
// profile/contact_add.php
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

include_once '../config/db.php';

$input = json_decode(file_get_contents('php://input'), true);
$user_id = isset($input['user_id']) ? (int) $input['user_id'] : 0;
$name = trim($input['name'] ?? '');
$relation = trim($input['relation'] ?? '');
$phone = trim($input['phone'] ?? '');

if ($user_id <= 0 || $name === '' || $relation === '' || $phone === '') {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'All fields are required']);
    exit();
}

try {
    $stmt = $conn->prepare("
        INSERT INTO trusted_contacts (user_id, name, relation, phone) VALUES (:user_id, :name, :relation, :phone)
    ");
    $stmt->execute([
        ':user_id' => $user_id,
        ':name' => $name,
        ':relation' => $relation,
        ':phone' => $phone,
    ]);

    echo json_encode(['success' => true, 'id' => $conn->lastInsertId()]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Failed to add contact: ' . $e->getMessage()]);
}