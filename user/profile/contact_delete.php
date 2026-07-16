<?php
// profile/contact_delete.php
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

include_once '../config/db.php';

$input = json_decode(file_get_contents('php://input'), true);
$contact_id = isset($input['contact_id']) ? (int) $input['contact_id'] : 0;
$user_id = isset($input['user_id']) ? (int) $input['user_id'] : 0;

if ($contact_id <= 0 || $user_id <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Missing contact_id or user_id']);
    exit();
}

try {
    $stmt = $conn->prepare("
        DELETE FROM trusted_contacts WHERE id = :contact_id AND user_id = :user_id
    ");
    $stmt->execute([':contact_id' => $contact_id, ':user_id' => $user_id]);

    if ($stmt->rowCount() > 0) {
        echo json_encode(['success' => true]);
    } else {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Contact not found or not yours']);
    }
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}