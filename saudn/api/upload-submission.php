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
    $task_id = $_POST['task_id'] ?? null;
    
    if (!$task_id) {
        throw new Exception('Task ID required');
    }
    
    // Verify assignment exists and student is enrolled
    $stmt = $pdo->prepare("
        SELECT a.id, a.start_date, a.deadline, c.id as class_id
        FROM assignments a
        JOIN classes c ON a.class_id = c.id
        JOIN class_enrollments ce ON c.id = ce.class_id
        WHERE a.id = ? AND ce.user_id = ?
    ");
    $stmt->execute([$task_id, $user_id]);
    $assignment = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$assignment) {
        throw new Exception('Assignment not found or not enrolled');
    }
    
    // Check if task has started
    if ($assignment['start_date']) {
        $now = new DateTime();
        $startDate = new DateTime($assignment['start_date']);
        if ($now < $startDate) {
            throw new Exception('Assignment has not started yet');
        }
    }
    
    // Check if deadline passed
    if ($assignment['deadline']) {
        $now = new DateTime();
        $deadline = new DateTime($assignment['deadline']);
        if ($now > $deadline) {
            throw new Exception('Deadline has passed');
        }
    }
    
    // Check if already submitted
    $stmt = $pdo->prepare("
        SELECT id FROM submissions 
        WHERE assignment_id = ? AND user_id = ?
    ");
    $stmt->execute([$task_id, $user_id]);
    if ($stmt->fetch()) {
        throw new Exception('You have already submitted this assignment');
    }
    
    // Handle file upload
    $file_path = null;
    if (isset($_FILES['file']) && $_FILES['file']['error'] === UPLOAD_ERR_OK) {
        $upload_dir = __DIR__ . '/../../uploads/submissions/';
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }
        
        $file_ext = pathinfo($_FILES['file']['name'], PATHINFO_EXTENSION);
        $file_name = $user_id . '_' . $task_id . '_' . time() . '.' . $file_ext;
        $file_path = $file_name;
        
        if (!move_uploaded_file($_FILES['file']['tmp_name'], $upload_dir . $file_name)) {
            throw new Exception('Failed to upload file');
        }
    }
    
    // Get current Vietnam time
    $submitted_at = date('Y-m-d H:i:s');
    
    // Insert submission
    $stmt = $pdo->prepare("
        INSERT INTO submissions 
        (assignment_id, user_id, file_path, submitted_at, status)
        VALUES (?, ?, ?, ?, 'submitted')
    ");
    $stmt->execute([$task_id, $user_id, $file_path, $submitted_at]);
    
    echo json_encode([
        'success' => true,
        'message' => 'Submission uploaded successfully',
        'submitted_at' => $submitted_at
    ]);
    
} catch(Exception $e) {
    // Clean up uploaded file if submission failed
    if (isset($file_path) && file_exists(__DIR__ . '/../../uploads/submissions/' . $file_path)) {
        unlink(__DIR__ . '/../../uploads/submissions/' . $file_path);
    }
    
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>