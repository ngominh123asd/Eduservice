<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'teacher') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

require_once __DIR__ . '/../../db/db_config.php';

try {
    $input = json_decode(file_get_contents('php://input'), true);
    
    $assignment_id = $input['assignment_id'] ?? null;
    $title = $input['title'] ?? null;
    $description = $input['description'] ?? '';
    $start_date = $input['start_date'] ?? null;
    $deadline = $input['deadline'] ?? null;
    $max_score = $input['max_score'] ?? 10;
    
    // Validate required fields
    if (!$assignment_id || !$title || !$deadline) {
        throw new Exception('Missing required fields');
    }
    
    // Verify assignment belongs to teacher
    $stmt = $pdo->prepare("
        SELECT a.id, c.teacher_id
        FROM assignments a
        JOIN classes c ON a.class_id = c.id
        WHERE a.id = ?
    ");
    $stmt->execute([$assignment_id]);
    $assignment = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$assignment) {
        throw new Exception('Assignment not found');
    }
    
    if ($assignment['teacher_id'] != $_SESSION['user_id']) {
        throw new Exception('Unauthorized to edit this assignment');
    }
    
    // Validate dates
    if ($start_date && $deadline) {
        if (strtotime($start_date) > strtotime($deadline)) {
            throw new Exception('Start date cannot be after deadline');
        }
    }
    
    // Update assignment
    $stmt = $pdo->prepare("
        UPDATE assignments 
        SET title = ?,
            description = ?,
            start_date = ?,
            deadline = ?,
            max_score = ?
        WHERE id = ?
    ");
    
    $stmt->execute([
        $title,
        $description,
        $start_date,
        $deadline,
        $max_score,
        $assignment_id
    ]);
    
    echo json_encode([
        'success' => true,
        'message' => 'Assignment updated successfully'
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>