<?php
// filepath: d:\Volunteerhub\giaovien\api\get_submission_detail.php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'teacher') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

require_once __DIR__ . '/../../db/db_config.php';

try {
    $submission_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
    
    if (!$submission_id) {
        throw new Exception('Invalid submission ID');
    }

    // Get submission details - CHỈ LẤY TỪ BẢNG submissions
    $stmt = $pdo->prepare("
        SELECT 
            s.id,
            s.assignment_id,
            s.user_id,
            s.submitted_at,
            s.file_path,
            s.score,
            s.feedback,
            s.status,
            u.fullname as student_name,
            u.email as student_email,
            a.title as assignment_title,
            a.max_score,
            c.teacher_id,
            c.class_name
        FROM submissions s
        JOIN users u ON s.user_id = u.id
        JOIN assignments a ON s.assignment_id = a.id
        JOIN classes c ON a.class_id = c.id
        WHERE s.id = ?
    ");
    $stmt->execute([$submission_id]);
    $submission = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$submission) {
        throw new Exception('Submission not found');
    }

    // Verify teacher owns this submission
    if ($submission['teacher_id'] != $_SESSION['user_id']) {
        throw new Exception('Unauthorized to view this submission');
    }

    echo json_encode([
        'success' => true,
        'submission' => $submission
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Database error: ' . $e->getMessage()
    ]);
}
?>