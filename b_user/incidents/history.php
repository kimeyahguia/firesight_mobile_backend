<?php
//history.php
error_reporting(E_ERROR | E_PARSE);
ini_set('display_errors', '0');

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

register_shutdown_function(function () {
    $error = error_get_last();
    if ($error && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
        if (!headers_sent()) {
            http_response_code(500);
            header('Content-Type: application/json');
        }
        echo json_encode(['success' => false, 'message' => 'Fatal server error: ' . $error['message']]);
    }
});

require_once __DIR__ . '/../../config/db.php';

$barangayId = $_GET['barangay_id'] ?? null;

if (!$barangayId) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'barangay_id is required.']);
    exit;
}

try {
    $stmt = $conn->prepare("
        SELECT
            id,
            reference_id,
            title,
            incident_type AS category,
            severity,
            status,
            created_at AS incident_date,
            resolved_at AS verified_date
        FROM incidents
        WHERE barangay = (SELECT name FROM barangays WHERE id = :barangay_id)
        ORDER BY created_at DESC
    ");
    $stmt->execute(['barangay_id' => $barangayId]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(['success' => true, 'data' => $rows]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Failed to fetch incident history.']);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error while fetching incident history.']);
}