<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

include_once '../config/db.php';

$user_id = isset($_GET['user_id']) ? $_GET['user_id'] : 1;

// Get user info
$userQuery = "SELECT id, full_name, email, avatar_url, barangay, is_verified FROM users WHERE id = :user_id";
$userStmt = $conn->prepare($userQuery);
$userStmt->bindParam(':user_id', $user_id);
$userStmt->execute();
$user = $userStmt->fetch(PDO::FETCH_ASSOC);

// Get readiness checklist
$readinessQuery = "SELECT id, label, icon, done FROM user_readiness WHERE user_id = :user_id";
$readinessStmt = $conn->prepare($readinessQuery);
$readinessStmt->bindParam(':user_id', $user_id);
$readinessStmt->execute();
$readiness = $readinessStmt->fetchAll(PDO::FETCH_ASSOC);

// Get trusted contacts
$contactsQuery = "SELECT id, name, relation, phone FROM trusted_contacts WHERE user_id = :user_id";
$contactsStmt = $conn->prepare($contactsQuery);
$contactsStmt->bindParam(':user_id', $user_id);
$contactsStmt->execute();
$contacts = $contactsStmt->fetchAll(PDO::FETCH_ASSOC);

// Get activity stats — check if incidents table exists first, safe fallback
$reportsCount = 0;
try {
    $reportsCountQuery = "SELECT COUNT(*) as count FROM incidents WHERE user_id = :user_id";
    $reportsStmt = $conn->prepare($reportsCountQuery);
    $reportsStmt->bindParam(':user_id', $user_id);
    $reportsStmt->execute();
    $reportsCount = $reportsStmt->fetch(PDO::FETCH_ASSOC)['count'];
} catch (PDOException $e) {
    $reportsCount = 0; // table doesn't exist yet, default to 0
}

echo json_encode([
    "user" => $user,
    "readiness" => $readiness,
    "contacts" => $contacts,
    "stats" => [
        "reports" => (int)$reportsCount,
        "alertsViewed" => 12,
        "guidesRead" => 5
    ]
]);
?>