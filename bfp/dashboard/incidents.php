<?php
// firesight_api/bfp/dashboard/incidents.php
// Nagbabalik ng listahan ng fire incidents para sa BFP Incidents screen.

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once __DIR__ . '/../../config/db.php'; // dapat naglalabas ng PDO sa $conn

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit();
}

try {
    // Optional filters — pwedeng dagdagan ng ?status=Pending o ?barangay=Bucana kung kailangan
    $statusFilter = isset($_GET['status']) ? trim($_GET['status']) : null;

    // ── Mahalaga: HUWAG kunin ang buong photo_url (longblob) dito.
    // Para lang malaman ng list card kung may kalakip na larawan, gumagamit
    // tayo ng LENGTH() para makakuha ng byte count na lang, hindi ang buong
    // binary data. Ang full image ay kukunin na lang sa details.php pag
    // binuksan ang isang specific incident (via "View Details").
    $sql = "SELECT
                i.id,
                i.reference_id,
                i.user_id,
                i.title,
                i.description,
                LENGTH(i.photo_url) AS photo_len,
                i.location,
                i.barangay,
                i.street_landmark,
                i.location_details,
                i.latitude,
                i.longitude,
                i.what_is_on_fire,
                i.severity,
                i.incident_type,
                i.people_at_risk,
                i.fire_active,
                i.responders_on_site,
                i.full_name,
                i.contact_number,
                i.status,
                i.created_at,
                i.verified_by,
                i.verified_at,
                vp.full_name AS verified_by_name
            FROM incidents i
            LEFT JOIN bfp_personnel vp ON i.verified_by = vp.id";

    $params = [];

    if ($statusFilter !== null && $statusFilter !== '' && strtolower($statusFilter) !== 'all') {
        $sql .= " WHERE i.status = :status";
        $params[':status'] = $statusFilter;
    }

    $sql .= " ORDER BY i.created_at DESC";

    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $incidents = array_map(function ($row) {
        return [
            'id'                => (string) $row['id'],
            'refId'              => $row['reference_id'],
            'reporter'           => $row['full_name'] ?? 'Unknown',
            'reporterPhone'      => $row['contact_number'] ?? '',
            'reporterBarangay'   => $row['barangay'] ?? '',
            'barangay'           => $row['barangay'] ?? '',
            'location'           => $row['location'] ?? '',
            'type'               => $row['incident_type'] ?? $row['title'],
            'dateTime'           => date('M j, Y · h:i A', strtotime($row['created_at'])),
            'status'             => ucfirst(strtolower($row['status'])),
            'severity'           => $row['severity'] ?? 'Moderate',
            'description'        => $row['description'] ?? '',
            'photoAttached'      => (int) $row['photo_len'] > 0,
            'photoUrl'           => null, // hindi na sinasama sa list; kunin sa details.php
            'causeOfFire'        => $row['what_is_on_fire'],
            'findings'           => $row['location_details'],
            'additionalNotes'    => $row['street_landmark'],
            'latitude'           => $row['latitude'] !== null ? (float) $row['latitude'] : null,
            'longitude'          => $row['longitude'] !== null ? (float) $row['longitude'] : null,
            'peopleAtRisk'       => $row['people_at_risk'],
            'fireActive'         => $row['fire_active'],
            'respondersOnSite'   => $row['responders_on_site'],
            'verifiedByName'     => $row['verified_by_name'],
            'verifiedAt'         => $row['verified_at'] ? date('M j, Y · h:i A', strtotime($row['verified_at'])) : null,
        ];
    }, $rows);

    echo json_encode([
        'success' => true,
        'data'    => $incidents,
    ]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Database error: ' . $e->getMessage(),
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Server error: ' . $e->getMessage(),
    ]);
}