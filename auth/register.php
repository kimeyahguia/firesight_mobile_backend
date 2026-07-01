<?php
// firesight_api/auth/register.php
error_reporting(E_ALL);
ini_set('display_errors', 1);

include_once '../config/db.php';

$data = json_decode(file_get_contents("php://input"));

if (empty($data->full_name) || empty($data->phone) || empty($data->password)) {
    http_response_code(400);
    echo json_encode(["success" => false, "message" => "All fields are required."]);
    exit();
}

// Server-side validation (client-side can be bypassed)
if (!preg_match('/^(09|\+639)\d{9}$/', $data->phone)) {
    http_response_code(400);
    echo json_encode(["success" => false, "message" => "Invalid phone number format."]);
    exit();
}

if (strlen($data->password) < 8) {
    http_response_code(400);
    echo json_encode(["success" => false, "message" => "Password must be at least 8 characters."]);
    exit();
}

$checkQuery = "SELECT id FROM users WHERE phone = :phone LIMIT 1";
$checkStmt = $conn->prepare($checkQuery);
$checkStmt->bindParam(":phone", $data->phone);
$checkStmt->execute();

if ($checkStmt->fetch()) {
    http_response_code(409);
    echo json_encode(["success" => false, "message" => "Phone number already registered."]);
    exit();
}

$hashedPassword = password_hash($data->password, PASSWORD_DEFAULT);
$email = $data->phone . "@firesight.local";

$query = "INSERT INTO users (full_name, phone, email, password, is_verified) 
          VALUES (:full_name, :phone, :email, :password, 0)";
$stmt = $conn->prepare($query);
$stmt->bindParam(":full_name", $data->full_name);
$stmt->bindParam(":phone", $data->phone);
$stmt->bindParam(":email", $email);
$stmt->bindParam(":password", $hashedPassword);

if ($stmt->execute()) {
    $newUserId = $conn->lastInsertId();
    echo json_encode([
        "success" => true,
        "message" => "Account created successfully.",
        "user" => ["id" => $newUserId, "full_name" => $data->full_name, "phone" => $data->phone]
    ]);
} else {
    http_response_code(500);
    echo json_encode(["success" => false, "message" => "Failed to create account."]);
}
?>