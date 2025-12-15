<?php
// File: giaovien/api/get_upcoming_deadlines.php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'teacher') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

require_once __DIR__ . '/../../db/db_config.php';

try {
    $teacher_id = $_SESSION['user_id'];
    $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
    $limit = 5;
    $offset = ($page - 1) * $limit;
    
    // Get upcoming deadlines from assignments only
    $stmt = $pdo->prepare("
        SELECT 
            a.id,
            a.title,
            a.deadline,
            a.start_date,
            c.class_name,
            (SELECT COUNT(*) FROM submissions WHERE assignment_id = a.id) as submission_count,
            (SELECT COUNT(*) FROM class_enrollments WHERE class_id = c.id) as total_students
        FROM assignments a
        JOIN classes c ON a.class_id = c.id
        WHERE c.teacher_id = ?
        AND a.deadline >= datetime('now')
        ORDER BY a.deadline ASC
        LIMIT ? OFFSET ?
    ");
    
    $stmt->execute([$teacher_id, $limit, $offset]);
    $deadlines = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get total count
    $countStmt = $pdo->prepare("
        SELECT COUNT(*) as total
        FROM assignments a
        JOIN classes c ON a.class_id = c.id
        WHERE c.teacher_id = ?
        AND a.deadline >= datetime('now')
    ");
    $countStmt->execute([$teacher_id]);
    $total = $countStmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    echo json_encode([
        'success' => true,
        'deadlines' => $deadlines,
        'pagination' => [
            'current_page' => $page,
            'total_pages' => ceil($total / $limit),
            'total_items' => $total
        ]
    ]);
    
} catch(PDOException $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Database error: ' . $e->getMessage()
    ]);
}
?>