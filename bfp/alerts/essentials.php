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
    $stmt = $conn->query("SELECT id, name, category, quantity, unit, condition_status FROM essentials ORDER BY category ASC, name ASC");
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $data = array_map(function ($r) {
        return [
            'id' => (string) $r['id'],
            'name' => $r['name'],
            'category' => $r['category'],
            'quantity' => (int) $r['quantity'],
            'unit' => $r['unit'],
            'condition' => $r['condition_status'],
        ];
    }, $rows);

    echo json_encode(['success' => true, 'data' => $data]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}