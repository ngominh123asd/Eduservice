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
    $filter = $_GET['filter'] ?? 'all';
    
    // Base query
    $sql = "
        SELECT 
            a.id,
            a.title as task_name,
            a.description,
            a.start_date,
            a.deadline,
            a.max_score,
            c.class_name,
            CASE WHEN s.id IS NOT NULL THEN 1 ELSE 0 END as submitted,
            s.score,
            s.submitted_at
        FROM assignments a
        JOIN class_enrollments ce ON a.class_id = ce.class_id
        JOIN classes c ON a.class_id = c.id
        LEFT JOIN submissions s ON a.id = s.assignment_id AND s.user_id = ce.user_id
        WHERE ce.user_id = ?
    ";
    
    // Add filter conditions
    $params = [$user_id];
    switch ($filter) {
        case 'pending':
            $sql .= " AND s.id IS NULL AND (a.deadline IS NULL OR datetime(a.deadline) >= datetime('now', 'localtime'))";
            break;
        case 'completed':
            $sql .= " AND s.id IS NOT NULL";
            break;
        case 'overdue':
            $sql .= " AND s.id IS NULL AND datetime(a.deadline) < datetime('now', 'localtime')";
            break;
    }
    // Order by priority
    $sql .= " ORDER BY 
        CASE 
            -- Priority 1: Đã bắt đầu, chưa nộp, chưa quá hạn (CÓ THỂ NỘP)
            WHEN s.id IS NULL 
                AND (a.start_date IS NULL OR datetime(a.start_date) <= datetime('now', 'localtime'))
                AND (a.deadline IS NULL OR datetime(a.deadline) >= datetime('now', 'localtime'))
            THEN 1
            
            -- Priority 2: Chưa bắt đầu và chưa nộp (CHƯA THỂ NỘP)
            WHEN s.id IS NULL 
                AND (a.start_date IS NOT NULL AND datetime(a.start_date) > datetime('now', 'localtime'))
            THEN 2
            
            -- Priority 3: Đã quá hạn
            WHEN s.id IS NULL 
                AND datetime(a.deadline) < datetime('now', 'localtime')
            THEN 3
            
            -- Priority 4: Đã nộp
            WHEN s.id IS NOT NULL
            THEN 4
            
            ELSE 5
        END,
        a.deadline ASC
    ";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $tasks = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Add computed fields
    foreach ($tasks as &$task) {
        $now = new DateTime();
        $startDate = $task['start_date'] ? new DateTime($task['start_date']) : null;
        $deadline = $task['deadline'] ? new DateTime($task['deadline']) : null;
        
        // Check if task has started
        $task['has_started'] = !$startDate || $startDate <= $now;
        
        // Check if overdue
        $task['is_overdue'] = !$task['submitted'] && $deadline && $deadline < $now;
        
        // Format dates
        if ($task['start_date']) {
            $task['start_date_formatted'] = date('d/m/Y H:i', strtotime($task['start_date']));
        }
        if ($task['deadline']) {
            $task['deadline_formatted'] = date('d/m/Y H:i', strtotime($task['deadline']));
        }
        if ($task['submitted_at']) {
            $task['submitted_at_formatted'] = date('d/m/Y H:i', strtotime($task['submitted_at']));
        }
    }
    
    echo json_encode([
        'success' => true,
        'tasks' => $tasks
    ]);
    
} catch(Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>