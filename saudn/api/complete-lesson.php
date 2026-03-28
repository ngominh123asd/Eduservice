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

    // Get JSON input
    $input = json_decode(file_get_contents('php://input'), true);
    $lesson_id = $input['lesson_id'] ?? null;
    $time_spent = $input['time_spent'] ?? 0;
    $start_time_iso = $input['start_time'] ?? null;
    $end_time_iso = $input['end_time'] ?? null;

    if (!$lesson_id) {
        throw new Exception('Lesson ID required');
    }

    // Verify lesson exists and user is enrolled
    $stmt = $pdo->prepare("
        SELECT l.id, l.min_duration_minutes, ch.class_id
        FROM lessons l
        JOIN chapters ch ON l.chapter_id = ch.id
        WHERE l.id = ?
    ");
    $stmt->execute([$lesson_id]);
    $lesson = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$lesson) {
        throw new Exception('Lesson not found');
    }

    // Check enrollment
    $stmt = $pdo->prepare("
        SELECT COUNT(*) 
        FROM class_enrollments 
        WHERE class_id = ? AND user_id = ?
    ");
    $stmt->execute([$lesson['class_id'], $user_id]);
    if (!$stmt->fetchColumn()) {
        throw new Exception('Not enrolled in this class');
    }

    // Verify minimum time requirement
    $minDurationSeconds = ($lesson['min_duration_minutes'] ?? 0) * 60;
    if ($time_spent < $minDurationSeconds) {
        throw new Exception("Bạn cần học ít nhất {$lesson['min_duration_minutes']} phút để hoàn thành bài học");
    }

    // Convert ISO times to Vietnam timezone
    $start_time = $start_time_iso ? date('Y-m-d H:i:s', strtotime($start_time_iso)) : date('Y-m-d H:i:s');
    $end_time = $end_time_iso ? date('Y-m-d H:i:s', strtotime($end_time_iso)) : date('Y-m-d H:i:s');
    $complete_time = $end_time; // complete_time = end_time

    // Check if lesson_progress record exists
    $stmt = $pdo->prepare("
        SELECT id 
        FROM lesson_progress 
        WHERE lesson_id = ? AND user_id = ?
    ");
    $stmt->execute([$lesson_id, $user_id]);
    $progress = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($progress) {
        // Update existing record - GIỮ NGUYÊN start_time cũ
        $stmt = $pdo->prepare("
            UPDATE lesson_progress 
            SET complete_time = ?,
                end_time = ?,
                time_spent = ?,
                status = 'completed',
                attempts = attempts + 1,
                last_attempt_at = ?
            WHERE lesson_id = ? AND user_id = ?
        ");
        $stmt->execute([
            $complete_time,  // complete_time
            $end_time,       // end_time (thời điểm click nút)
            $time_spent,
            $end_time,       // last_attempt_at
            $lesson_id,
            $user_id
        ]);
    } else {
        // Insert new record
        $stmt = $pdo->prepare("
            INSERT INTO lesson_progress 
            (lesson_id, user_id, start_time, complete_time, end_time, time_spent, status, attempts, last_attempt_at)
            VALUES (?, ?, ?, ?, ?, ?, 'completed', 1, ?)
        ");
        $stmt->execute([
            $lesson_id,
            $user_id,
            $start_time,      // start_time (từ client)
            $complete_time,   // complete_time
            $end_time,        // end_time (thời điểm click nút)
            $time_spent,
            $end_time         // last_attempt_at
        ]);
    }

    // Get updated stats
    $stmt = $pdo->prepare("
        SELECT COUNT(*) 
        FROM lesson_progress 
        WHERE user_id = ? AND complete_time IS NOT NULL
    ");
    $stmt->execute([$user_id]);
    $completed_count = $stmt->fetchColumn();

    echo json_encode([
        'success' => true,
        'message' => 'Lesson completed successfully',
        'completed_count' => $completed_count,
        'time_spent' => $time_spent,
        'start_time' => $start_time,
        'end_time' => $end_time,
        'duration_readable' => gmdate("H:i:s", $time_spent)
    ]);
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
