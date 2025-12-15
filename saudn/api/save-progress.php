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
    $data = json_decode(file_get_contents('php://input'), true);
    
    $lesson_id = $data['lesson_id'] ?? null;
    $time_spent = $data['time_spent'] ?? 0;
    
    if (!$lesson_id) {
        throw new Exception('Lesson ID required');
    }
    
    // Check if progress exists
    $stmt = $pdo->prepare("
        SELECT id FROM lesson_progress 
        WHERE lesson_id = ? AND user_id = ?
    ");
    $stmt->execute([$lesson_id, $user_id]);
    $existing = $stmt->fetch();
    
    if ($existing) {
        // Update time spent
        $stmt = $pdo->prepare("
            UPDATE lesson_progress 
            SET time_spent = time_spent + ?
            WHERE lesson_id = ? AND user_id = ?
        ");
        $stmt->execute([$time_spent, $lesson_id, $user_id]);
    } else {
        // Create new progress record
        $stmt = $pdo->prepare("
            INSERT INTO lesson_progress 
            (lesson_id, user_id, start_time, time_spent, status)
            VALUES (?, ?, datetime('now'), ?, 0)
        ");
        $stmt->execute([$lesson_id, $user_id, $time_spent]);
    }
    
    echo json_encode([
        'success' => true,
        'message' => 'Progress saved'
    ]);
    
} catch(Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>