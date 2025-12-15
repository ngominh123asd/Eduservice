<?php
// File: giaovien/api/get_teacher_assignments.php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'teacher') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

require_once __DIR__ . '/../../db/db_config.php';

try {
    $teacher_id = $_SESSION['user_id'];
    
    // Updated query to join with classes table
    $stmt = $pdo->prepare("
        SELECT 
            a.*,
            c.class_name,
            (SELECT COUNT(*) FROM submissions s WHERE s.assignment_id = a.id) as submission_count
        FROM assignments a
        JOIN classes c ON a.class_id = c.id 
        WHERE c.teacher_id = ?
        ORDER BY a.created_at DESC
    ");

    $stmt->execute([$teacher_id]);
    $assignments = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'assignments' => $assignments
    ]);

} catch (PDOException $e) {
    error_log('Database error: ' . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Database error occurred',
        'details' => $e->getMessage()
    ]);
}
?>