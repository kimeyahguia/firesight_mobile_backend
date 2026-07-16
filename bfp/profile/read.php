<?php
// ── FOR BFP SIDE ── Logged-in Personnel Profile
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once __DIR__ . '/../../config/db.php';

$personnel_id = isset($_GET['personnel_id']) ? (int) $_GET['personnel_id'] : 0;

if ($personnel_id <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Missing personnel_id']);
    exit();
}

try {
    // Sadyang hindi kasama ang `password` dito — kailanman ay hindi dapat
    // umalis ang password hash papunta sa client, kahit hashed na.
    $stmt = $conn->prepare("
        SELECT id, full_name, rank_title, email, phone, avatar_url, position, badge_number, is_verified, created_at
        FROM bfp_personnel
        WHERE id = :id
        LIMIT 1
    ");
    $stmt->execute([':id' => $personnel_id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$row) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Personnel not found.']);
        exit();
    }

    $data = [
        'id' => (string) $row['id'],
        'fullName' => $row['full_name'],
        'rankTitle' => $row['rank_title'] ?? '',
        'email' => $row['email'],
        'phone' => $row['phone'],
        'avatarUrl' => $row['avatar_url'],
        'position' => $row['position'],
        'badgeNumber' => $row['badge_number'],
        'isVerified' => (bool) $row['is_verified'],
        'memberSince' => date('F Y', strtotime($row['created_at'])),
    ];

    echo json_encode(['success' => true, 'data' => $data]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Failed to load profile.']);
}