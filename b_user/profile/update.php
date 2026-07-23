<?php
// profile/update.php
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
require_once __DIR__ . '/../../config/db.php';

$user_id = null;
$full_name = null;
$barangay = null;
$avatar_url = null;

if (!empty($_POST)) {
    $user_id = isset($_POST['user_id']) ? (int) $_POST['user_id'] : 0;
    $full_name = $_POST['full_name'] ?? null;
    $barangay = $_POST['barangay'] ?? null;

    if (isset($_FILES['avatar']) && $_FILES['avatar']['error'] === UPLOAD_ERR_OK) {
        $uploadDir = __DIR__ . '/../uploads/avatars/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        $ext = pathinfo($_FILES['avatar']['name'], PATHINFO_EXTENSION);
        $allowedExt = ['jpg', 'jpeg', 'png', 'webp'];
        if (!in_array(strtolower($ext), $allowedExt)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Invalid image format']);
            exit();
        }

        $filename = 'avatar_' . $user_id . '_' . time() . '.' . $ext;
        $destPath = $uploadDir . $filename;

        if (move_uploaded_file($_FILES['avatar']['tmp_name'], $destPath)) {
            // TODO: palitan ng actual local IP niyo (same IP na ginagamit sa BASE_URL)
            $avatar_url = 'http://192.168.1.X/firesight_api/uploads/avatars/' . $filename;
        }
    }
} else {
    $input = json_decode(file_get_contents('php://input'), true);
    $user_id = isset($input['user_id']) ? (int) $input['user_id'] : 0;
    $full_name = $input['full_name'] ?? null;
    $barangay = $input['barangay'] ?? null;
}

if ($user_id <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Missing or invalid user_id']);
    exit();
}

$fields = [];
$params = [':user_id' => $user_id];

if ($full_name !== null) {
    $fields[] = 'full_name = :full_name';
    $params[':full_name'] = $full_name;
}
if ($barangay !== null) {
    $fields[] = 'barangay = :barangay';
    $params[':barangay'] = $barangay;
}
if ($avatar_url !== null) {
    $fields[] = 'avatar_url = :avatar_url';
    $params[':avatar_url'] = $avatar_url;
}

if (empty($fields)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'No fields to update']);
    exit();
}

try {
    $sql = "UPDATE users SET " . implode(', ', $fields) . " WHERE id = :user_id";
    $stmt = $conn->prepare($sql);
    $stmt->execute($params);

    echo json_encode([
        'success' => true,
        'avatar_url' => $avatar_url,
    ]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Update failed: ' . $e->getMessage()]);
}