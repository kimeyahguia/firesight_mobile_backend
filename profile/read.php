<?php
// profile/read.php
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

require_once __DIR__ . '/../config/db.php';

// ── Bagong check: siguraduhing may valid connection bago tumuloy ──
if (!isset($conn) || !($conn instanceof PDO)) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database connection not established']);
    exit();
}

$user_id = isset($_GET['user_id']) ? (int) $_GET['user_id'] : 0;

if ($user_id <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Missing or invalid user_id']);
    exit();
}

try {
    // ── User info ──
    $userStmt = $conn->prepare("
        SELECT id, full_name, email, avatar_url, barangay, is_verified
        FROM users
        WHERE id = :user_id
    ");
    $userStmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
    $userStmt->execute();
    $user = $userStmt->fetch();

    if (!$user) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'User not found']);
        exit();
    }

    $user['id'] = (int) $user['id'];
    $user['is_verified'] = (int) $user['is_verified'];

    // ── Readiness checklist ──
    $readinessStmt = $conn->prepare("
        SELECT ri.id, ri.label, ri.icon, COALESCE(ur.done, 0) AS done
        FROM readiness_items ri
        LEFT JOIN user_readiness ur
            ON ur.readiness_item_id = ri.id AND ur.user_id = :user_id
        ORDER BY ri.sort_order ASC
    ");
    $readinessStmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
    $readinessStmt->execute();
    $readinessRows = $readinessStmt->fetchAll();

    $readiness = array_map(function ($row) {
        return [
            'id' => (string) $row['id'],
            'label' => $row['label'],
            'icon' => $row['icon'],
            'done' => (int) $row['done'],
        ];
    }, $readinessRows);

    // ── Trusted contacts ──
    $contactsStmt = $conn->prepare("
        SELECT id, name, relation, phone
        FROM trusted_contacts
        WHERE user_id = :user_id
        ORDER BY created_at ASC
    ");
    $contactsStmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
    $contactsStmt->execute();
    $contactRows = $contactsStmt->fetchAll();

    $contacts = array_map(function ($row) {
        return [
            'id' => (string) $row['id'],
            'name' => $row['name'],
            'relation' => $row['relation'],
            'phone' => $row['phone'],
        ];
    }, $contactRows);

    // ── Stats ──
    $reportsStmt = $conn->prepare("SELECT COUNT(*) AS total FROM incidents WHERE user_id = :user_id");
    $reportsStmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
    $reportsStmt->execute();
    $reportsCount = (int) $reportsStmt->fetch()['total'];

    $alertsStmt = $conn->prepare("SELECT COUNT(*) AS total FROM user_alerts_viewed WHERE user_id = :user_id");
    $alertsStmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
    $alertsStmt->execute();
    $alertsCount = (int) $alertsStmt->fetch()['total'];

    $guidesStmt = $conn->prepare("SELECT COUNT(*) AS total FROM user_guides_read WHERE user_id = :user_id");
    $guidesStmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
    $guidesStmt->execute();
    $guidesCount = (int) $guidesStmt->fetch()['total'];

    echo json_encode([
        'success' => true,
        'user' => $user,
        'readiness' => $readiness,
        'contacts' => $contacts,
        'stats' => [
            'reports' => $reportsCount,
            'alertsViewed' => $alertsCount,
            'guidesRead' => $guidesCount,
        ],
    ]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
} catch (Throwable $e) {
    // ── Masasagap na ngayon ang Fatal Errors, hindi lang PDOException ──
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
}