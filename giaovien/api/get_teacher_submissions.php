<?php
// File: giaovien/api/get_teacher_submissions.php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'teacher') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

require_once __DIR__ . '/../../db/db_config.php';

try {
    $teacher_id = $_SESSION['user_id'];
    $class_id = $_GET['class_id'] ?? null;
    $status = $_GET['status'] ?? null;
    
    // Get submissions from both lessons and assignments
    $query = "
        SELECT * FROM (
            -- Lesson submissions
            SELECT 
                lp.id,
                lp.user_id,
                u.fullname as student_name,
                u.email as student_email,
                c.name as class_name,
                l.title as lesson_title,
                NULL as assignment_title,
                lp.submission_text,
                lp.file_path,
                lp.score,
                l.max_score,
                lp.updated_at as submitted_at,
                lp.graded_at,
                lp.feedback,
                CASE WHEN lp.score IS NULL THEN 'pending' ELSE 'graded' END as status,
                'lesson' as type
            FROM lesson_progress lp
            JOIN users u ON lp.user_id = u.id
            JOIN lessons l ON lp.lesson_id = l.id
            JOIN chapters ch ON l.chapter_id = ch.id
            JOIN classes c ON ch.class_id = c.id
            WHERE c.teacher_id = ?
            AND lp.submission_text IS NOT NULL
            " . ($class_id ? "AND c.id = ?" : "") . "
            
            UNION ALL
            
            -- Assignment submissions
            SELECT 
                as2.id,
                as2.user_id,
                u.fullname as student_name,
                u.email as student_email,
                c.name as class_name,
                NULL as lesson_title,
                a.title as assignment_title,
                as2.submission_text,
                as2.file_path,
                as2.score,
                a.max_score,
                as2.submitted_at,
                as2.graded_at,
                as2.feedback,
                CASE WHEN as2.score IS NULL THEN 'pending' ELSE 'graded' END as status,
                'assignment' as type
            FROM assignment_submissions as2
            JOIN users u ON as2.user_id = u.id
            JOIN assignments a ON as2.assignment_id = a.id
            JOIN classes c ON a.class_id = c.id
            WHERE c.teacher_id = ?
            " . ($class_id ? "AND c.id = ?" : "") . "
        ) as all_submissions
        " . ($status ? "WHERE status = ?" : "") . "
        ORDER BY submitted_at DESC
    ";
    
    $params = [$teacher_id];
    if ($class_id) $params[] = $class_id;
    $params[] = $teacher_id;
    if ($class_id) $params[] = $class_id;
    if ($status) $params[] = $status;
    
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $submissions = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'submissions' => $submissions
    ]);
    
} catch(PDOException $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Database error: ' . $e->getMessage()
    ]);
}
?>