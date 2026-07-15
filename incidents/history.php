<?php
//history.php
header('Content-Type: application/json');
require_once '../config/db.php';

$barangayId = $_GET['barangay_id'] ?? null;

if (!$barangayId) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'barangay_id is required.']);
    exit;
}

try {
    $stmt = $conn->prepare("
        SELECT id, reference_id, title, description, status, severity, created_at, resolved_at
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
}