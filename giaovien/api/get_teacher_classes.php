<?php
// File: giaovien/api/get_teacher_classes.php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'teacher') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

require_once __DIR__ . '/../../db/db_config.php';

try {
    $teacher_id = $_SESSION['user_id'];
    
    $stmt = $pdo->prepare("
        SELECT 
            c.*,
            (SELECT COUNT(*) FROM enrollments e WHERE e.class_id = c.id) as student_count,
            (SELECT COUNT(*) FROM chapters ch WHERE ch.class_id = c.id) as chapter_count,
            (SELECT COUNT(*) FROM assignments a WHERE a.class_id = c.id) as assignment_count,
            strftime('%Y-%m-%d %H:%M:%S', c.created_at) as formatted_date
        FROM classes c 
        WHERE c.teacher_id = ?
        ORDER BY c.created_at DESC
    ");
    
    $stmt->execute([$teacher_id]);
    $classes = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Format dates for each class
    foreach ($classes as &$class) {
        $class['created_at'] = $class['formatted_date'];
        unset($class['formatted_date']);
    }
    
    echo json_encode([
        'success' => true,
        'classes' => $classes
    ]);

} catch(PDOException $e) {
    error_log('Database error: ' . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Database error: ' . $e->getMessage()
    ]);
}
?>