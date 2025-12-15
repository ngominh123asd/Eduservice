<?php
session_start();
header('Content-Type: application/json');

// Set timezone to Vietnam
date_default_timezone_set('Asia/Ho_Chi_Minh');

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

require_once __DIR__ . '/../../db/db_config.php';

try {
    $user_id = $_SESSION['user_id'];
    
    // Get filter and pagination params
    $filter = $_GET['filter'] ?? 'all';
    $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
    $per_page = 5; // Increased to 5 items per page
    $offset = ($page - 1) * $per_page;
    
    // Ensure page is at least 1
    if ($page < 1) $page = 1;
    
    // Get statistics
    $stats = [
        'total_classes' => 0,
        'pending_tasks' => 0,
        'completed_lessons' => 0,
        'avg_score' => 0
    ];
    
    // Total enrolled classes
    $stmt = $pdo->prepare("
        SELECT COUNT(DISTINCT ce.class_id) 
        FROM class_enrollments ce
        INNER JOIN classes c ON ce.class_id = c.id
        WHERE ce.user_id = ?
    ");
    $stmt->execute([$user_id]);
    $stats['total_classes'] = (int)$stmt->fetchColumn();
    
    // Pending tasks (chỉ đếm task đã bắt đầu và chưa nộp)
    $stmt = $pdo->prepare("
        SELECT COUNT(DISTINCT a.id) 
        FROM assignments a
        INNER JOIN class_enrollments ce ON a.class_id = ce.class_id
        LEFT JOIN submissions s ON a.id = s.assignment_id AND s.user_id = ?
        WHERE ce.user_id = ? 
        AND s.id IS NULL 
        AND datetime(a.start_date) <= datetime('now', 'localtime')
        AND (a.deadline IS NULL OR datetime(a.deadline) >= datetime('now', 'localtime'))
    ");
    $stmt->execute([$user_id, $user_id]);
    $stats['pending_tasks'] = (int)$stmt->fetchColumn();
    
    // Completed lessons
    $stmt = $pdo->prepare("
        SELECT COUNT(DISTINCT lp.lesson_id) 
        FROM lesson_progress lp
        WHERE lp.user_id = ? AND lp.complete_time IS NOT NULL
    ");
    $stmt->execute([$user_id]);
    $stats['completed_lessons'] = (int)$stmt->fetchColumn();
    
    // Average score
    $stmt = $pdo->prepare("
        SELECT AVG(lp.score) 
        FROM lesson_progress lp
        WHERE lp.user_id = ? AND lp.score IS NOT NULL
    ");
    $stmt->execute([$user_id]);
    $avgScore = $stmt->fetchColumn();
    $stats['avg_score'] = $avgScore ? round((float)$avgScore, 1) : 0;
    
    // ✅ FIRST: Get all activities to count correctly
    $baseQuery = "
        SELECT 
            'lesson' as type,
            l.title as title,
            lp.complete_time as created_at,
            'Hoàn thành bài học' as description,
            c.class_name as class_name
        FROM lesson_progress lp
        INNER JOIN lessons l ON lp.lesson_id = l.id
        INNER JOIN chapters ch ON l.chapter_id = ch.id
        INNER JOIN classes c ON ch.class_id = c.id
        WHERE lp.user_id = :user_id 
        AND lp.complete_time IS NOT NULL
        
        UNION ALL
        
        SELECT 
            'submission' as type,
            a.title as title,
            s.submitted_at as created_at,
            'Nộp bài tập' as description,
            c.class_name as class_name
        FROM submissions s
        INNER JOIN assignments a ON s.assignment_id = a.id
        INNER JOIN classes c ON a.class_id = c.id
        WHERE s.user_id = :user_id
        AND s.submitted_at IS NOT NULL
        
        UNION ALL
        
        SELECT 
            'enrollment' as type,
            c.class_name as title,
            ce.enrolled_at as created_at,
            'Tham gia lớp học' as description,
            c.class_name as class_name
        FROM class_enrollments ce
        INNER JOIN classes c ON ce.class_id = c.id
        WHERE ce.user_id = :user_id
        AND ce.enrolled_at IS NOT NULL
    ";
    
    // Build WHERE clause for filter
    $whereClause = "";
    if ($filter !== 'all') {
        $whereClause = "WHERE type = :filter";
    }
    
    // ✅ COUNT QUERY - Wrap in subquery to filter correctly
    $countQuery = "
        SELECT COUNT(*) FROM (
            $baseQuery
        ) activities
        $whereClause
    ";
    
    $countStmt = $pdo->prepare($countQuery);
    $countStmt->bindValue(':user_id', $user_id, PDO::PARAM_INT);
    if ($filter !== 'all') {
        $countStmt->bindValue(':filter', $filter, PDO::PARAM_STR);
    }
    $countStmt->execute();
    $total_items = (int)$countStmt->fetchColumn();
    
    // ✅ Calculate pagination
    $total_pages = $total_items > 0 ? (int)ceil($total_items / $per_page) : 1;
    
    // ✅ Adjust page if out of bounds
    if ($page > $total_pages) {
        $page = $total_pages;
        $offset = ($page - 1) * $per_page;
    }
    
    // ✅ DATA QUERY with pagination
    $query = "
        SELECT * FROM (
            $baseQuery
        ) activities
        $whereClause
        ORDER BY created_at DESC
        LIMIT :limit OFFSET :offset
    ";
    
    $stmt = $pdo->prepare($query);
    $stmt->bindValue(':user_id', $user_id, PDO::PARAM_INT);
    if ($filter !== 'all') {
        $stmt->bindValue(':filter', $filter, PDO::PARAM_STR);
    }
    $stmt->bindValue(':limit', $per_page, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $recent_activity = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Format timestamps
    foreach ($recent_activity as &$activity) {
        if ($activity['created_at']) {
            $timestamp = strtotime($activity['created_at']);
            $activity['formatted_time'] = date('H:i d/m/Y', $timestamp);
            $activity['relative_time'] = getRelativeTime($timestamp);
        }
    }
    
    echo json_encode([
        'success' => true,
        'stats' => $stats,
        'recent_activity' => $recent_activity,
        'pagination' => [
            'current_page' => $page,
            'total_pages' => $total_pages,
            'total_items' => $total_items,
            'per_page' => $per_page,
            'has_next' => $page < $total_pages,
            'has_prev' => $page > 1,
            'showing_from' => $offset + 1,
            'showing_to' => min($offset + $per_page, $total_items)
        ],
        'filter' => $filter,
        'server_time' => date('Y-m-d H:i:s'),
        'debug' => [
            'user_id' => $user_id,
            'filter' => $filter,
            'page' => $page,
            'offset' => $offset,
            'total_items' => $total_items,
            'total_pages' => $total_pages
        ]
    ]);
    
} catch(Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
        'trace' => $e->getTraceAsString()
    ]);
}

function getRelativeTime($timestamp) {
    $diff = time() - $timestamp;
    
    if ($diff < 60) {
        return 'Vừa xong';
    } elseif ($diff < 3600) {
        $minutes = floor($diff / 60);
        return $minutes . ' phút trước';
    } elseif ($diff < 86400) {
        $hours = floor($diff / 3600);
        return $hours . ' giờ trước';
    } elseif ($diff < 604800) {
        $days = floor($diff / 86400);
        return $days . ' ngày trước';
    } else {
        return date('d/m/Y', $timestamp);
    }
}
?>