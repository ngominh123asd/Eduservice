<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

require_once __DIR__ . '/../../db/db_config.php';

try {
    $user_id = $_SESSION['user_id'];
    
    $stmt = $pdo->prepare("
        SELECT 
            s.id,
            s.submitted_at,
            s.score,
            s.status,
            s.graded_at,
            s.feedback,
            a.title as task_name,
            a.max_score,
            c.class_name
        FROM submissions s
        JOIN assignments a ON s.assignment_id = a.id
        JOIN classes c ON a.class_id = c.id
        WHERE s.user_id = ?
        ORDER BY s.submitted_at DESC
    ");
    
    $stmt->execute([$user_id]);
    $submissions = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'submissions' => $submissions
    ]);
    
} catch(PDOException $e) {
    error_log('Submissions API error: ' . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Database error occurred'
    ]);
}
?>