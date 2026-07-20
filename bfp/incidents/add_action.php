<?php
// firesight_api/bfp/incidents/add_action.php
// Nagdadagdag ng responder action/update entry (may kasamang optional na photo evidence).
// Multipart/form-data POST: incident_id, personnel_id, action_type (optional), note (optional),
// at "photos[]" (0 or more image files) o "photo" (single file, legacy/simple clients).

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

$incident_id  = isset($_POST['incident_id']) ? (int) $_POST['incident_id'] : 0;
$personnel_id = isset($_POST['personnel_id']) ? (int) $_POST['personnel_id'] : 0;
$actionType   = isset($_POST['action_type']) ? trim($_POST['action_type']) : 'update';
$note         = isset($_POST['note']) ? trim($_POST['note']) : '';

if ($actionType === '') {
    $actionType = 'update';
}

if ($incident_id <= 0 || $personnel_id <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Missing incident_id or personnel_id']);
    exit();
}

$hasPhotosArray = !empty($_FILES['photos']['name'][0] ?? null);
$hasSinglePhoto = !empty($_FILES['photo']['name'] ?? null);

if ($note === '' && !$hasPhotosArray && !$hasSinglePhoto) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Maglagay ng note o photo bago mag-submit.']);
    exit();
}

try {
    $check = $conn->prepare("SELECT id FROM incidents WHERE id = :id LIMIT 1");
    $check->execute([':id' => $incident_id]);
    if (!$check->fetch()) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Incident not found.']);
        exit();
    }

    $personnelCheck = $conn->prepare("SELECT full_name FROM bfp_personnel WHERE id = :id LIMIT 1");
    $personnelCheck->execute([':id' => $personnel_id]);
    $personnelRow = $personnelCheck->fetch(PDO::FETCH_ASSOC);
    if (!$personnelRow) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Personnel not found.']);
        exit();
    }

    $conn->beginTransaction();

    $insertAction = $conn->prepare("
        INSERT INTO incident_actions (incident_id, personnel_id, action_type, note)
        VALUES (:incident_id, :personnel_id, :action_type, :note)
    ");
    $insertAction->execute([
        ':incident_id'  => $incident_id,
        ':personnel_id' => $personnel_id,
        ':action_type'  => $actionType,
        ':note'         => $note !== '' ? $note : null,
    ]);
    $actionId = (int) $conn->lastInsertId();

    // ── Handle photo uploads ──
    $uploadDir = __DIR__ . '/../../uploads/incident_actions/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }

    $filesToProcess = [];

    if ($hasPhotosArray) {
        $count = count($_FILES['photos']['name']);
        for ($i = 0; $i < $count; $i++) {
            if ($_FILES['photos']['error'][$i] === UPLOAD_ERR_OK) {
                $filesToProcess[] = [
                    'tmp_name' => $_FILES['photos']['tmp_name'][$i],
                    'name'     => $_FILES['photos']['name'][$i],
                ];
            }
        }
    } elseif ($hasSinglePhoto && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
        $filesToProcess[] = [
            'tmp_name' => $_FILES['photo']['tmp_name'],
            'name'     => $_FILES['photo']['name'],
        ];
    }

    $allowedExt = ['jpg', 'jpeg', 'png', 'webp'];
    $insertPhoto = $conn->prepare(
        "INSERT INTO incident_action_photos (action_id, photo_url) VALUES (:action_id, :photo_url)"
    );

    $savedPhotoUrls = [];

    foreach ($filesToProcess as $file) {
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, $allowedExt, true)) {
            continue; // skip invalid/unsupported file types
        }
        $filename = 'action_' . $actionId . '_' . uniqid() . '.' . $ext;
        $destPath = $uploadDir . $filename;

        if (move_uploaded_file($file['tmp_name'], $destPath)) {
            $relativeUrl = 'uploads/incident_actions/' . $filename;
            $insertPhoto->execute([':action_id' => $actionId, ':photo_url' => $relativeUrl]);
            $savedPhotoUrls[] = $relativeUrl;
        }
    }

    $conn->commit();

    echo json_encode([
        'success' => true,
        'data' => [
            'id'         => (string) $actionId,
            'actionType' => $actionType,
            'note'       => $note !== '' ? $note : null,
            'photos'     => $savedPhotoUrls,
            'personnel'  => $personnelRow['full_name'],
            'createdAt'  => date('M j, Y · h:i A'),
        ],
    ]);
} catch (PDOException $e) {
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Failed to add action: ' . $e->getMessage()]);
} catch (Exception $e) {
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
}