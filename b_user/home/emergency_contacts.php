<?php
// home/emergency_contacts.php
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
    $stmt = $conn->prepare("
        SELECT id, name, role, phone, icon
        FROM emergency_contacts
        ORDER BY sort_order ASC, id ASC
    ");
    $stmt->execute();
    $rows = $stmt->fetchAll();

    $contacts = array_map(function ($row) {
        return [
            'id'    => (string) $row['id'],
            'name'  => $row['name'],
            'role'  => $row['role'],
            'phone' => $row['phone'],
            'icon'  => $row['icon'],
        ];
    }, $rows);

    echo json_encode([
        'success'  => true,
        'contacts' => $contacts,
    ]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}