<?php
// ── FOR BFP SIDE ── Help & Support (backend-managed content)
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once __DIR__ . '/../../config/db.php';

try {
    $stmt = $conn->prepare("
        SELECT contact_person, support_email, hotline_number, announcement
        FROM bfp_help_support
        ORDER BY id DESC
        LIMIT 1
    ");
    $stmt->execute();
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    $faqStmt = $conn->prepare("
        SELECT id, question, answer
        FROM bfp_support_faqs
        WHERE is_active = 1
        ORDER BY sort_order ASC, id ASC
    ");
    $faqStmt->execute();
    $faqRows = $faqStmt->fetchAll(PDO::FETCH_ASSOC);

    $faqs = array_map(function ($f) {
        return [
            'id' => (string) $f['id'],
            'question' => $f['question'],
            'answer' => $f['answer'],
        ];
    }, $faqRows);

    $data = [
        'contactPerson' => $row['contact_person'] ?? null,
        'supportEmail'  => $row['support_email'] ?? null,
        'hotline'       => $row['hotline_number'] ?? null,
        'announcement'  => $row['announcement'] ?? null,
        'faqs'          => $faqs,
    ];

    echo json_encode(['success' => true, 'data' => $data]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Failed to load Help & Support info.']);
}