<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'teacher') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

require_once __DIR__ . '/../../db/db_config.php';

try {
    $class_id = $_GET['id'] ?? null;
    $teacher_id = $_SESSION['user_id'];
    
    if (!$class_id) {
        echo json_encode(['success' => false, 'message' => 'Class ID required']);
        exit();
    }
    
    // Get class details with stats
    $stmt = $pdo->prepare("
        SELECT 
            c.*,
            (SELECT COUNT(*) FROM class_enrollments e WHERE e.class_id = c.id) as student_count,
            (SELECT COUNT(*) FROM chapters ch WHERE ch.class_id = c.id) as chapter_count,
            (SELECT COUNT(*) FROM assignments a WHERE a.class_id = c.id) as assignment_count,
            datetime('now') as current_time
        FROM classes c 
        WHERE c.id = ? AND c.teacher_id = ?
    ");
    $stmt->execute([$class_id, $teacher_id]);
    $class = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$class) {
        echo json_encode(['success' => false, 'message' => 'Class not found']);
        exit();
    }
    
    // Get chapters with lessons count
    $stmt = $pdo->prepare("
        SELECT 
            ch.*,
            (SELECT COUNT(*) FROM lessons l WHERE l.chapter_id = ch.id) as lesson_count,
            datetime('now') as current_time
        FROM chapters ch 
        WHERE ch.class_id = ? 
        ORDER BY ch.order_index ASC
    ");
    $stmt->execute([$class_id]);
    $chapters = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get lessons for each chapter
    foreach ($chapters as &$chapter) {
        $stmt = $pdo->prepare("
            SELECT 
                l.*,
                datetime('now') as current_time
            FROM lessons l 
            WHERE l.chapter_id = ? 
            ORDER BY l.order_index ASC
        ");
        $stmt->execute([$chapter['id']]);
        $chapter['lessons'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    // Get recent enrollments (SỬA: enrollments -> class_enrollments, student_id -> user_id)
    $stmt = $pdo->prepare("
        SELECT 
            e.*,
            u.fullname as student_name,
            u.email as student_email,
            datetime('now') as current_time
        FROM class_enrollments e
        JOIN users u ON e.user_id = u.id
        WHERE e.class_id = ?
        ORDER BY e.enrolled_at DESC
        LIMIT 5
    ");
    $stmt->execute([$class_id]);
    $recent_enrollments = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get recent assignments
    $stmt = $pdo->prepare("
        SELECT 
            a.*,
            (SELECT COUNT(*) FROM submissions s WHERE s.assignment_id = a.id) as submission_count,
            datetime('now') as current_time
        FROM assignments a
        WHERE a.class_id = ?
        ORDER BY a.created_at DESC
        LIMIT 5
    ");
    $stmt->execute([$class_id]);
    $recent_assignments = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'class' => $class,
        'chapters' => $chapters,
        'recent_enrollments' => $recent_enrollments,
        'recent_assignments' => $recent_assignments,
        'timestamp' => date('Y-m-d H:i:s')
    ]);
    
} catch(PDOException $e) {
    error_log('Database error in get_class_detail: ' . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Database error: ' . $e->getMessage()
    ]);
}
?>