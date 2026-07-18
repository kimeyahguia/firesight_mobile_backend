<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
require_once __DIR__ . '/../../config/db.php';

try {
    $stmt = $conn->query("SELECT * FROM bfp_station_info ORDER BY id DESC LIMIT 1");
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$row) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'No station info found.']);
        exit();
    }

    $data = [
        'name' => $row['name'],
        'tagline' => $row['tagline'],
        'address' => $row['address'],
        'contactNumber' => $row['contact_number'],
        'emergencyHotline' => $row['emergency_hotline'],
        'officeHours' => $row['office_hours'],
        'established' => $row['established'],
        'barangaysCovered' => (int) $row['barangays_covered'],
        'mission' => $row['mission'],
        'vision' => $row['vision'],
        'services' => json_decode($row['services'], true) ?? [],
    ];

    echo json_encode(['success' => true, 'data' => $data]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Failed to load station info.']);
}