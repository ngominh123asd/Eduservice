<?php
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
header('Content-Type: application/json; charset=utf-8');

session_start();

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

require_once __DIR__ . '/../../db/db_config.php';

try {
    $input = json_decode(file_get_contents('php://input'), true);
    $message = $input['message'] ?? '';
    $history = $input['history'] ?? [];
    
    if (empty($message)) {
        throw new Exception('Message is required');
    }
    
    $user_id = $_SESSION['user_id'];
    
    // Process message and generate response
    $response = processAIChat($message, $user_id, $history, $pdo);
    
    echo json_encode([
        'success' => true,
        'response' => $response
    ]);
    
} catch (Exception $e) {
    error_log('AI Chat error: ' . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

function processAIChat($message, $user_id, $history, $pdo) {
    $message_lower = mb_strtolower($message, 'UTF-8');
    
    // ✅ Check for class-related queries
    if (strpos($message_lower, 'lớp') !== false || strpos($message_lower, 'class') !== false) {
        return handleClassQuery($user_id, $pdo);
    }
    
    // ✅ Check for task/assignment queries
    if (strpos($message_lower, 'nhiệm vụ') !== false || strpos($message_lower, 'bài tập') !== false || strpos($message_lower, 'task') !== false) {
        return handleTaskQuery($user_id, $pdo);
    }
    
    // ✅ Check for score queries
    if (strpos($message_lower, 'điểm') !== false || strpos($message_lower, 'score') !== false) {
        return handleScoreQuery($user_id, $pdo);
    }
    
    // ✅ Check for progress queries
    if (strpos($message_lower, 'tiến độ') !== false || strpos($message_lower, 'progress') !== false) {
        return handleProgressQuery($user_id, $pdo);
    }
    
    // ✅ Default helpful response
    return "Tôi có thể giúp bạn với:\n\n" .
           "**Lớp học**: Hỏi về các lớp học bạn đang tham gia\n" .
           "**Nhiệm vụ**: Xem danh sách nhiệm vụ chưa hoàn thành\n" .
           "**Điểm số**: Kiểm tra điểm trung bình của bạn\n" .
           "**Tiến độ**: Theo dõi tiến độ học tập\n\n" .
           "Bạn muốn biết về vấn đề nào?";
}

function handleClassQuery($user_id, $pdo) {
    $stmt = $pdo->prepare("
        SELECT c.*, t.fullname as teacher_name
        FROM class_enrollments ce
        JOIN classes c ON ce.class_id = c.id
        LEFT JOIN users t ON c.teacher_id = t.id
        WHERE ce.user_id = ?
        ORDER BY c.created_at DESC
    ");
    $stmt->execute([$user_id]);
    $classes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($classes)) {
        return "Bạn chưa tham gia lớp học nào. Hãy đăng ký lớp học để bắt đầu học tập! 📚";
    }
    
    $response = "📚 **Các lớp học của bạn**:\n\n";
    foreach ($classes as $class) {
        $response .= "• **{$class['class_name']}**\n";
        $response .= "  Giảng viên: {$class['teacher_name']}\n";
        $response .= "  Mã lớp: " . ($class['code'] ?? 'N/A') . "\n\n";
    }
    
    $response .= "Bạn muốn biết thêm về lớp nào không? 😊";
    
    return $response;
}

function handleTaskQuery($user_id, $pdo) {
    try {
        $stmt = $pdo->prepare("
            SELECT a.title, c.class_name, a.due_date as end_date
            FROM assignments a
            JOIN classes c ON a.class_id = c.id
            JOIN class_enrollments ce ON c.id = ce.class_id
            LEFT JOIN submissions s ON a.id = s.assignment_id AND s.user_id = ?
            WHERE ce.user_id = ? 
            AND s.id IS NULL
            AND (a.due_date IS NULL OR a.due_date >= DATE('now', 'localtime'))
            ORDER BY a.due_date ASC
            LIMIT 5
        ");
        $stmt->execute([$user_id, $user_id]);
        $tasks = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        return "Hiện chưa có bài tập/nhiệm vụ nào trong hệ thống.";
    }
    
    if (empty($tasks)) {
        return "🎉 Tuyệt vời! Bạn đã hoàn thành tất cả nhiệm vụ hiện tại!";
    }
    
    $response = "📝 **Nhiệm vụ chưa hoàn thành** ({count} nhiệm vụ):\n\n";
    $response = str_replace('{count}', count($tasks), $response);
    
    foreach ($tasks as $task) {
        $response .= "• **{$task['title']}**\n";
        $response .= "  Lớp: {$task['class_name']}\n";
        if ($task['end_date']) {
            $response .= "  Hạn: " . date('d/m/Y', strtotime($task['end_date'])) . "\n";
        }
        $response .= "\n";
    }
    
    $response .= "Hãy cố gắng hoàn thành đúng hạn nhé! 💪";
    
    return $response;
}

function handleScoreQuery($user_id, $pdo) {
    try {
        $stmt = $pdo->prepare("
            SELECT AVG(lp.score) as avg_score, COUNT(*) as total_lessons
            FROM lesson_progress lp
            WHERE lp.user_id = ? AND lp.score IS NOT NULL
        ");
        $stmt->execute([$user_id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        return "Bạn chưa có điểm số nào. Hãy hoàn thành bài học để nhận điểm! 📊";
    }
    
    $avg_score = round($result['avg_score'] ?? 0, 1);
    $total = $result['total_lessons'] ?? 0;
    
    if ($total == 0) {
        return "Bạn chưa có điểm số nào. Hãy hoàn thành bài học để nhận điểm! 📊";
    }
    
    $response = "📊 **Thống kê điểm số của bạn**:\n\n";
    $response .= "• Điểm trung bình: **{$avg_score}**/10\n";
    $response .= "• Tổng số bài đã làm: **{$total}** bài\n\n";
    
    if ($avg_score >= 8) {
        $response .= "🌟 Xuất sắc! Tiếp tục phát huy nhé!";
    } elseif ($avg_score >= 6.5) {
        $response .= "👍 Tốt lắm! Cố gắng thêm nhé!";
    } else {
        $response .= "💪 Đừng nản lòng! Mỗi ngày tiến bộ một chút!";
    }
    
    return $response;
}

function handleProgressQuery($user_id, $pdo) {
    try {
        $stmt = $pdo->prepare("
            SELECT 
                COUNT(DISTINCT l.id) as total_lessons,
                COUNT(DISTINCT CASE WHEN lp.complete_time IS NOT NULL THEN l.id END) as completed_lessons,
                SUM(0) as total_time
            FROM lessons l
            JOIN class_enrollments ce ON l.class_id = ce.class_id
            LEFT JOIN lesson_progress lp ON l.id = lp.lesson_id AND lp.user_id = ?
            WHERE ce.user_id = ?
        ");
        $stmt->execute([$user_id, $user_id]);
        $progress = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        return "Chưa có dữ liệu tiến độ. Hãy bắt đầu bài học đầu tiên! 📈";
    }
    
    $total = $progress['total_lessons'] ?? 0;
    $completed = $progress['completed_lessons'] ?? 0;
    $time_spent = $progress['total_time'] ?? 0;
    
    $percentage = $total > 0 ? round(($completed / $total) * 100, 1) : 0;
    $hours = floor($time_spent / 60);
    $minutes = $time_spent % 60;
    
    $response = "📈 **Tiến độ học tập của bạn**:\n\n";
    $response .= "• Hoàn thành: **{$completed}/{$total}** bài ({$percentage}%)\n";
    
    if ($percentage >= 80) {
        $response .= "\n🎯 Tuyệt vời! Bạn gần hoàn thành rồi!";
    } elseif ($percentage >= 50) {
        $response .= "\n👏 Làm tốt lắm! Cố gắng thêm nhé!";
    } else {
        $response .= "\n💪 Hãy tiếp tục cố gắng! Mỗi bước đi đều quan trọng!";
    }
    
    return $response;
}
?>
