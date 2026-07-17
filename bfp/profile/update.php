<?php
// ── FOR BFP SIDE ── Update Logged-in Personnel Profile
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

$personnel_id = isset($input['personnel_id']) ? (int) $input['personnel_id'] : 0;
if ($personnel_id <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Missing personnel_id']);
    exit();
}

// Editable fields lang — password at badge_number/verification status
// ay may sarili nang endpoint/flow, hindi dito
$editable = ['full_name', 'rank_title', 'email', 'phone', 'position'];

$fields = [];
$params = [':id' => $personnel_id];

foreach ($editable as $field) {
    if (array_key_exists($field, $input)) {
        $value = trim((string) $input[$field]);
        $fields[] = "$field = :$field";
        $params[":$field"] = $value;
    }
}

if (empty($fields)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'No fields to update']);
    exit();
}

// Basic email format check kung binago ang email
if (isset($input['email']) && $input['email'] !== '') {
    if (!filter_var($input['email'], FILTER_VALIDATE_EMAIL)) {
        http_response_code(422);
        echo json_encode(['success' => false, 'message' => 'Invalid email format']);
        exit();
    }
}

try {
    $sql = "UPDATE bfp_personnel SET " . implode(', ', $fields) . " WHERE id = :id";
    $stmt = $conn->prepare($sql);
    $stmt->execute($params);

    // Ibalik yung updated row para direktang ma-refresh ang UI nang walang extra fetch
    $stmt2 = $conn->prepare("
        SELECT id, full_name, rank_title, email, phone, avatar_url, position, badge_number, is_verified, created_at
        FROM bfp_personnel
        WHERE id = :id
        LIMIT 1
    ");
    $stmt2->execute([':id' => $personnel_id]);
    $row = $stmt2->fetch(PDO::FETCH_ASSOC);

    if (!$row) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Personnel not found after update.']);
        exit();
    }

    $data = [
        'id'          => (string) $row['id'],
        'fullName'    => $row['full_name'],
        'rankTitle'   => $row['rank_title'] ?? '',
        'email'       => $row['email'],
        'phone'       => $row['phone'],
        'avatarUrl'   => $row['avatar_url'],
        'position'    => $row['position'],
        'badgeNumber' => $row['badge_number'],
        'isVerified'  => (bool) $row['is_verified'],
        'memberSince' => date('F Y', strtotime($row['created_at'])),
    ];

    echo json_encode(['success' => true, 'data' => $data]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Failed to update profile.']);
}