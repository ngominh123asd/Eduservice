<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'teacher') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

require_once __DIR__ . '/../../db/db_config.php';

try {
    $class_id = filter_input(INPUT_GET, 'class_id', FILTER_VALIDATE_INT);
    
    if (!$class_id) {
        throw new Exception('Invalid class ID');
    }

    // Verify teacher owns this class
    $stmt = $pdo->prepare("SELECT id FROM classes WHERE id = ? AND teacher_id = ?");
    $stmt->execute([$class_id, $_SESSION['user_id']]);
    if (!$stmt->fetch()) {
        throw new Exception('Unauthorized to view this class');
    }

    // Get students with completion rate and average score
    $stmt = $pdo->prepare("
        SELECT 
            u.id as user_id,
            u.fullname as name,
            u.email,
            ce.enrolled_at,
            ce.status,
            
            -- Calculate total items (lessons + assignments)
            (
                (SELECT COUNT(*) FROM lessons l 
                 JOIN chapters ch ON l.chapter_id = ch.id 
                 WHERE ch.class_id = ?) +
                (SELECT COUNT(*) FROM assignments WHERE class_id = ?)
            ) as total_items,
            
            -- Calculate completed items
            (
                (SELECT COUNT(*) FROM lesson_progress lp
                 JOIN lessons l ON lp.lesson_id = l.id
                 JOIN chapters ch ON l.chapter_id = ch.id
                 WHERE lp.user_id = u.id AND ch.class_id = ? 
                 AND lp.complete_time IS NOT NULL) +
                (SELECT COUNT(*) FROM submissions s
                 JOIN assignments a ON s.assignment_id = a.id
                 WHERE s.user_id = u.id AND a.class_id = ?
                 AND s.score IS NOT NULL)
            ) as completed_items,
            
            -- Calculate average score
            (
                SELECT AVG(score) FROM (
                    SELECT 
                        CASE 
                            WHEN lp.complete_time IS NOT NULL THEN l.max_score
                            ELSE 0
                        END as score
                    FROM lesson_progress lp
                    JOIN lessons l ON lp.lesson_id = l.id
                    JOIN chapters ch ON l.chapter_id = ch.id
                    WHERE lp.user_id = u.id AND ch.class_id = ?
                    
                    UNION ALL
                    
                    SELECT s.score
                    FROM submissions s
                    JOIN assignments a ON s.assignment_id = a.id
                    WHERE s.user_id = u.id AND a.class_id = ?
                    AND s.score IS NOT NULL
                ) as all_scores
            ) as avg_score
            
        FROM users u
        JOIN class_enrollments ce ON u.id = ce.user_id
        WHERE ce.class_id = ?
        ORDER BY u.fullname
    ");
    
    $stmt->execute([
        $class_id, $class_id,  // total_items
        $class_id, $class_id,  // completed_items
        $class_id, $class_id,  // avg_score
        $class_id              // WHERE clause
    ]);
    
    $students = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Calculate completion_rate for each student
    foreach ($students as &$student) {
        $total = $student['total_items'] ?: 1; // Avoid division by zero
        $completed = $student['completed_items'] ?: 0;
        $student['completion_rate'] = round(($completed / $total) * 100);
        $student['avg_score'] = $student['avg_score'] ? round($student['avg_score'], 1) : 'N/A';
        
        // Remove temporary fields
        unset($student['total_items']);
        unset($student['completed_items']);
    }

    echo json_encode([
        'success' => true,
        'students' => $students
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>