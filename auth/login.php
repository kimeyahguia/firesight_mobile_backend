<?php
// firesight_api/auth/login.php
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

include_once '../config/db.php';

$data = json_decode(file_get_contents("php://input"));

if (empty($data->phone) || empty($data->password)) {
    http_response_code(400);
    echo json_encode(["success" => false, "message" => "Phone and password required."]);
    exit();
}

try {
    $query = "SELECT id, full_name, email, phone, password, avatar_url, barangay, is_verified 
              FROM users WHERE phone = :phone LIMIT 1";

    $stmt = $conn->prepare($query);
    $stmt->bindParam(":phone", $data->phone);
    $stmt->execute();
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        http_response_code(401);
        echo json_encode(["success" => false, "message" => "Account not found."]);
        exit();
    }

    if (!password_verify($data->password, $user['password'])) {
        http_response_code(401);
        echo json_encode(["success" => false, "message" => "Incorrect password."]);
        exit();
    }

    unset($user['password']);

    echo json_encode([
        "success" => true,
        "message" => "Login successful.",
        "user" => $user
    ]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(["success" => false, "message" => "Database error: " . $e->getMessage()]);
}