<?php
// incidents/submit_report.php

error_reporting(E_ERROR | E_PARSE); // itago ang notices/warnings sa output
ini_set('display_errors', '0');      // huwag i-echo errors sa body

header("Content-Type: application/json");
require_once __DIR__ . '/../../config/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(["success" => false, "message" => "Method not allowed"]);
    exit;
}

// ── Generate unique Reference ID (e.g. FS-20260707-1234) ──
function generateReferenceId() {
    $date = date("Ymd");
    $rand = rand(1000, 9999);
    return "FS-{$date}-{$rand}";
}

// ── Handle photo upload (if present) ──
$photoUrl = null;

if (isset($_FILES['photo']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
    $uploadDir = __DIR__ . '/../uploads/incidents/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }

    $allowedTypes = ['image/jpeg', 'image/png', 'image/jpg'];
    $fileType = $_FILES['photo']['type'];

    if (!in_array($fileType, $allowedTypes)) {
        http_response_code(400);
        echo json_encode(["success" => false, "message" => "Invalid photo format. Only JPG/PNG allowed."]);
        exit;
    }

    $ext = pathinfo($_FILES['photo']['name'], PATHINFO_EXTENSION);
    $fileName = "incident_" . time() . "_" . rand(1000, 9999) . "." . $ext;
    $targetPath = $uploadDir . $fileName;

    if (move_uploaded_file($_FILES['photo']['tmp_name'], $targetPath)) {
        $photoUrl = "uploads/incidents/" . $fileName;
    } else {
        http_response_code(500);
        echo json_encode(["success" => false, "message" => "Failed to save uploaded photo."]);
        exit;
    }
} elseif (isset($_FILES['photo']) && $_FILES['photo']['error'] !== UPLOAD_ERR_NO_FILE) {
    // May photo field pero may upload error (too large, partial upload, etc.)
    http_response_code(400);
    echo json_encode([
        "success" => false,
        "message" => "Photo upload error (code {$_FILES['photo']['error']}). Baka lumagpas sa upload_max_filesize ng PHP."
    ]);
    exit;
}

// ── Get text fields ──
$userId            = $_POST['user_id'] ?? null;
$barangay          = trim($_POST['barangay'] ?? '');
$streetLandmark    = trim($_POST['street_landmark'] ?? '');
$locationDetails   = trim($_POST['location_details'] ?? '');
$latitude          = $_POST['latitude'] ?? null;
$longitude         = $_POST['longitude'] ?? null;
$whatIsOnFire      = trim($_POST['what_is_on_fire'] ?? '');
$severity          = $_POST['severity'] ?? 'Moderate';
$incidentType      = $_POST['incident_type'] ?? '';
$description       = trim($_POST['description'] ?? '');
$peopleAtRisk      = $_POST['people_at_risk'] ?? 'Unsure';
$fireActive        = $_POST['fire_active'] ?? 'Yes';
$respondersOnSite  = $_POST['responders_on_site'] ?? 'No';
$fullName          = trim($_POST['full_name'] ?? '');
$contactNumber     = trim($_POST['contact_number'] ?? '');

// ── Validate required fields (specific per-field messages, hindi generic) ──
$missingFields = [];
if (empty($userId)) $missingFields[] = 'user_id';
if (empty($barangay)) $missingFields[] = 'barangay';
if (empty($streetLandmark)) $missingFields[] = 'street_landmark';
if (empty($incidentType)) $missingFields[] = 'incident_type';

if (!empty($missingFields)) {
    http_response_code(400);
    echo json_encode([
        "success" => false,
        "message" => "Missing required fields: " . implode(', ', $missingFields)
    ]);
    exit;
}

// ── Validate DB connection exists before proceeding ──
if (!isset($conn) || $conn === null) {
    http_response_code(500);
    echo json_encode([
        "success" => false,
        "message" => "Database connection not available. Check db.php config."
    ]);
    exit;
}

$referenceId = generateReferenceId();

// Auto-build title + location (para kasya sa existing columns mo)
$title = $incidentType . ($whatIsOnFire ? " — " . $whatIsOnFire : "");
$location = $streetLandmark . ", " . $barangay;

try {
    $stmt = $conn->prepare("
        INSERT INTO incidents (
            reference_id, user_id, title, description, location,
            photo_url, barangay, street_landmark, location_details,
            latitude, longitude, what_is_on_fire, severity, incident_type,
            people_at_risk, fire_active, responders_on_site,
            full_name, contact_number, status
        ) VALUES (
            :reference_id, :user_id, :title, :description, :location,
            :photo_url, :barangay, :street_landmark, :location_details,
            :latitude, :longitude, :what_is_on_fire, :severity, :incident_type,
            :people_at_risk, :fire_active, :responders_on_site,
            :full_name, :contact_number, 'pending'
        )
    ");

    $stmt->execute([
        ':reference_id'       => $referenceId,
        ':user_id'            => $userId,
        ':title'              => $title,
        ':description'        => $description,
        ':location'           => $location,
        ':photo_url'          => $photoUrl,
        ':barangay'           => $barangay,
        ':street_landmark'    => $streetLandmark,
        ':location_details'   => $locationDetails,
        ':latitude'           => $latitude,
        ':longitude'          => $longitude,
        ':what_is_on_fire'    => $whatIsOnFire,
        ':severity'           => $severity,
        ':incident_type'      => $incidentType,
        ':people_at_risk'     => $peopleAtRisk,
        ':fire_active'        => $fireActive,
        ':responders_on_site' => $respondersOnSite,
        ':full_name'          => $fullName,
        ':contact_number'     => $contactNumber,
    ]);

    if ($stmt->rowCount() === 0) {
        http_response_code(500);
        echo json_encode([
            "success" => false,
            "message" => "Insert executed but no row was affected. Please try again."
        ]);
        exit;
    }

    echo json_encode([
        "success" => true,
        "message" => "Report submitted successfully.",
        "reference_id" => $referenceId,
        "photo_url" => $photoUrl,
        "status" => "pending",
    ]);

} catch (PDOException $e) {
    http_response_code(500);

    // Specific na error message base sa PDO error code, hindi laging raw exception message
    $errorCode = $e->errorInfo[1] ?? null;
    $userMessage = "Failed to save report.";

    if ($errorCode === 1062) {
        $userMessage = "Duplicate reference ID generated. Please try submitting again.";
    } elseif ($errorCode === 1452) {
        $userMessage = "Invalid user_id — user account not found.";
    } elseif ($errorCode === 1048) {
        $userMessage = "A required database field was left empty.";
    } elseif (strpos($e->getMessage(), 'could not find driver') !== false) {
        $userMessage = "Database driver missing on server (PDO MySQL not enabled).";
    } elseif (strpos($e->getMessage(), 'Connection refused') !== false) {
        $userMessage = "Cannot connect to database. Check if MySQL is running in XAMPP.";
    }

    echo json_encode([
        "success" => false,
        "message" => $userMessage,
        "debug" => $e->getMessage(), // pwede mong tanggalin 'to sa production/final build
    ]);
}