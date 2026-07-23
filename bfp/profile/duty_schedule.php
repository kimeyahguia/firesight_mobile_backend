<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once __DIR__ . '/../../config/db.php';

$personnel_id = isset($_GET['personnel_id']) ? (int)$_GET['personnel_id'] : 0;

if ($personnel_id <= 0) {
    http_response_code(400);
    echo json_encode([
        "success" => false,
        "message" => "Missing personnel_id"
    ]);
    exit();
}

try {

    $stmt = $conn->prepare("
        SELECT
            duty_date,
            shift,
            start_time,
            end_time,
            station,
            remarks,
            status
        FROM bfp_duty_schedule
        WHERE personnel_id = :personnel_id
        ORDER BY duty_date DESC
        LIMIT 30
    ");

    $stmt->execute([
        ':personnel_id' => $personnel_id
    ]);

    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $data = [];

    foreach ($rows as $row) {
        $data[] = [
            "duty_date" => $row["duty_date"],
            "shift" => $row["shift"],
            "start_time" => date("h:i A", strtotime($row["start_time"])),
            "end_time" => date("h:i A", strtotime($row["end_time"])),
            "station" => $row["station"],
            "remarks" => $row["remarks"],
            "status" => $row["status"]
        ];
    }

    echo json_encode([
        "success" => true,
        "data" => $data
    ]);

} catch (PDOException $e) {

    http_response_code(500);

    echo json_encode([
        "success" => false,
        "message" => $e->getMessage()
    ]);
}