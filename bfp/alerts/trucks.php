<?php
error_reporting(E_ERROR | E_PARSE);
ini_set('display_errors', '0');
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
require_once __DIR__ . '/../../config/db.php';

if (!isset($conn) || !($conn instanceof PDO)) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database connection not established']);
    exit();
}

try {
    $stmt = $conn->query("SELECT id, name, plate_no, status, assigned_to, water_capacity FROM fire_trucks ORDER BY name ASC");
    $trucks = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $crewStmt = $conn->prepare("SELECT full_name FROM bfp_personnel WHERE assigned_truck_id = :truckId ORDER BY full_name ASC");

    $data = array_map(function ($t) use ($crewStmt) {
        $crewStmt->execute([':truckId' => $t['id']]);
        $crew = array_column($crewStmt->fetchAll(PDO::FETCH_ASSOC), 'full_name');
        return [
            'id' => (string) $t['id'],
            'name' => $t['name'],
            'plateNo' => $t['plate_no'],
            'status' => $t['status'],
            'assignedTo' => $t['assigned_to'],
            'waterCapacity' => $t['water_capacity'],
            'crew' => $crew,
        ];
    }, $trucks);

    echo json_encode(['success' => true, 'data' => $data]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}