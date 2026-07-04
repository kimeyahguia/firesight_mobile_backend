<?php
// firesight_api/auth/login.php
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

include_once '../config/db.php';
require_once '../config/audit_logger.php';

$data = json_decode(file_get_contents("php://input"));

if (empty($data->phone) || empty($data->password)) {
    http_response_code(400);
    echo json_encode(["success" => false, "message" => "Phone and password required."]);
    exit();
}

try {
    // 1. Check users table first
    $query = "SELECT id, full_name, email, phone, password, avatar_url, barangay, is_verified 
              FROM users WHERE phone = :phone LIMIT 1";

    $stmt = $conn->prepare($query);
    $stmt->bindParam(":phone", $data->phone);
    $stmt->execute();
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user && password_verify($data->password, $user['password'])) {
        unset($user['password']);
        $user['role'] = 'user';
        logAudit($conn, $user['id'], 'user', 'login', 'User logged in successfully.');
        echo json_encode([
            "success" => true,
            "message" => "Login successful.",
            "user" => $user
        ]);
        exit();
    }

    // 2. If not found/matched in users, check bfp_personnel table
    $query2 = "SELECT id, full_name, email, phone, password, avatar_url, position, badge_number, is_verified 
               FROM bfp_personnel WHERE phone = :phone LIMIT 1";

    $stmt2 = $conn->prepare($query2);
    $stmt2->bindParam(":phone", $data->phone);
    $stmt2->execute();
    $personnel = $stmt2->fetch(PDO::FETCH_ASSOC);

    if ($personnel && password_verify($data->password, $personnel['password'])) {
        unset($personnel['password']);
        $personnel['role'] = 'personnel';
        logAudit($conn, $personnel['id'], 'personnel', 'login', 'BFP personnel logged in successfully.');
        echo json_encode([
            "success" => true,
            "message" => "Login successful.",
            "user" => $personnel
        ]);
        exit();
    }

    // 3. Wala talaga sa parehong table / mali ang password
    logAudit($conn, 0, 'system', 'login_failed', "Failed login attempt for phone: {$data->phone}");
    http_response_code(401);
    echo json_encode(["success" => false, "message" => "Invalid phone or password."]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(["success" => false, "message" => "Database error: " . $e->getMessage()]);
}