<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'teacher') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

require_once __DIR__ . '/../../db/db_config.php';

try {
    $assignment_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
    
    if (!$assignment_id) {
        throw new Exception('Invalid assignment ID');
    }

    // Verify teacher owns this assignment and get assignment details
    $stmt = $pdo->prepare("
        SELECT 
            a.*,
            c.class_name,
            c.id as class_id
        FROM assignments a
        JOIN classes c ON a.class_id = c.id
        WHERE a.id = ? AND c.teacher_id = ?
    ");
    
    $stmt->execute([$assignment_id, $_SESSION['user_id']]);
    $assignment = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$assignment) {
        throw new Exception('Assignment not found or unauthorized');
    }

    // Format dates for frontend
    if ($assignment['start_date']) {
        $assignment['start_date'] = date('Y-m-d\TH:i', strtotime($assignment['start_date']));
    }
    if ($assignment['deadline']) {
        $assignment['deadline'] = date('Y-m-d\TH:i', strtotime($assignment['deadline']));
    }

    echo json_encode([
        'success' => true,
        'assignment' => $assignment
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
} catch (PDOException $e) {
    error_log($e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Database error occurred'
    ]);
}