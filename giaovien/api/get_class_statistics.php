<?php
// File: giaovien/api/get_class_statistics.php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'teacher') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

require_once __DIR__ . '/../../db/db_config.php';

try {
    $class_id = $_GET['class_id'] ?? null;
    $teacher_id = $_SESSION['user_id'];
    
    if (!$class_id) {
        echo json_encode(['success' => false, 'message' => 'Class ID required']);
        exit();
    }
    
    // Verify teacher owns this class
    $stmt = $pdo->prepare("SELECT id FROM classes WHERE id = ? AND teacher_id = ?");
    $stmt->execute([$class_id, $teacher_id]);
    if (!$stmt->fetch()) {
        echo json_encode(['success' => false, 'message' => 'Class not found']);
        exit();
    }
    
    // Get total students
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as total 
        FROM class_enrollments 
        WHERE class_id = ?
    ");
    $stmt->execute([$class_id]);
    $total_students = $stmt->fetch()['total'];
    
    // Get total lessons
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as total 
        FROM lessons l
        JOIN chapters ch ON l.chapter_id = ch.id
        WHERE ch.class_id = ?
    ");
    $stmt->execute([$class_id]);
    $total_lessons = $stmt->fetch()['total'];
    
    // Get average completion rate
    $stmt = $pdo->prepare("
        SELECT 
            AVG(completion_rate) as avg_completion
        FROM (
            SELECT 
                ce.user_id,
                (COUNT(DISTINCT CASE WHEN lp.completed = 1 THEN lp.lesson_id END) * 100.0 / 
                    NULLIF(COUNT(DISTINCT l.id), 0)) as completion_rate
            FROM class_enrollments ce
            CROSS JOIN chapters ch
            LEFT JOIN lessons l ON ch.id = l.chapter_id
            LEFT JOIN lesson_progress lp ON l.id = lp.lesson_id AND lp.user_id = ce.user_id
            WHERE ce.class_id = ? AND ch.class_id = ?
            GROUP BY ce.user_id
        ) as student_progress
    ");
    $stmt->execute([$class_id, $class_id]);
    $avg_completion = round($stmt->fetch()['avg_completion'] ?? 0, 1);
    
    // Get average score
    $stmt = $pdo->prepare("
        SELECT AVG(score) as avg_score
        FROM lesson_progress lp
        JOIN lessons l ON lp.lesson_id = l.id
        JOIN chapters ch ON l.chapter_id = ch.id
        WHERE ch.class_id = ? AND lp.score IS NOT NULL
    ");
    $stmt->execute([$class_id]);
    $avg_score = round($stmt->fetch()['avg_score'] ?? 0, 1);
    
    // Get top students
    $stmt = $pdo->prepare("
        SELECT 
            u.id,
            u.fullname as name,
            AVG(lp.score) as avg_score,
            COUNT(CASE WHEN lp.completed = 1 THEN 1 END) as completed_lessons
        FROM class_enrollments ce
        JOIN users u ON ce.user_id = u.id
        LEFT JOIN lesson_progress lp ON u.id = lp.user_id
        LEFT JOIN lessons l ON lp.lesson_id = l.id
        LEFT JOIN chapters ch ON l.chapter_id = ch.id AND ch.class_id = ?
        WHERE ce.class_id = ?
        GROUP BY u.id
        HAVING avg_score IS NOT NULL
        ORDER BY avg_score DESC, completed_lessons DESC
        LIMIT 10
    ");
    $stmt->execute([$class_id, $class_id]);
    $top_students = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Format avg_score for top students
    foreach ($top_students as &$student) {
        $student['avg_score'] = round($student['avg_score'], 1);
    }
    
    echo json_encode([
        'success' => true,
        'stats' => [
            'total_students' => $total_students,
            'total_lessons' => $total_lessons,
            'avg_completion' => $avg_completion,
            'avg_score' => $avg_score
        ],
        'top_students' => $top_students
    ]);
    
} catch(PDOException $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Database error: ' . $e->getMessage()
    ]);
}
?>