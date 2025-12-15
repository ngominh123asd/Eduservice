<?php
session_start();
header('Content-Type: application/json');

// Set timezone to Vietnam
date_default_timezone_set('Asia/Ho_Chi_Minh');

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

require_once __DIR__ . '/../../db/db_config.php';

try {
    $user_id = $_SESSION['user_id'];
    $task_id = $_GET['task_id'] ?? null;
    $submission_id = $_GET['id'] ?? null;
    
    if (!$task_id && !$submission_id) {
        throw new Exception('Task ID or Submission ID required');
    }
    
    // Build query based on what parameter is provided
    if ($task_id) {
        // Get submission by task_id
        $stmt = $pdo->prepare("
            SELECT 
                s.id,
                s.submitted_at,
                s.score,
                s.feedback,
                s.file_path,
                s.status,
                a.title as task_name,
                a.max_score,
                c.class_name
            FROM submissions s
            JOIN assignments a ON s.assignment_id = a.id
            JOIN classes c ON a.class_id = c.id
            WHERE a.id = ? AND s.user_id = ?
            ORDER BY s.submitted_at DESC
            LIMIT 1
        ");
        $stmt->execute([$task_id, $user_id]);
    } else {
        // Get submission by submission_id
        $stmt = $pdo->prepare("
            SELECT 
                s.id,
                s.submitted_at,
                s.score,
                s.feedback,
                s.file_path,
                s.status,
                a.title as task_name,
                a.max_score,
                c.class_name
            FROM submissions s
            JOIN assignments a ON s.assignment_id = a.id
            JOIN classes c ON a.class_id = c.id
            WHERE s.id = ? AND s.user_id = ?
        ");
        $stmt->execute([$submission_id, $user_id]);
    }
    
    $submission = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$submission) {
        throw new Exception('Submission not found');
    }
    
    // Format dates
    if ($submission['submitted_at']) {
        $submission['submitted_at_formatted'] = date('d/m/Y H:i:s', strtotime($submission['submitted_at']));
    }
    
    // Check if graded (has score and feedback)
    $submission['is_graded'] = ($submission['score'] !== null || $submission['feedback'] !== null);
    
    echo json_encode([
        'success' => true,
        'submission' => $submission
    ]);
    
} catch(Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>