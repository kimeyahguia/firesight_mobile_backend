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
    $trucksTotal = (int) $conn->query("SELECT COUNT(*) FROM fire_trucks")->fetchColumn();
    $trucksDeployed = (int) $conn->query("SELECT COUNT(*) FROM fire_trucks WHERE status = 'Deployed'")->fetchColumn();
    $personnelTotal = (int) $conn->query("SELECT COUNT(*) FROM bfp_personnel")->fetchColumn();
    $personnelOnDuty = (int) $conn->query("SELECT COUNT(*) FROM bfp_personnel WHERE status != 'Off Duty'")->fetchColumn();
    $needAttention = (int) $conn->query("SELECT COUNT(*) FROM essentials WHERE condition_status != 'Good'")->fetchColumn();

    echo json_encode(['success' => true, 'data' => [
        'trucksDeployed' => $trucksDeployed,
        'trucksTotal' => $trucksTotal,
        'personnelOnDuty' => $personnelOnDuty,
        'personnelTotal' => $personnelTotal,
        'needAttention' => $needAttention,
    ]]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}