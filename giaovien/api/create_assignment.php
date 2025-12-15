<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'teacher') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

require_once __DIR__ . '/../../db/db_config.php';

try {
    $data = json_decode(file_get_contents('php://input'), true);
    $teacher_id = $_SESSION['user_id'];
    
    // Validate required fields
    if (!isset($data['class_id'], $data['title'], $data['deadline'])) {
        throw new Exception('Missing required fields');
    }

    $class_id = filter_var($data['class_id'], FILTER_VALIDATE_INT);
    $title = trim($data['title']);
    $description = isset($data['description']) ? trim($data['description']) : null;
    $start_date = !empty($data['start_date']) ? $data['start_date'] : null;
    $deadline = $data['deadline'];
    $max_score = isset($data['max_score']) ? filter_var($data['max_score'], FILTER_VALIDATE_INT) : 10;
    $created_at = date('Y-m-d H:i:s');

    // Validate start_date and deadline
    if ($start_date) {
        $start_timestamp = strtotime($start_date);
        $deadline_timestamp = strtotime($deadline);
        if ($start_timestamp > $deadline_timestamp) {
            throw new Exception('Ngày bắt đầu không thể sau hạn nộp');
        }
    }

    // Validate class ownership
    $stmt = $pdo->prepare("SELECT id FROM classes WHERE id = ? AND teacher_id = ?");
    $stmt->execute([$class_id, $teacher_id]);
    if (!$stmt->fetch()) {
        throw new Exception('Class not found or unauthorized');
    }

    // Create assignment
    $stmt = $pdo->prepare("
        INSERT INTO assignments (
            class_id, 
            title, 
            description, 
            start_date,
            deadline, 
            max_score, 
            created_at
        ) 
        VALUES (?, ?, ?, ?, ?, ?, ?)
    ");
    
    $stmt->execute([
        $class_id,
        $title,
        $description,
        $start_date,
        $deadline,
        $max_score,
        $created_at
    ]);
    
    echo json_encode([
        'success' => true,
        'message' => 'Assignment created successfully',
        'assignment_id' => $pdo->lastInsertId()
    ]);

} catch (Exception $e) {
    error_log('Assignment creation error: ' . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Error creating assignment: ' . $e->getMessage()
    ]);
} catch (PDOException $e) {
    error_log('Database error: ' . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Database error occurred'
    ]);
}
?>