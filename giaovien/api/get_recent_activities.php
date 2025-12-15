<?php
// File: giaovien/api/get_recent_activities.php
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
    
    // Get recent activities: assignments created only (simplified version)
    $stmt = $pdo->prepare("
        SELECT 
            'assignment_created' as type,
            a.id,
            a.created_at as activity_time,
            NULL as student_name,
            a.title as item_title,
            c.class_name
        FROM assignments a
        JOIN classes c ON a.class_id = c.id
        WHERE c.teacher_id = ?
        AND a.created_at IS NOT NULL
        AND a.created_at >= datetime('now', '-30 days')
        ORDER BY a.created_at DESC
        LIMIT ? OFFSET ?
    ");
    
    $stmt->execute([$teacher_id, $limit, $offset]);
    $activities = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // ✅ Ensure all required fields exist
    $validActivities = array_map(function($activity) {
        return [
            'type' => $activity['type'] ?? 'assignment_created',
            'id' => $activity['id'] ?? 0,
            'activity_time' => $activity['activity_time'] ?? date('Y-m-d H:i:s'),
            'student_name' => $activity['student_name'] ?? null,
            'item_title' => $activity['item_title'] ?? 'Không có tiêu đề',
            'class_name' => $activity['class_name'] ?? 'Không có tên lớp'
        ];
    }, $activities);
    
    // Get total count
    $countStmt = $pdo->prepare("
        SELECT COUNT(*) as total
        FROM assignments a
        JOIN classes c ON a.class_id = c.id
        WHERE c.teacher_id = ?
        AND a.created_at IS NOT NULL
        AND a.created_at >= datetime('now', '-30 days')
    ");
    $countStmt->execute([$teacher_id]);
    $total = $countStmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    echo json_encode([
        'success' => true,
        'activities' => $validActivities,
        'pagination' => [
            'current_page' => $page,
            'total_pages' => max(1, ceil($total / $limit)),
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