<?php
include_once '../config/db.php';

$query = "SELECT * FROM incidents ORDER BY created_at DESC";
$stmt = $conn->prepare($query);
$stmt->execute();
$incidents = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo json_encode(["data" => $incidents]);
?>