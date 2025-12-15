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
    
    $input = json_decode(file_get_contents('php://input'), true);
    $lesson_id = $input['lesson_id'] ?? null;
    $start_time_iso = $input['start_time'] ?? null;
    
    if (!$lesson_id) {
        throw new Exception('Lesson ID required');
    }
    
    // Convert ISO time to Vietnam timezone
    $start_time = date('Y-m-d H:i:s', strtotime($start_time_iso));
    
    // Check if progress exists
    $stmt = $pdo->prepare("
        SELECT id 
        FROM lesson_progress 
        WHERE lesson_id = ? AND user_id = ?
    ");
    $stmt->execute([$lesson_id, $user_id]);
    $exists = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($exists) {
        // Update start_time only if not completed
        $stmt = $pdo->prepare("
            UPDATE lesson_progress 
            SET start_time = ?
            WHERE lesson_id = ? 
            AND user_id = ? 
            AND complete_time IS NULL
        ");
        $stmt->execute([$start_time, $lesson_id, $user_id]);
    } else {
        // Insert new record with start_time
        $stmt = $pdo->prepare("
            INSERT INTO lesson_progress 
            (lesson_id, user_id, start_time, status)
            VALUES (?, ?, ?, 'in_progress')
        ");
        $stmt->execute([$lesson_id, $user_id, $start_time]);
    }
    
    echo json_encode([
        'success' => true,
        'start_time' => $start_time
    ]);
    
} catch(Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>