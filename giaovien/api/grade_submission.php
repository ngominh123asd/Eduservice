<?php
// File: giaovien/api/grade_submission.php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'teacher') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

require_once __DIR__ . '/../../db/db_config.php';

try {
    $input = json_decode(file_get_contents('php://input'), true);
    
    $submission_id = $input['submission_id'] ?? null;
    $score = $input['score'] ?? null;
    $feedback = $input['feedback'] ?? '';
    
    if (!$submission_id || $score === null) {
        throw new Exception('Missing required fields');
    }
    
    // Verify submission exists and belongs to teacher's class
    $stmt = $pdo->prepare("
        SELECT s.id, s.assignment_id, a.max_score, c.teacher_id
        FROM submissions s
        JOIN assignments a ON s.assignment_id = a.id
        JOIN classes c ON a.class_id = c.id
        WHERE s.id = ?
    ");
    $stmt->execute([$submission_id]);
    $submission = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$submission) {
        throw new Exception('Submission not found');
    }
    
    if ($submission['teacher_id'] != $_SESSION['user_id']) {
        throw new Exception('Unauthorized to grade this submission');
    }
    
    // Validate score
    $score = floatval($score);
    if ($score < 0 || $score > $submission['max_score']) {
        throw new Exception("Score must be between 0 and {$submission['max_score']}");
    }
    
    // Update submission with score and feedback
    $stmt = $pdo->prepare("
        UPDATE submissions 
        SET score = ?, 
            feedback = ?,
            status = 'graded'
        WHERE id = ?
    ");
    $stmt->execute([$score, $feedback, $submission_id]);
    
    echo json_encode([
        'success' => true,
        'message' => 'Graded successfully'
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>