<?php
// profile/read.php
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

require_once __DIR__ . '/../config/db.php';

if (!isset($conn) || !($conn instanceof PDO)) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database connection not established']);
    exit();
}

$user_id = isset($_GET['user_id']) ? (int) $_GET['user_id'] : 0;
$role = isset($_GET['role']) ? $_GET['role'] : 'user';

if ($user_id <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Missing or invalid user_id']);
    exit();
}

try {
    // ── BFP PERSONNEL: simplified profile, walang readiness/contacts/incidents ──
    if ($role === 'personnel') {
        $stmt = $conn->prepare("
            SELECT id, full_name, email, phone, avatar_url, position, badge_number, is_verified
            FROM bfp_personnel
            WHERE id = :user_id
        ");
        $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
        $stmt->execute();
        $personnel = $stmt->fetch();

        if (!$personnel) {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'Personnel not found']);
            exit();
        }

        $personnel['id'] = (int) $personnel['id'];
        $personnel['is_verified'] = (int) $personnel['is_verified'];
        $personnel['role'] = 'personnel';

        echo json_encode([
            'success' => true,
            'user' => $personnel,
            'readiness' => [],
            'contacts' => [],
            'stats' => [
                'reports' => 0,
                'alertsViewed' => 0,
                'guidesRead' => 0,
            ],
        ]);
        exit();
    }

    // ── REGULAR USER: original full profile query ──
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
    $user['role'] = 'user';

    // ── Readiness checklist (direktang galing sa user_readiness) ──
    $readinessStmt = $conn->prepare("
        SELECT id, label, icon, done
        FROM user_readiness
        WHERE user_id = :user_id
        ORDER BY id ASC
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
        ORDER BY id ASC
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

    $reportsStmt = $conn->prepare("SELECT COUNT(*) AS total FROM incidents WHERE user_id = :user_id");
    $reportsStmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
    $reportsStmt->execute();
    $reportsCount = (int) $reportsStmt->fetch()['total'];

    $alertsStmt = $conn->prepare("SELECT COUNT(*) AS total FROM user_alerts_viewed");
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
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
}