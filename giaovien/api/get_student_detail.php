<?php
// filepath: d:\Volunteerhub\giaovien\api\get_student_detail.php

session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'teacher') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

require_once __DIR__ . '/../../db/db_config.php';

try {
    $student_id = filter_input(INPUT_GET, 'student_id', FILTER_VALIDATE_INT);
    $class_id = filter_input(INPUT_GET, 'class_id', FILTER_VALIDATE_INT);
    
    if (!$student_id || !$class_id) {
        throw new Exception('Invalid parameters');
    }

    // Verify teacher owns this class
    $stmt = $pdo->prepare("SELECT id FROM classes WHERE id = ? AND teacher_id = ?");
    $stmt->execute([$class_id, $_SESSION['user_id']]);
    if (!$stmt->fetch()) {
        throw new Exception('Unauthorized to view this student');
    }

    // Get student basic info
    $stmt = $pdo->prepare("
        SELECT 
            u.id,
            u.fullname as name,
            u.email,
            ce.enrolled_at
        FROM users u
        JOIN class_enrollments ce ON u.id = ce.user_id
        WHERE u.id = ? AND ce.class_id = ?
    ");
    $stmt->execute([$student_id, $class_id]);
    $student = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$student) {
        throw new Exception('Student not found');
    }

    // Calculate completion rate - BOTH lessons AND assignments
    $stmt = $pdo->prepare("
        SELECT 
            (SELECT COUNT(*) FROM lessons l 
             JOIN chapters ch ON l.chapter_id = ch.id 
             WHERE ch.class_id = ?) +
            (SELECT COUNT(*) FROM assignments WHERE class_id = ?)
        as total_items
    ");
    $stmt->execute([$class_id, $class_id]);
    $total_items = $stmt->fetchColumn() ?: 1;

    $stmt = $pdo->prepare("
        SELECT 
            (SELECT COUNT(*) FROM lesson_progress lp
             JOIN lessons l ON lp.lesson_id = l.id
             JOIN chapters ch ON l.chapter_id = ch.id
             WHERE lp.user_id = ? AND ch.class_id = ? 
             AND lp.complete_time IS NOT NULL) +
            (SELECT COUNT(*) FROM submissions s
             JOIN assignments a ON s.assignment_id = a.id
             WHERE s.user_id = ? AND a.class_id = ?
             AND s.score IS NOT NULL)
        as completed_items
    ");
    $stmt->execute([$student_id, $class_id, $student_id, $class_id]);
    $completed_items = $stmt->fetchColumn() ?: 0;

    $completion_rate = round(($completed_items / $total_items) * 100);

    // Calculate average score - BOTH lessons AND assignments
    $stmt = $pdo->prepare("
        SELECT AVG(score) as avg_score FROM (
            SELECT 
                CASE 
                    WHEN lp.complete_time IS NOT NULL THEN l.max_score
                    ELSE 0
                END as score
            FROM lesson_progress lp
            JOIN lessons l ON lp.lesson_id = l.id
            JOIN chapters ch ON l.chapter_id = ch.id
            WHERE lp.user_id = ? AND ch.class_id = ?
            
            UNION ALL
            
            SELECT s.score
            FROM submissions s
            JOIN assignments a ON s.assignment_id = a.id
            WHERE s.user_id = ? AND a.class_id = ?
            AND s.score IS NOT NULL
        )
    ");
    $stmt->execute([$student_id, $class_id, $student_id, $class_id]);
    $avg_score = $stmt->fetchColumn();
    $student['avg_score'] = $avg_score ? round($avg_score, 1) : 'N/A';
    $student['completion_rate'] = $completion_rate;

    // ✅ Get detailed progress - BOTH lessons AND assignments WITH DEADLINE
    $stmt = $pdo->prepare("
        SELECT 
            'lesson' as type,
            l.id,
            l.title,
            l.max_score,
            CASE 
                WHEN lp.complete_time IS NOT NULL THEN 'completed'
                WHEN lp.start_time IS NOT NULL THEN 'in_progress'
                ELSE 'not_started'
            END as status,
            CASE 
                WHEN lp.complete_time IS NOT NULL THEN l.max_score
                ELSE NULL
            END as score,
            lp.start_time,
            lp.complete_time,
            NULL as deadline,
            NULL as submitted_at,
            ch.order_index as chapter_order,
            l.order_index as item_order
        FROM lessons l
        JOIN chapters ch ON l.chapter_id = ch.id
        LEFT JOIN lesson_progress lp ON l.id = lp.lesson_id AND lp.user_id = ?
        WHERE ch.class_id = ?
        
        UNION ALL
        
        SELECT 
            'assignment' as type,
            a.id,
            a.title,
            a.max_score,
            CASE 
                WHEN s.score IS NOT NULL THEN 'completed'
                WHEN s.submitted_at IS NOT NULL THEN 'in_progress'
                ELSE 'not_started'
            END as status,
            s.score,
            s.submitted_at as start_time,
            CASE 
                WHEN s.score IS NOT NULL THEN s.submitted_at
                ELSE NULL
            END as complete_time,
            a.deadline,
            s.submitted_at,
            999 as chapter_order,
            a.id as item_order
        FROM assignments a
        LEFT JOIN submissions s ON a.id = s.assignment_id AND s.user_id = ?
        WHERE a.class_id = ?
        
        ORDER BY chapter_order, item_order
    ");
    $stmt->execute([$student_id, $class_id, $student_id, $class_id]);
    $progress = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Get recent submissions
    $stmt = $pdo->prepare("
        SELECT 
            s.id,
            s.submitted_at,
            s.score,
            s.status,
            a.title as assignment_title,
            a.max_score,
            a.deadline
        FROM submissions s
        JOIN assignments a ON s.assignment_id = a.id
        WHERE s.user_id = ? AND a.class_id = ?
        ORDER BY s.submitted_at DESC
        LIMIT 5
    ");
    $stmt->execute([$student_id, $class_id]);
    $submissions = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'student' => $student,
        'progress' => $progress,
        'submissions' => $submissions
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>