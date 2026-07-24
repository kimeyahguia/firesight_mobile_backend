<?php
//recent.php
error_reporting(E_ERROR | E_PARSE);
ini_set('display_errors', '0');

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

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

try {
    $query = "SELECT
                id,
                reference_id,
                title,
                description,
                incident_type AS type,
                barangay,
                severity,
                status,
                created_at
              FROM incidents
              WHERE created_at >= DATE_SUB(NOW(), INTERVAL 14 DAY)
              ORDER BY created_at DESC
              LIMIT 20";

    $stmt = $conn->prepare($query);
    $stmt->execute();

    $incidents = array();

    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $row['time_ago'] = date("g:i A", strtotime($row['created_at']));
        $incidents[] = $row;
    }

    echo json_encode([
        "success" => true,
        "data" => $incidents
    ]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        "success" => false,
        "message" => "Database error while fetching recent incidents."
    ]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        "success" => false,
        "message" => "Server error while fetching recent incidents."
    ]);
}