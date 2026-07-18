<?php
//recent.php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

require_once __DIR__ . '/../../config/db.php';

try {
    $query = "SELECT 
                id, 
                reference_id,
                title, 
                description, 
                incident_type AS type, 
                barangay,
                status, 
                created_at 
              FROM incidents 
              ORDER BY created_at DESC 
              LIMIT 10";

    $stmt = $conn->prepare($query); // ginamit ko yung $conn, dapat match sa db.php mo
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
        "message" => "Database error: " . $e->getMessage()
    ]);
}
?>