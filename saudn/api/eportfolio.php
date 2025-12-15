<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

require_once __DIR__ . '/../../db/db_config.php';

try {
    $user_id = $_SESSION['user_id'];
    
    // Get user info
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Initialize empty arrays
    $activities = [];
    $skills = [];
    
    // Try to get activities - check if tables exist
    try {
        // Get all table names
        $tables = $pdo->query("SELECT name FROM sqlite_master WHERE type='table'")->fetchAll(PDO::FETCH_COLUMN);
        
        // Check if required tables exist for activities
        if (in_array('submissions', $tables) && in_array('tasks', $tables) && in_array('classes', $tables)) {
            $stmt = $pdo->prepare("
                SELECT 
                    t.task_name as activity_name,
                    t.description,
                    t.created_at as date,
                    c.class_name as organization,
                    s.score,
                    s.feedback,
                    s.file_path,
                    s.submitted_at,
                    t.max_score
                FROM submissions s
                JOIN tasks t ON s.task_id = t.id
                JOIN classes c ON t.class_id = c.id
                WHERE s.user_id = ?
                ORDER BY s.submitted_at DESC
            ");
            $stmt->execute([$user_id]);
            $activities = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
        
        // Check if required tables exist for skills
        if (in_array('user_lessons', $tables) && in_array('lessons', $tables) && 
            in_array('chapters', $tables) && in_array('classes', $tables)) {
            $stmt = $pdo->prepare("
                SELECT 
                    l.lesson_name,
                    l.lesson_type,
                    ul.completed_at,
                    c.class_name
                FROM user_lessons ul
                JOIN lessons l ON ul.lesson_id = l.id
                JOIN chapters ch ON l.chapter_id = ch.id
                JOIN classes c ON ch.class_id = c.id
                WHERE ul.user_id = ? AND ul.is_completed = 1
                ORDER BY ul.completed_at DESC
            ");
            $stmt->execute([$user_id]);
            $skills = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
    } catch (PDOException $e) {
        // If any query fails, continue with empty arrays
        error_log("E-Portfolio query error: " . $e->getMessage());
    }
    
    // Calculate statistics
    $total_activities = count($activities);
    $total_hours = count($skills) * 2; // Estimate 2 hours per lesson
    $avg_score = 0;
    
    if ($total_activities > 0) {
        $scores = array_filter(array_map(function($a) {
            return $a['score'] ?? null;
        }, $activities), function($score) {
            return $score !== null;
        });
        
        if (count($scores) > 0) {
            $avg_score = array_sum($scores) / count($scores);
        }
    }
    
    // Add default info if not in database
    if (!isset($user['school_name'])) {
        $user['school_name'] = 'EDUSERVICE';
    }
    if (!isset($user['major'])) {
        $user['major'] = 'Thành viên tình nguyện';
    }
    
    echo json_encode([
        'success' => true,
        'user' => $user,
        'activities' => $activities,
        'skills' => $skills,
        'stats' => [
            'total_activities' => $total_activities,
            'total_hours' => $total_hours,
            'avg_score' => round($avg_score, 1),
            'total_skills' => count($skills)
        ]
    ]);
    
} catch (PDOException $e) {
    echo json_encode([
        'success' => false, 
        'message' => $e->getMessage(),
        'debug' => [
            'file' => $e->getFile(),
            'line' => $e->getLine()
        ]
    ]);
}
?>