<?php
// home/resources.php
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

$preview = isset($_GET['preview']) && $_GET['preview'] === 'true';

try {
    $sql = "
        SELECT id, category, title, snippet, content
        FROM resources
        ORDER BY sort_order ASC, id ASC
    ";
    if ($preview) {
        $sql .= " LIMIT 2";
    }

    $stmt = $conn->prepare($sql);
    $stmt->execute();
    $rows = $stmt->fetchAll();

    $resources = array_map(function ($row) {
        return [
            'id'       => (string) $row['id'],
            'category' => $row['category'],
            'title'    => $row['title'],
            'snippet'  => $row['snippet'],
            'content'  => $row['content'],
        ];
    }, $rows);

    echo json_encode([
        'success'   => true,
        'resources' => $resources,
    ]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}