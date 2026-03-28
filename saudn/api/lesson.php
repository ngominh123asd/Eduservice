<?php
session_start();
header('Content-Type: application/json');

// Set timezone to Vietnam
date_default_timezone_set('Asia/Ho_Chi_Minh');

if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['student', 'admin', 'teacher'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

require_once __DIR__ . '/../../db/db_config.php';

try {
    $user_id = $_SESSION['user_id'];
    $lesson_id = $_GET['id'] ?? null;
    
    if (!$lesson_id) {
        throw new Exception('Lesson ID required');
    }
    
    // Get lesson details
    $stmt = $pdo->prepare("
        SELECT 
            l.id,
            l.title as lesson_name,
            l.type as lesson_type,
            l.description,
            l.file_path,
            l.min_duration_minutes,
            l.max_score,
            l.start_date,
            l.end_date,
            l.order_index,
            ch.class_id,
            ch.title as chapter_name,
            CASE WHEN lp.complete_time IS NOT NULL THEN 1 ELSE 0 END as is_completed,
            lp.time_spent,
            lp.score,
            lp.complete_time,
            lp.end_time
        FROM lessons l
        JOIN chapters ch ON l.chapter_id = ch.id
        LEFT JOIN lesson_progress lp ON l.id = lp.lesson_id AND lp.user_id = ?
        WHERE l.id = ?
    ");
    $stmt->execute([$user_id, $lesson_id]);
    $lesson = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$lesson) {
        throw new Exception('Lesson not found');
    }
    
    // Verify student is enrolled in this class
    $stmt = $pdo->prepare("
        SELECT COUNT(*) 
        FROM class_enrollments 
        WHERE class_id = ? AND user_id = ?
    ");
    $stmt->execute([$lesson['class_id'], $user_id]);
    if (!$stmt->fetchColumn()) {
        throw new Exception('Not enrolled in this class');
    }
    
    // ✅ CHECK IF LESSON HAS STARTED
    $now = new DateTime('now', new DateTimeZone('Asia/Ho_Chi_Minh'));
    $has_started = true;
    $time_until_start = null;
    
    if ($lesson['start_date']) {
        $start_date = new DateTime($lesson['start_date'], new DateTimeZone('Asia/Ho_Chi_Minh'));
        $has_started = $now >= $start_date;
        
        if (!$has_started) {
            $interval = $now->diff($start_date);
            $time_until_start = [
                'days' => $interval->days,
                'hours' => $interval->h,
                'minutes' => $interval->i,
                'seconds' => $interval->s,
                'total_seconds' => ($interval->days * 86400) + ($interval->h * 3600) + ($interval->i * 60) + $interval->s
            ];
        }
    }
    
    // ✅ If lesson hasn't started, return limited info
    if (!$has_started) {
        echo json_encode([
            'success' => false,
            'locked' => true,
            'message' => 'Bài học chưa đến giờ mở',
            'lesson' => [
                'id' => $lesson['id'],
                'lesson_name' => $lesson['lesson_name'],
                'lesson_type' => $lesson['lesson_type'],
                'description' => $lesson['description'],
                'start_date' => $lesson['start_date'],
                'start_date_formatted' => $start_date->format('d/m/Y H:i'),
                'has_started' => false,
                'time_until_start' => $time_until_start
            ]
        ]);
        exit();
    }
    
    // Convert min_duration_minutes to seconds for frontend compatibility
    $lesson['min_duration'] = ($lesson['min_duration_minutes'] ?? 0) * 60;
    $lesson['has_started'] = true;
    $lesson['time_until_start'] = null;
    
    // Extract file extension and determine if it's PDF
    if ($lesson['file_path']) {
        $extension = strtolower(pathinfo($lesson['file_path'], PATHINFO_EXTENSION));
        $lesson['pdf_file'] = ($extension === 'pdf') ? basename($lesson['file_path']) : null;
        $lesson['video_url'] = in_array($extension, ['mp4', 'webm', 'ogg']) ? $lesson['file_path'] : null;
    } else {
        $lesson['pdf_file'] = null;
        $lesson['video_url'] = null;
    }
    
    // Format dates if they exist
    if ($lesson['start_date']) {
        $lesson['start_date_formatted'] = $start_date->format('d/m/Y H:i');
    }
    if ($lesson['end_date']) {
        $end_date = new DateTime($lesson['end_date'], new DateTimeZone('Asia/Ho_Chi_Minh'));
        $lesson['end_date_formatted'] = $end_date->format('d/m/Y H:i');
    }
    if ($lesson['complete_time']) {
        $lesson['completed_at'] = date('d/m/Y H:i:s', strtotime($lesson['complete_time']));
    }
    if ($lesson['end_time']) {
        $lesson['ended_at'] = date('d/m/Y H:i:s', strtotime($lesson['end_time']));
    }
    
    echo json_encode([
        'success' => true,
        'lesson' => $lesson
    ]);
    
} catch(Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>
