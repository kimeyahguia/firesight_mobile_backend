<?php
// firesight_api/bfp/incidents/details.php
// Nagbabalik ng FULL details (kasama picture) ng isang partikular na incident.

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

$incidentId = isset($_GET['id']) ? trim($_GET['id']) : null;

if (!$incidentId) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Missing incident id']);
    exit();
}

// ── Helper: nagko-convert ng raw blob (longblob column) papuntang
// base64 data URI para direktang magamit ng <Image source={{ uri }} />
// sa frontend, walang kailangang extra file-serving endpoint.
function blobToDataUri($blob, string $mime = 'image/jpeg'): ?string {
    if ($blob === null || $blob === '') return null;
    return 'data:' . $mime . ';base64,' . base64_encode($blob);
}

try {
    $sql = "SELECT
                i.id,
                i.reference_id,
                i.user_id,
                i.title,
                i.description,
                i.photo_url,
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
            LEFT JOIN bfp_personnel vp ON i.verified_by = vp.id
            WHERE i.id = :id
            LIMIT 1";

    $stmt = $conn->prepare($sql);
    $stmt->execute([':id' => $incidentId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$row) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Incident not found']);
        exit();
    }

    $photoDataUri = blobToDataUri($row['photo_url']);

    $incident = [
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
        'photoAttached'      => $photoDataUri !== null,
        'photoUrl'           => $photoDataUri,
        'photos'             => $photoDataUri !== null
            ? [['url' => $photoDataUri, 'caption' => null, 'createdAt' => null]]
            : [],
        'actions'            => [],
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

    echo json_encode([
        'success' => true,
        'data'    => $incident,
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