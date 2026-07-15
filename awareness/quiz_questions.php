<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
require_once __DIR__ . '/../config/db.php';

try {
    $stmt = $conn->query("SELECT id, question, choices, correct_index, explanation FROM quiz_questions ORDER BY sort_order ASC");
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $data = array_map(function ($q) {
        return [
            'id' => (string) $q['id'],
            'question' => $q['question'],
            'choices' => json_decode($q['choices'], true) ?? [],
            'correct' => (int) $q['correct_index'],
            'explanation' => $q['explanation'],
        ];
    }, $rows);

    echo json_encode(['success' => true, 'data' => $data]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Failed to load quiz questions.']);
}