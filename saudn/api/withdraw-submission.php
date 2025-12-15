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
    $input = json_decode(file_get_contents('php://input'), true);
    $task_id = $input['task_id'] ?? null;
    
    if (!$task_id) {
        throw new Exception('Task ID required');
    }
    
    // Get assignment info
    $stmt = $pdo->prepare("
        SELECT a.id, a.deadline, s.id as submission_id, s.file_path, s.score
        FROM assignments a
        LEFT JOIN submissions s ON a.id = s.assignment_id AND s.user_id = ?
        WHERE a.id = ?
    ");
    $stmt->execute([$user_id, $task_id]);
    $assignment = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$assignment) {
        throw new Exception('Assignment not found');
    }
    
    if (!$assignment['submission_id']) {
        throw new Exception('No submission found to withdraw');
    }
    
    // Check if already graded
    if ($assignment['score'] !== null) {
        throw new Exception('Không thể thu hồi bài đã được chấm điểm');
    }
    
    // Check if deadline passed
    if ($assignment['deadline']) {
        $now = new DateTime();
        $deadline = new DateTime($assignment['deadline']);
        if ($now > $deadline) {
            throw new Exception('Không thể thu hồi sau thời hạn');
        }
    }
    
    // Delete file if exists
    if ($assignment['file_path']) {
        $file_path = __DIR__ . '/../../uploads/submissions/' . $assignment['file_path'];
        if (file_exists($file_path)) {
            unlink($file_path);
        }
    }
    
    // Delete submission from database
    $stmt = $pdo->prepare("
        DELETE FROM submissions 
        WHERE id = ? AND user_id = ?
    ");
    $stmt->execute([$assignment['submission_id'], $user_id]);
    
    echo json_encode([
        'success' => true,
        'message' => 'Submission withdrawn successfully'
    ]);
    
} catch(Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>