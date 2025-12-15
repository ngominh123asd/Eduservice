<?php
// filepath: d:\Volunteerhub\giaovien\api\get_assignment_submissions.php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'teacher') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

require_once __DIR__ . '/../../db/db_config.php';

try {
    $assignment_id = filter_input(INPUT_GET, 'assignment_id', FILTER_VALIDATE_INT);
    
    if (!$assignment_id) {
        throw new Exception('Invalid assignment ID');
    }

    // Verify teacher owns this assignment
    $stmt = $pdo->prepare("
        SELECT a.*, c.class_name 
        FROM assignments a
        JOIN classes c ON a.class_id = c.id
        WHERE a.id = ? AND c.teacher_id = ?
    ");
    $stmt->execute([$assignment_id, $_SESSION['user_id']]);
    $assignment = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$assignment) {
        throw new Exception('Assignment not found or unauthorized');
    }

    // Get submissions with proper status check
    $stmt = $pdo->prepare("
        SELECT 
            s.id,
            s.assignment_id,
            s.user_id,
            s.submitted_at,
            s.file_path,
            s.score,
            s.feedback,
            CASE 
                WHEN s.score IS NOT NULL THEN 'graded'
                ELSE 'pending'
            END as status,
            u.fullname as student_name,
            u.email as student_email
        FROM submissions s
        JOIN users u ON s.user_id = u.id
        WHERE s.assignment_id = ?
        ORDER BY s.submitted_at DESC
    ");
    $stmt->execute([$assignment_id]);
    $submissions = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'assignment' => $assignment,
        'submissions' => $submissions
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Database error occurred',
        'details' => $e->getMessage()
    ]);
}
?>