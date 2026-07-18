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
    $stmt = $conn->query("
        SELECT p.id, p.full_name, p.rank_title, p.status, p.assigned_to, t.name AS assigned_truck_name
        FROM bfp_personnel p
        LEFT JOIN fire_trucks t ON t.id = p.assigned_truck_id
        ORDER BY FIELD(p.status, 'Deployed', 'On Duty', 'Off Duty'), p.full_name ASC
    ");
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $data = array_map(function ($p) {
        return [
            'id' => (string) $p['id'],
            'name' => $p['full_name'],
            'rank' => $p['rank_title'],
            'status' => $p['status'],
            'assignedTruck' => $p['assigned_truck_name'],
            'assignedTo' => $p['assigned_to'],
        ];
    }, $rows);

    echo json_encode(['success' => true, 'data' => $data]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}