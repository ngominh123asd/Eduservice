<?php
// File: giaovien/api/delete_assignment.php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'teacher') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

require_once __DIR__ . '/../../db/db_config.php';

try {
    $data = json_decode(file_get_contents('php://input'), true);
    $assignment_id = $data['assignment_id'] ?? null;
    $teacher_id = $_SESSION['user_id'];
    
    if (!$assignment_id) {
        echo json_encode(['success' => false, 'message' => 'Assignment ID required']);
        exit();
    }
    
    // Verify teacher owns the class that contains this assignment
    $stmt = $pdo->prepare("
        SELECT a.id 
        FROM assignments a
        JOIN classes c ON a.class_id = c.id
        WHERE a.id = ? AND c.teacher_id = ?
    ");
    $stmt->execute([$assignment_id, $teacher_id]);
    if (!$stmt->fetch()) {
        echo json_encode(['success' => false, 'message' => 'Assignment not found']);
        exit();
    }
    
    // Delete assignment (cascade will handle related submissions)
    $stmt = $pdo->prepare("DELETE FROM assignments WHERE id = ?");
    $stmt->execute([$assignment_id]);
    
    echo json_encode([
        'success' => true,
        'message' => 'Assignment deleted successfully'
    ]);
    
} catch(PDOException $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Database error: ' . $e->getMessage()
    ]);
}
?>