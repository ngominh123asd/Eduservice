<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

require_once __DIR__ . '/../../db/db_config.php';

try {
    $user_id = $_SESSION['user_id'];
    
    // Get lesson completion stats
    $stmt = $pdo->prepare("
        SELECT 
            COUNT(DISTINCT lesson_id) as total_completed,
            SUM(time_spent) as total_time
        FROM lesson_progress
        WHERE user_id = ? AND complete_time = 1
    ");
    $stmt->execute([$user_id]);
    $lesson_stats = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Get submission stats
    $stmt = $pdo->prepare("
        SELECT 
            COUNT(*) as total_submissions,
            COUNT(CASE WHEN status = 'graded' THEN 1 END) as graded_submissions,
            AVG(CASE WHEN score IS NOT NULL THEN score END) as avg_score
        FROM submissions
        WHERE user_id = ?
    ");
    $stmt->execute([$user_id]);
    $submission_stats = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Get perfect scores count
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as perfect_scores
        FROM submissions s
        JOIN assignments a ON s.assignment_id = a.id
        WHERE s.user_id = ? AND s.score = a.max_score
    ");
    $stmt->execute([$user_id]);
    $perfect_scores = $stmt->fetchColumn();
    
    // Get streak (consecutive days with completed lessons)
    $stmt = $pdo->prepare("
        SELECT DATE(complete_time) as date
        FROM lesson_progress
        WHERE user_id = ? AND complete_time = 1
        GROUP BY DATE(complete_time)
        ORDER BY date DESC
    ");
    $stmt->execute([$user_id]);
    $dates = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    $current_streak = 0;
    $max_streak = 0;
    $temp_streak = 0;
    
    for ($i = 0; $i < count($dates); $i++) {
        if ($i == 0) {
            $temp_streak = 1;
        } else {
            $date1 = new DateTime($dates[$i]);
            $date2 = new DateTime($dates[$i-1]);
            $diff = $date2->diff($date1)->days;
            
            if ($diff == 1) {
                $temp_streak++;
            } else {
                $temp_streak = 1;
            }
        }
        
        if ($i == 0) {
            $current_streak = $temp_streak;
        }
        
        $max_streak = max($max_streak, $temp_streak);
    }
    
    // Define achievements
    $achievements = [
        [
            'id' => 'first_lesson',
            'name' => 'Bước đầu tiên',
            'description' => 'Hoàn thành bài học đầu tiên',
            'icon' => 'fa-star',
            'unlocked' => $lesson_stats['total_completed'] >= 1
        ],
        [
            'id' => 'lesson_10',
            'name' => 'Người học siêng năng',
            'description' => 'Hoàn thành 10 bài học',
            'icon' => 'fa-book',
            'unlocked' => $lesson_stats['total_completed'] >= 10
        ],
        [
            'id' => 'lesson_50',
            'name' => 'Chuyên gia học tập',
            'description' => 'Hoàn thành 50 bài học',
            'icon' => 'fa-graduation-cap',
            'unlocked' => $lesson_stats['total_completed'] >= 50
        ],
        [
            'id' => 'perfect_score',
            'name' => 'Điểm tuyệt đối',
            'description' => 'Đạt điểm tối đa trong 1 bài tập',
            'icon' => 'fa-trophy',
            'unlocked' => $perfect_scores >= 1
        ],
        [
            'id' => 'perfect_score_5',
            'name' => 'Hoàn hảo',
            'description' => 'Đạt điểm tối đa trong 5 bài tập',
            'icon' => 'fa-crown',
            'unlocked' => $perfect_scores >= 5
        ],
        [
            'id' => 'streak_7',
            'name' => 'Kiên trì',
            'description' => 'Học liên tục 7 ngày',
            'icon' => 'fa-fire',
            'unlocked' => $max_streak >= 7
        ],
        [
            'id' => 'streak_30',
            'name' => 'Bất khuất',
            'description' => 'Học liên tục 30 ngày',
            'icon' => 'fa-medal',
            'unlocked' => $max_streak >= 30
        ],
        [
            'id' => 'high_score',
            'name' => 'Điểm cao',
            'description' => 'Đạt điểm trung bình trên 80',
            'icon' => 'fa-chart-line',
            'unlocked' => ($submission_stats['avg_score'] ?? 0) >= 80
        ]
    ];
    
    echo json_encode([
        'success' => true,
        'achievements' => $achievements,
        'stats' => [
            'lessons_completed' => $lesson_stats['total_completed'] ?? 0,
            'total_time' => $lesson_stats['total_time'] ?? 0,
            'submissions' => $submission_stats['total_submissions'] ?? 0,
            'avg_score' => round($submission_stats['avg_score'] ?? 0, 1),
            'perfect_scores' => $perfect_scores,
            'current_streak' => $current_streak,
            'max_streak' => $max_streak
        ]
    ]);
    
} catch(PDOException $e) {
    error_log('Achievements API error: ' . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Database error occurred'
    ]);
}
?>